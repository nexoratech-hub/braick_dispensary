<?php
// ================================================================
// FILE: frontend/pages/admin/audit/view_prescription.php
// AUDIT - VIEW PRESCRIPTION DETAILS (BLUE THEME) - V7 FINAL
// ✅ Load ALL items za visit_id husika
// ✅ PAID status per item (from bill_items.status)
// ✅ Scroll buttons <>
// ✅ Delete Item - FINAL LOGIC:
//    - PAID/DISPENSED  → Stock NOT RETURNED, Bill REDUCED (was in revenue)
//    - PENDING/CONFIRMED → Stock RETURNED, Bill NOT REDUCED (not yet counted)
// ✅ Delete Whole Rx
// ✅ BLUE THEME (#0B5ED7)
// ✅ ENGLISH ONLY
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$allowed_roles = ['admin', 'audit'];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$is_admin = ($user_role === 'admin');
$is_audit = ($user_role === 'audit');

$prescription_id = (int)($_GET['id'] ?? 0);
$visit_id = (int)($_GET['visit_id'] ?? 0);
$patient_id = (int)($_GET['patient_id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($prescription_id <= 0 && $visit_id <= 0 && $patient_id <= 0) {
    header('Location: inventory.php?branch=' . urlencode($selected_branch_id));
    exit;
}

require_once __DIR__ . '/../../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// HANDLE DELETE ACTIONS
// ================================================================
$alert_message = '';
$alert_type = '';
$reload_page = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ✅ AUDIT ROLE CANNOT DELETE
    if ($is_audit) {
        $alert_message = "Audit role cannot delete. Read-only access.";
        $alert_type = 'error';
    } else {
        
        // ==========================================================
        // ✅ DELETE SINGLE ITEM
        // ==========================================================
        if ($_POST['action'] === 'delete_prescription_item' && !empty($_POST['item_id'])) {
            try {
                $del_item_id = (int)$_POST['item_id'];
                $del_presc_id = (int)($_POST['prescription_id'] ?? 0);
                $del_bill_id = (int)($_POST['bill_id'] ?? 0);
                $del_item_name = trim($_POST['item_name'] ?? '');
                
                $db->beginTransaction();
                
                // ✅ 1. GET ITEM INFO
                $stmt = $db->prepare("SELECT medication_name, quantity, total_price, inventory_id FROM prescription_items WHERE id = ?");
                $stmt->execute([$del_item_id]);
                $item_info = $stmt->fetch(PDO::FETCH_ASSOC);
                $item_name = $item_info['medication_name'] ?? $del_item_name;
                $item_qty = (int)($item_info['quantity'] ?? 0);
                $item_price = (float)($item_info['total_price'] ?? 0);
                $inventory_id = (int)($item_info['inventory_id'] ?? 0);
                
                // ✅ 2. GET ITEM STATUS
                $item_status = 'pending';
                $bill_item_id = 0;
                
                if ($del_bill_id > 0) {
                    $stmt = $db->prepare("
                        SELECT id, status FROM bill_items 
                        WHERE bill_id = ? 
                        AND item_type = 'medication'
                        AND item_name LIKE ?
                        LIMIT 1
                    ");
                    $stmt->execute([$del_bill_id, '%' . $item_name . '%']);
                    $bill_item = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($bill_item) {
                        $bill_item_id = (int)$bill_item['id'];
                        $item_status = strtolower($bill_item['status'] ?? 'pending');
                    }
                }
                
                // ✅ 3. DELETE ITEM
                $db->prepare("DELETE FROM prescription_items WHERE id = ?")->execute([$del_item_id]);
                
                // ✅ 4. DELETE from bill_items
                if ($bill_item_id > 0) {
                    $db->prepare("DELETE FROM bill_items WHERE id = ?")->execute([$bill_item_id]);
                }
                
                // ============================================================
                // ✅ 5. STOCK LOGIC
                // PENDING/CONFIRMED → RETURN STOCK
                // PAID/DISPENSED → DO NOT RETURN
                // ============================================================
                $stock_returned = false;
                
                if ($inventory_id > 0 && $item_qty > 0) {
                    if ($item_status !== 'paid' && $item_status !== 'dispensed') {
                        try {
                            $db->prepare("UPDATE medications_inventory SET quantity = quantity + ? WHERE id = ?")
                               ->execute([$item_qty, $inventory_id]);
                            $stock_returned = true;
                        } catch (Exception $e) {
                            error_log("Stock return error: " . $e->getMessage());
                        }
                    }
                }
                
                // ============================================================
                // ✅ 6. BILL LOGIC (REVENUE)
                // PAID/DISPENSED  → REDUCE BILL (was in revenue)
                // PENDING/CONFIRMED → DO NOT REDUCE (not yet counted)
                // ============================================================
                $bill_updated = false;
                
                if ($del_bill_id > 0 && $item_price > 0) {
                    if ($item_status === 'paid' || $item_status === 'dispensed') {
                        try {
                            $stmt = $db->prepare("
                                SELECT 
                                    COALESCE(SUM(total_price), 0) as new_subtotal,
                                    COALESCE(SUM(discount_amount), 0) as new_item_discount
                                FROM bill_items 
                                WHERE bill_id = ?
                            ");
                            $stmt->execute([$del_bill_id]);
                            $totals = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            $stmt = $db->prepare("SELECT discount_amount, premium_amount, paid_amount FROM bills WHERE id = ?");
                            $stmt->execute([$del_bill_id]);
                            $bill_extra = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            $bill_discount = (float)($bill_extra['discount_amount'] ?? 0);
                            $premium = (float)($bill_extra['premium_amount'] ?? 0);
                            $paid = (float)($bill_extra['paid_amount'] ?? 0);
                            
                            $new_subtotal = (float)($totals['new_subtotal'] ?? 0);
                            $new_item_discount = (float)($totals['new_item_discount'] ?? 0);
                            $new_total = $new_subtotal - $bill_discount - $new_item_discount + $premium;
                            if ($new_total < 0) $new_total = 0;
                            
                            $new_balance = $new_total - $paid;
                            if ($new_balance < 0) $new_balance = 0;
                            
                            $new_status = 'pending';
                            if ($new_balance <= 0 && $new_total > 0) $new_status = 'paid';
                            elseif ($paid > 0 && $new_balance > 0) $new_status = 'partial';
                            
                            $db->prepare("UPDATE bills SET 
                                subtotal = ?, total_discount = ?, total_amount = ?, 
                                balance = ?, status = ?, updated_at = NOW() 
                                WHERE id = ?
                            ")->execute([
                                $new_subtotal, $new_item_discount, $new_total, 
                                $new_balance, $new_status, $del_bill_id
                            ]);
                            
                            $bill_updated = true;
                        } catch (Exception $e) {
                            error_log("Bill recalc error: " . $e->getMessage());
                        }
                    }
                }
                
                // ✅ 7. LOG ACTIVITY
                try {
                    $log_detail = "Deleted prescription item: $item_name (Qty: $item_qty, Amount: TSh " . number_format($item_price, 0) . ", Status: $item_status) from Rx #$del_presc_id";
                    
                    if ($stock_returned) {
                        $log_detail .= " | Stock returned: +$item_qty units";
                    } else {
                        $log_detail .= " | Stock NOT returned (paid/dispensed)";
                    }
                    
                    if ($bill_updated) {
                        $log_detail .= " | Bill REDUCED: -TSh " . number_format($item_price, 0);
                    } else {
                        $log_detail .= " | Bill NOT reduced (not yet in revenue)";
                    }
                    
                    $db->prepare("INSERT INTO activity_logs 
                        (user_id, branch_id, action, details, ip_address, created_at) 
                        VALUES (?, ?, 'delete_prescription_item', ?, ?, NOW())"
                    )->execute([
                        $user_id,
                        $selected_branch_id !== 'all' ? (int)$selected_branch_id : 1,
                        $log_detail,
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                } catch (Exception $e) {}
                
                $db->commit();
                
                // ✅ Alert message - ENGLISH
                $msg = "Item '$item_name' deleted!";
                
                if ($stock_returned) {
                    $msg .= " | Stock returned: +$item_qty";
                } else {
                    $msg .= " | Stock NOT returned ($item_status)";
                }
                
                if ($bill_updated) {
                    $msg .= " | Bill reduced: -TSh " . number_format($item_price, 0);
                } else {
                    $msg .= " | Bill NOT reduced ($item_status)";
                }
                
                $alert_message = $msg;
                $alert_type = 'success';
                $reload_page = true;
                
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $alert_message = "Error deleting item: " . $e->getMessage();
                $alert_type = 'error';
            }
        }
        
        // ==========================================================
        // ✅ DELETE WHOLE PRESCRIPTION
        // ==========================================================
        if ($_POST['action'] === 'delete_prescription') {
            $del_prescription_id = (int)($_POST['prescription_id'] ?? 0);
            $del_visit_id = (int)($_POST['visit_id'] ?? 0);
            
            try {
                $db->beginTransaction();
                
                $stock_returned_count = 0;
                $stock_not_returned_count = 0;
                $bill_updated = false;
                
                if ($del_visit_id > 0) {
                    // ✅ 1. GET ALL ITEMS with status
                    $stmt = $db->prepare("
                        SELECT pi.id, pi.medication_name, pi.quantity, pi.inventory_id,
                               (SELECT bi.status FROM bill_items bi 
                                   INNER JOIN bills b ON bi.bill_id = b.id 
                                   WHERE b.visit_id = ? 
                                   AND bi.item_type = 'medication' 
                                   AND bi.item_name LIKE CONCAT('%', pi.medication_name, '%')
                                   LIMIT 1
                               ) as bill_item_status
                        FROM prescription_items pi
                        INNER JOIN prescriptions p ON pi.prescription_id = p.id
                        WHERE p.visit_id = ?
                    ");
                    $stmt->execute([$del_visit_id, $del_visit_id]);
                    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // ✅ 2. STOCK LOGIC per item
                    foreach ($all_items as $ai) {
                        $inv_id = (int)($ai['inventory_id'] ?? 0);
                        $qty = (int)($ai['quantity'] ?? 0);
                        $status = strtolower($ai['bill_item_status'] ?? 'pending');
                        
                        if ($inv_id > 0 && $qty > 0) {
                            if ($status !== 'paid' && $status !== 'dispensed') {
                                try {
                                    $db->prepare("UPDATE medications_inventory SET quantity = quantity + ? WHERE id = ?")
                                       ->execute([$qty, $inv_id]);
                                    $stock_returned_count++;
                                } catch (Exception $e) {}
                            } else {
                                $stock_not_returned_count++;
                            }
                        }
                    }
                    
                    // ✅ 3. GET BILL INFO
                    $bill_id = 0;
                    $bill_status = 'pending';
                    if ($patient_id > 0) {
                        $stmt = $db->prepare("SELECT id, status FROM bills WHERE visit_id = ? AND patient_id = ? LIMIT 1");
                        $stmt->execute([$del_visit_id, $patient_id]);
                        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($bill) {
                            $bill_id = (int)$bill['id'];
                            $bill_status = strtolower($bill['status'] ?? 'pending');
                        }
                    }
                    
                    // ✅ 4. DELETE ALL prescriptions
                    $stmt = $db->prepare("SELECT id FROM prescriptions WHERE visit_id = ?");
                    $stmt->execute([$del_visit_id]);
                    $all_rx = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (!empty($all_rx)) {
                        $placeholders = implode(',', array_fill(0, count($all_rx), '?'));
                        $db->prepare("DELETE FROM prescription_items WHERE prescription_id IN ($placeholders)")->execute($all_rx);
                        $db->prepare("DELETE FROM prescriptions WHERE id IN ($placeholders)")->execute($all_rx);
                    }
                    
                    // ✅ 5. DELETE bill_items + UPDATE BILL ONLY IF PAID
                    if ($bill_id > 0) {
                        try {
                            $db->prepare("DELETE FROM bill_items WHERE bill_id = ? AND item_type = 'medication'")
                               ->execute([$bill_id]);
                            
                            if ($bill_status === 'paid') {
                                $stmt = $db->prepare("
                                    SELECT COALESCE(SUM(total_price), 0) as new_subtotal,
                                           COALESCE(SUM(discount_amount), 0) as new_item_discount
                                    FROM bill_items WHERE bill_id = ?
                                ");
                                $stmt->execute([$bill_id]);
                                $totals = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                $stmt = $db->prepare("SELECT discount_amount, premium_amount, paid_amount FROM bills WHERE id = ?");
                                $stmt->execute([$bill_id]);
                                $bill_extra = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                $bill_discount = (float)($bill_extra['discount_amount'] ?? 0);
                                $premium = (float)($bill_extra['premium_amount'] ?? 0);
                                $paid = (float)($bill_extra['paid_amount'] ?? 0);
                                
                                $new_subtotal = (float)($totals['new_subtotal'] ?? 0);
                                $new_item_discount = (float)($totals['new_item_discount'] ?? 0);
                                $new_total = $new_subtotal - $bill_discount - $new_item_discount + $premium;
                                if ($new_total < 0) $new_total = 0;
                                
                                $new_balance = $new_total - $paid;
                                if ($new_balance < 0) $new_balance = 0;
                                
                                $new_status = 'pending';
                                if ($new_balance <= 0 && $new_total > 0) $new_status = 'paid';
                                elseif ($paid > 0 && $new_balance > 0) $new_status = 'partial';
                                
                                $db->prepare("UPDATE bills SET 
                                    subtotal = ?, total_discount = ?, total_amount = ?, 
                                    balance = ?, status = ?, updated_at = NOW() 
                                    WHERE id = ?
                                ")->execute([
                                    $new_subtotal, $new_item_discount, $new_total, 
                                    $new_balance, $new_status, $bill_id
                                ]);
                                
                                $bill_updated = true;
                            }
                        } catch (Exception $e) {
                            error_log("Bill update error: " . $e->getMessage());
                        }
                    }
                    
                    $log_msg = "Deleted ALL prescriptions for visit #$del_visit_id";
                    if ($stock_returned_count > 0) {
                        $log_msg .= " | Stock returned: $stock_returned_count items";
                    }
                    if ($stock_not_returned_count > 0) {
                        $log_msg .= " | Stock NOT returned: $stock_not_returned_count items (paid/dispensed)";
                    }
                    if ($bill_updated) {
                        $log_msg .= " | Bill updated (was paid)";
                    } else {
                        $log_msg .= " | Bill NOT updated (status: $bill_status - not in revenue)";
                    }
                } else {
                    // Single Rx delete
                    $stmt = $db->prepare("SELECT prescription_number FROM prescriptions WHERE id = ?");
                    $stmt->execute([$del_prescription_id]);
                    $presc = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $db->prepare("DELETE FROM prescription_items WHERE prescription_id = ?")->execute([$del_prescription_id]);
                    $db->prepare("DELETE FROM prescriptions WHERE id = ?")->execute([$del_prescription_id]);
                    
                    $log_msg = "Deleted prescription: " . ($presc['prescription_number'] ?? 'N/A');
                }
                
                try {
                    $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) 
                                  VALUES (?, ?, 'delete_prescription', ?, ?, NOW())")
                       ->execute([
                           $user_id,
                           $selected_branch_id !== 'all' ? (int)$selected_branch_id : 1,
                           $log_msg . " by " . $user_role,
                           $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                       ]);
                } catch (Exception $e) {}
                
                $db->commit();
                
                $alert_message = "Prescription(s) deleted successfully!";
                $alert_type = 'success';
                
                header("refresh:2;url=inventory.php?branch=" . urlencode($selected_branch_id));
                exit;
                
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $alert_message = "Error deleting prescription: " . $e->getMessage();
                $alert_type = 'error';
            }
        }
    }
}

// CURRENCY
$currency = 'TSh';
try {
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['setting_value'])) $currency = $row['setting_value'];
} catch (Exception $e) {}

// ================================================================
// GET VISIT + PATIENT INFO
// ================================================================
$prescription = null;
if ($visit_id > 0) {
    try {
        $sql = "SELECT 
            v.id as visit_ref_id,
            v.visit_number,
            v.visit_date,
            v.diagnosis,
            v.symptoms,
            v.treatment,
            v.patient_id,
            v.doctor_id,
            v.branch_id,
            v.status as visit_status,
            pat.full_name as patient_name,
            pat.patient_id as patient_number,
            pat.phone as patient_phone,
            pat.email as patient_email,
            pat.date_of_birth as patient_dob,
            pat.gender as patient_gender,
            pat.address as patient_address,
            pat.blood_group as patient_blood,
            pat.allergies as patient_allergies,
            u_doctor.full_name as doctor_name,
            u_doctor.username as doctor_username,
            u_doctor.role as doctor_role,
            u_doctor.specialty as doctor_specialty,
            u_doctor.profile_pic as doctor_pic,
            b.name as branch_name,
            b.location as branch_location,
            b.phone as branch_phone,
            v.created_at
        FROM visits v
        LEFT JOIN patients pat ON v.patient_id = pat.id
        LEFT JOIN users u_doctor ON v.doctor_id = u_doctor.id
        LEFT JOIN branches b ON v.branch_id = b.id
        WHERE v.id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$visit_id]);
        $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Visit fetch error: " . $e->getMessage());
    }
}

if (!$prescription) {
    header('Location: inventory.php?branch=' . urlencode($selected_branch_id));
    exit;
}

// GET ALL PRESCRIPTIONS ZA VISIT
$visit_prescriptions = [];
try {
    $sql = "SELECT 
        p.id,
        p.prescription_number,
        p.doctor_id,
        p.status as rx_status,
        p.notes,
        p.created_at,
        u.full_name as doctor_name
    FROM prescriptions p
    LEFT JOIN users u ON p.doctor_id = u.id
    WHERE p.visit_id = ?
    ORDER BY p.id ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$visit_id]);
    $visit_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Visit prescriptions error: " . $e->getMessage());
}

if ($prescription_id <= 0 && !empty($visit_prescriptions)) {
    $prescription_id = (int)$visit_prescriptions[0]['id'];
}

// ================================================================
// GET ALL ITEMS ZA VISIT - WITH BILL STATUS
// ================================================================
$prescription_items = [];
try {
    $sql = "SELECT 
        pi.id,
        pi.prescription_id,
        pi.patient_id,
        pi.inventory_id as medication_id,
        pi.medication_name,
        pi.dosage,
        pi.frequency,
        pi.quantity,
        pi.duration,
        pi.route,
        pi.instructions,
        pi.unit_price,
        pi.total_price,
        pi.branch_id,
        pi.created_at,
        p.prescription_number,
        p.status as rx_status,
        p.visit_id,
        m.category as med_category,
        m.unit as med_unit,
        (SELECT bi.status FROM bill_items bi 
            INNER JOIN bills b ON bi.bill_id = b.id 
            WHERE b.visit_id = p.visit_id 
            AND bi.item_type = 'medication' 
            AND bi.item_name LIKE CONCAT('%', pi.medication_name, '%')
            LIMIT 1
        ) as bill_item_status,
        (SELECT bi.id FROM bill_items bi 
            INNER JOIN bills b ON bi.bill_id = b.id 
            WHERE b.visit_id = p.visit_id 
            AND bi.item_type = 'medication' 
            AND bi.item_name LIKE CONCAT('%', pi.medication_name, '%')
            LIMIT 1
        ) as bill_item_id,
        (SELECT b.status FROM bills b 
            WHERE b.visit_id = p.visit_id 
            LIMIT 1
        ) as bill_status
    FROM prescription_items pi
    INNER JOIN prescriptions p ON pi.prescription_id = p.id
    LEFT JOIN medications_inventory m ON pi.inventory_id = m.id
    WHERE p.visit_id = ?
    ORDER BY pi.id ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$visit_id]);
    $prescription_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Items fetch error: " . $e->getMessage());
}

// GET RELATED BILL
$related_bill = null;
try {
    $sql = "SELECT 
        b.*,
        u_cashier.full_name as cashier_name,
        u_cashier.role as cashier_role
    FROM bills b
    LEFT JOIN users u_cashier ON b.created_by = u_cashier.id
    WHERE b.visit_id = ? AND b.patient_id = ?
    ORDER BY b.created_at DESC
    LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$visit_id, $prescription['patient_id'] ?? 0]);
    $related_bill = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// STATS
$total_items = count($prescription_items);
$total_qty = 0;
$total_amount = 0;
$total_final = 0;
$total_paid_items = 0;
$total_pending_items = 0;

foreach ($prescription_items as $item) {
    $total_qty += (int)($item['quantity'] ?? 0);
    $item_total = (float)($item['total_price'] ?? (($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0)));
    $total_amount += $item_total;
    $total_final += $item_total;
    
    $item_status = strtolower($item['bill_item_status'] ?? $item['rx_status'] ?? 'pending');
    if ($item_status === 'paid' || $item_status === 'dispensed') $total_paid_items++;
    else $total_pending_items++;
}

function calculateAge($dob) {
    if (!$dob) return 'N/A';
    try {
        $birthDate = new DateTime($dob);
        $today = new DateTime();
        return $today->diff($birthDate)->y . ' yrs';
    } catch (Exception $e) { return 'N/A'; }
}

function getStatusClass($status) {
    $status = strtolower($status ?? 'pending');
    $map = [
        'paid' => 'success', 'completed' => 'success', 'confirmed' => 'success',
        'dispensed' => 'success', 'pending' => 'warning', 'partial' => 'info',
        'cancelled' => 'danger',
    ];
    return $map[$status] ?? 'secondary';
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once __DIR__ . '/../../../components/admin_audit_header.php';
include_once __DIR__ . '/../../../components/admin_audit_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Prescription - <?= htmlspecialchars($prescription['visit_number'] ?? 'N/A') ?></title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
:root {
    --font-primary: 'Inter', -apple-system, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #3B82F6;
    --primary-bg: #E8F0FE;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --success: #059669;
    --success-bg: #D1FAE5;
    --danger: #DC2626;
    --danger-bg: #FEE2E2;
    --warning: #D97706;
    --warning-bg: #FEF3C7;
    --purple: #7C3AED;
    --purple-bg: #EDE9FE;
    --cyan: #0891B2;
    --cyan-bg: #CFFAFE;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-xl: 0 20px 50px rgba(0,0,0,0.15);
}
[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary: #3B82F6;
    --primary-dark: #2563EB;
    --primary-light: #60A5FA;
    --primary-bg: #1E3A5F;
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3A2A1A;
    --purple-bg: #2D1B4E;
    --cyan-bg: #0E3A47;
}
* { font-family: var(--font-primary); -webkit-font-smoothing: antialiased; }
html, body { font-family: var(--font-primary); background: var(--bg-body); color: var(--text-primary); }
.money-number, .money-cell, .font-mono, .qty-number { font-family: var(--font-mono) !important; font-feature-settings: 'tnum'; letter-spacing: -0.02em; }

.alert { padding: 12px 18px; border-radius: 12px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 0.82rem; animation: slideDown 0.4s ease; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.alert.success { background: var(--success-bg); color: var(--success); border-left: 4px solid var(--success); }
.alert.error { background: var(--danger-bg); color: var(--danger); border-left: 4px solid var(--danger); }

.page-header { background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%); border-radius: 16px; padding: 20px 24px; margin-bottom: 18px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px; box-shadow: 0 6px 20px rgba(11, 94, 215, 0.25); position: relative; overflow: hidden; }
.page-header::before { content: ''; position: absolute; top: -50%; right: -20%; width: 350px; height: 350px; background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%); border-radius: 50%; pointer-events: none; }
.page-header .page-title { color: white; font-size: 1.4rem; font-weight: 800; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; position: relative; z-index: 1; letter-spacing: -0.02em; }
.page-header .page-title i { font-size: 1.5rem; color: #93C5FD; }
.page-header .page-subtitle { color: rgba(255,255,255,0.85); font-size: 0.78rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px; position: relative; z-index: 1; }
.branch-tag { background: rgba(255,255,255,0.15); color: white; padding: 3px 10px; border-radius: 16px; font-size: 0.65rem; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.1); }
.branch-tag.admin-tag { background: linear-gradient(135deg, #FCD34D, #F59E0B); color: #78350F; font-weight: 800; }
.branch-tag.audit-tag { background: linear-gradient(135deg, #0EA5E9, #0284C7); font-weight: 800; }
.branch-tag.count-tag { background: linear-gradient(135deg, #10B981, #059669); font-weight: 700; }

.btn-header { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 8px 14px; border-radius: 9px; font-weight: 600; font-size: 0.75rem; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; backdrop-filter: blur(4px); position: relative; z-index: 1; cursor: pointer; }
.btn-header:hover { background: rgba(255,255,255,0.28); transform: translateY(-2px); }

.status-banner { border-radius: 14px; padding: 16px 22px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; position: relative; overflow: hidden; box-shadow: var(--shadow-md); }
.status-banner.pending { background: linear-gradient(135deg, #D97706, #B45309); color: white; }
.status-banner.confirmed { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; }
.status-banner.dispensed { background: linear-gradient(135deg, #059669, #047857); color: white; }
.status-banner.paid { background: linear-gradient(135deg, #059669, #047857); color: white; }
.status-banner.cancelled { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; }
.status-banner .status-left { display: flex; align-items: center; gap: 16px; position: relative; z-index: 1; }
.status-banner .status-icon { width: 56px; height: 56px; border-radius: 14px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; border: 2px solid rgba(255,255,255,0.3); backdrop-filter: blur(4px); }
.status-banner .status-info .status-label { font-size: 1.35rem; font-weight: 900; letter-spacing: -0.02em; line-height: 1.1; }
.status-banner .status-info .status-sub { font-size: 0.75rem; opacity: 0.9; font-weight: 500; margin-top: 4px; }
.status-banner .status-amount { text-align: right; position: relative; z-index: 1; }
.status-banner .status-amount .label { font-size: 0.65rem; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; margin-bottom: 4px; }
.status-banner .status-amount .value { font-size: 1.75rem; font-weight: 900; font-family: var(--font-mono); letter-spacing: -0.03em; line-height: 1; }
.status-banner .status-amount .currency-prefix { font-size: 0.95rem; opacity: 0.9; font-family: var(--font-primary); font-weight: 700; }

.view-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px; }

.info-card { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm); transition: all 0.3s ease; }
.info-card:hover { box-shadow: var(--shadow-md); border-color: var(--primary); }
.info-card.blue-theme { border-color: var(--primary); }
.info-card.blue-theme .card-header { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border-bottom-color: transparent; }
.info-card.blue-theme .card-header i { color: #93C5FD; }
.info-card .card-header { padding: 14px 18px; background: var(--primary-bg); border-bottom: 2px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 0.85rem; font-weight: 800; color: var(--text-primary); }
.info-card .card-header .title { display: flex; align-items: center; gap: 8px; }
.info-card .card-header i { color: var(--primary); font-size: 0.95rem; }
.info-card .card-body { padding: 18px 20px; }

.info-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border-color); font-size: 0.82rem; }
.info-row:last-child { border-bottom: none; padding-bottom: 0; }
.info-row:first-child { padding-top: 0; }
.info-row .label { color: var(--text-secondary); font-weight: 600; font-size: 0.72rem; display: flex; align-items: center; gap: 6px; flex-shrink: 0; min-width: 100px; }
.info-row .label i { font-size: 0.7rem; opacity: 0.8; color: var(--primary); }
.info-row .value { color: var(--text-primary); font-weight: 700; text-align: right; word-break: break-word; flex: 1; }
.info-row .value.mono { font-family: var(--font-mono); font-size: 0.8rem; letter-spacing: -0.02em; }

.patient-profile { display: flex; align-items: center; gap: 14px; padding: 16px 18px; background: linear-gradient(135deg, var(--primary-bg), var(--primary-bg)); border-bottom: 2px solid var(--border-color); }
.patient-avatar { width: 56px; height: 56px; border-radius: 14px; background: linear-gradient(135deg, #0B5ED7, #3B82F6); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 900; text-transform: uppercase; flex-shrink: 0; box-shadow: 0 6px 16px rgba(11, 94, 215, 0.3); border: 3px solid var(--bg-card); }
.patient-name { font-size: 1.1rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.02em; line-height: 1.2; margin-bottom: 4px; }
.patient-meta { display: flex; flex-wrap: wrap; gap: 8px; }
.patient-meta-item { font-size: 0.68rem; font-weight: 600; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 4px; background: var(--bg-card); padding: 3px 10px; border-radius: 12px; border: 1px solid var(--border-color); }
.patient-meta-item i { color: var(--primary); font-size: 0.65rem; }

.items-table-card { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 18px; }
.items-table-card .card-header { padding: 14px 20px; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
.items-table-card .card-header .title { color: white; font-size: 0.9rem; font-weight: 800; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.items-table-card .card-header .title i { color: #93C5FD; font-size: 1rem; }
.items-table-card .card-header .count { color: rgba(255,255,255,0.9); font-size: 0.7rem; font-weight: 700; background: rgba(255,255,255,0.15); padding: 4px 12px; border-radius: 10px; backdrop-filter: blur(4px); }

.header-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.scroll-buttons { display: inline-flex; gap: 4px; background: rgba(255,255,255,0.15); border-radius: 10px; padding: 4px; border: 1px solid rgba(255,255,255,0.25); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.btn-scroll { width: 36px; height: 36px; border-radius: 8px; background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.15); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 800; transition: all 0.25s ease; }
.btn-scroll:hover { background: rgba(255,255,255,0.35); transform: scale(1.1); border-color: rgba(255,255,255,0.5); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
.btn-scroll:active { transform: scale(0.92); }

.items-table-wrapper { overflow-x: auto; scroll-behavior: smooth; position: relative; }
.items-table-wrapper::-webkit-scrollbar { height: 10px; }
.items-table-wrapper::-webkit-scrollbar-track { background: var(--border-color); border-radius: 10px; margin: 0 8px; }
.items-table-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(90deg, #0B5ED7, #3B82F6); border-radius: 10px; border: 2px solid var(--border-color); }

.items-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; min-width: 1400px; }
.items-table thead th { text-align: left; padding: 11px 16px; font-weight: 800; font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.06em; color: white; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); white-space: nowrap; }
.items-table tbody td { padding: 11px 16px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); vertical-align: middle; font-weight: 500; }
.items-table tbody tr { transition: background 0.2s ease; }
.items-table tbody tr:hover td { background: var(--primary-bg); }
[data-theme="dark"] .items-table tbody tr:hover td { background: #0A2E5C; }
.items-table tbody tr:last-child td { border-bottom: none; }
.items-table tbody tr.deleting { opacity: 0.4; background: var(--danger-bg) !important; text-decoration: line-through; }

.rx-badge { display: inline-flex; align-items: center; gap: 3px; padding: 3px 9px; border-radius: 7px; background: var(--primary-bg); color: var(--primary); font-family: var(--font-mono); font-size: 0.65rem; font-weight: 800; white-space: nowrap; border: 1px solid rgba(11, 94, 215, 0.2); }
[data-theme="dark"] .rx-badge { background: #1E3A5F; color: #93C5FD; border-color: rgba(96, 165, 250, 0.3); }

.status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 11px; border-radius: 8px; font-size: 0.62rem; font-weight: 800; text-transform: uppercase; white-space: nowrap; letter-spacing: 0.04em; }
.status-badge.success { background: var(--success-bg); color: var(--success); border: 1.5px solid var(--success); }
.status-badge.pending { background: var(--warning-bg); color: var(--warning); border: 1.5px solid var(--warning); }
.status-badge.danger { background: var(--danger-bg); color: var(--danger); border: 1.5px solid var(--danger); }
.status-badge.info { background: var(--cyan-bg); color: var(--cyan); border: 1.5px solid var(--cyan); }
.status-badge.secondary { background: var(--border-color); color: var(--text-secondary); }

.status-badge.paid-badge {
    background: linear-gradient(135deg, #059669, #047857);
    color: white;
    border: 1.5px solid #34D399;
    box-shadow: 0 2px 8px rgba(5, 150, 105, 0.4);
    font-weight: 900;
}
.status-badge.paid-badge i { color: #A7F3D0; }

.status-badge.pending-badge {
    background: linear-gradient(135deg, #D97706, #B45309);
    color: white;
    border: 1.5px solid #FCD34D;
    box-shadow: 0 2px 8px rgba(217, 119, 6, 0.4);
}
.status-badge.pending-badge i { color: #FDE68A; }

.money-cell { font-family: var(--font-mono); font-weight: 800; font-size: 0.82rem; text-align: right; color: var(--text-primary); letter-spacing: -0.02em; white-space: nowrap; }
.money-cell.blue { color: var(--primary); }
.money-cell.green { color: var(--success); }
.currency-prefix { font-size: 0.68rem; color: var(--text-secondary); margin-right: 2px; font-family: var(--font-primary); font-weight: 600; }

.btn-item-delete { display: inline-flex; align-items: center; justify-content: center; gap: 4px; padding: 6px 12px; border-radius: 8px; background: rgba(220, 38, 38, 0.1); color: var(--danger); border: 2px solid rgba(220, 38, 38, 0.2); font-weight: 800; font-size: 0.7rem; cursor: pointer; transition: all 0.25s ease; text-decoration: none; font-family: var(--font-primary); white-space: nowrap; flex-shrink: 0; }
.btn-item-delete i { font-size: 0.75rem; }
.btn-item-delete:hover { background: var(--danger); color: white; border-color: var(--danger); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(220, 38, 38, 0.4); }
.btn-item-delete:active { transform: translateY(0) scale(0.98); }

.scroll-hint { padding: 8px 16px; background: var(--primary-bg); border-top: 1px solid var(--border-color); text-align: center; font-size: 0.68rem; color: var(--text-secondary); font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap; }
.scroll-hint i { color: var(--primary); font-size: 0.75rem; }
.scroll-hint kbd { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 4px; padding: 1px 6px; font-family: var(--font-mono); font-size: 0.65rem; color: var(--primary); font-weight: 800; }

.totals-card { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 18px; }
.totals-card .card-header { padding: 12px 20px; background: linear-gradient(135deg, #059669, #047857); color: white; font-size: 0.85rem; font-weight: 800; display: flex; align-items: center; gap: 8px; }
.totals-card .card-header i { color: #A7F3D0; }
.totals-body { padding: 8px 0; }
.total-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 20px; font-size: 0.85rem; border-bottom: 1px solid var(--border-color); }
.total-row:last-child { border-bottom: none; }
.total-row .label { color: var(--text-secondary); font-weight: 600; display: flex; align-items: center; gap: 8px; }
.total-row .label i { color: var(--primary); font-size: 0.8rem; width: 16px; text-align: center; }
.total-row .value { font-family: var(--font-mono); font-weight: 800; font-size: 0.92rem; color: var(--text-primary); letter-spacing: -0.02em; }
.total-row .value.purple { color: var(--purple); }
.total-row .value.red { color: var(--danger); }
.total-row .value.green { color: var(--success); }
.total-row .value.blue { color: var(--primary); }
.total-row.grand { background: linear-gradient(135deg, var(--primary-bg), var(--primary-bg)); border-top: 3px solid var(--primary); border-bottom: 3px solid var(--primary); padding: 16px 20px; margin: 6px 0; }
.total-row.grand .label { color: var(--primary); font-size: 1rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; }
.total-row.grand .value { color: var(--primary); font-size: 1.35rem; font-weight: 900; }

.action-bar { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); padding: 16px 20px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; box-shadow: var(--shadow-sm); position: sticky; bottom: 20px; z-index: 10; }
.action-bar .action-info { display: flex; align-items: center; gap: 12px; }
.action-bar .action-info .info-icon { width: 42px; height: 42px; border-radius: 12px; background: var(--primary-bg); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
.action-bar .action-info .info-text .info-title { font-size: 0.85rem; font-weight: 800; color: var(--text-primary); }
.action-bar .action-info .info-text .info-sub { font-size: 0.7rem; color: var(--text-secondary); font-weight: 500; }
.action-bar .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
.btn { padding: 10px 20px; border-radius: 10px; font-weight: 700; font-size: 0.8rem; border: none; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 7px; text-decoration: none; white-space: nowrap; }
.btn:hover { transform: translateY(-2px); }
.btn-primary { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
.btn-primary:hover { box-shadow: 0 6px 20px rgba(11, 94, 215, 0.5); }
.btn-secondary { background: var(--bg-card); color: var(--text-primary); border: 2px solid var(--border-color); }
.btn-secondary:hover { border-color: var(--primary); color: var(--primary); }
.btn-edit { background: linear-gradient(135deg, #3B82F6, #2563EB); color: white; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35); }
.btn-edit:hover { box-shadow: 0 6px 20px rgba(59, 130, 246, 0.55); }
.btn-delete { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); }
.btn-delete:hover { box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5); }

.empty-state { padding: 40px 20px; text-align: center; color: var(--text-secondary); }
.empty-state i { font-size: 2.5rem; opacity: 0.3; display: block; margin-bottom: 10px; color: var(--primary); }
.empty-state p { font-weight: 600; font-size: 0.85rem; }

.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(6px); z-index: 99999; display: none; align-items: center; justify-content: center; padding: 20px; }
.modal-overlay.active { display: flex; }
.modal-box { background: var(--bg-card); border-radius: 20px; max-width: 480px; width: 100%; padding: 30px; box-shadow: var(--shadow-xl); animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); text-align: center; }
@keyframes modalPop { 0% { opacity: 0; transform: scale(0.8) translateY(20px); } 100% { opacity: 1; transform: scale(1) translateY(0); } }
.modal-icon { width: 68px; height: 68px; border-radius: 50%; background: var(--danger-bg); color: var(--danger); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 16px; animation: iconPulse 1.5s infinite; }
@keyframes iconPulse { 0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); } 50% { transform: scale(1.05); box-shadow: 0 0 0 12px rgba(220, 38, 38, 0); } }
.modal-title { font-size: 1.2rem; font-weight: 800; margin-bottom: 8px; color: var(--text-primary); }
.modal-text { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 22px; line-height: 1.7; }
.modal-text strong { color: var(--primary); background: var(--primary-bg); padding: 2px 8px; border-radius: 6px; font-family: var(--font-mono); font-weight: 700; }
.modal-info { background: var(--warning-bg); padding: 12px 16px; border-radius: 10px; margin-bottom: 18px; font-size: 0.75rem; color: var(--warning); font-weight: 600; display: flex; align-items: flex-start; gap: 8px; text-align: left; border-left: 3px solid var(--warning); transition: all 0.3s ease; }
.modal-info i { font-size: 0.9rem; flex-shrink: 0; margin-top: 2px; }
.modal-info strong { background: transparent; padding: 0; font-family: var(--font-mono); }
.modal-actions { display: flex; gap: 10px; justify-content: center; }
.modal-btn { padding: 11px 24px; border-radius: 11px; font-weight: 700; font-size: 0.85rem; border: none; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
.modal-btn.cancel { background: var(--border-color); color: var(--text-primary); }
.modal-btn.cancel:hover { background: #CBD5E1; transform: translateY(-2px); }
.modal-btn.danger { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; }
.modal-btn.danger:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5); }

@media (max-width: 1024px) { .view-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .status-banner { padding: 14px 16px; }
    .status-banner .status-amount .value { font-size: 1.35rem; }
    .status-banner .status-icon { width: 44px; height: 44px; font-size: 1.2rem; }
    .status-banner .status-info .status-label { font-size: 1.1rem; }
    .items-table { font-size: 0.72rem; }
    .items-table thead th, .items-table tbody td { padding: 8px 10px; }
    .action-bar { padding: 12px 14px; flex-direction: column; align-items: stretch; }
    .action-bar .action-buttons { justify-content: center; }
    .btn { padding: 8px 14px; font-size: 0.75rem; }
    .btn-item-delete { padding: 5px 10px; font-size: 0.65rem; }
    .btn-item-delete span { display: none; }
    .btn-item-delete i { font-size: 0.85rem; }
    .btn-scroll { width: 32px; height: 32px; font-size: 0.8rem; }
}
@media print {
    .action-bar, .btn-header, .btn-edit, .btn-delete, .btn-item-delete, .scroll-buttons, .scroll-hint { display: none !important; }
    .page-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; }
    .status-banner { -webkit-print-color-adjust: exact; }
}
    </style>
</head>
<body>

<main class="main-content">

    <?php if (!empty($alert_message)): ?>
        <div class="alert <?= $alert_type ?>" id="alertBox">
            <i class="fas fa-<?= $alert_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <span><?= htmlspecialchars($alert_message) ?></span>
        </div>
        <script>
            setTimeout(function() {
                var el = document.getElementById('alertBox');
                if (el) { 
                    el.style.transition = 'all 0.5s ease'; 
                    el.style.opacity = '0'; 
                    setTimeout(function() { el.remove(); }, 500); 
                }
            }, <?= $reload_page ? '2000' : '4000' ?>);
            
            <?php if ($reload_page): ?>
            setTimeout(function() {
                window.location.href = window.location.href;
            }, 2000);
            <?php endif; ?>
        </script>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription"></i>
                Prescription Details
                <?php if ($is_admin): ?>
                    <span class="branch-tag admin-tag"><i class="fas fa-crown"></i> ADMIN</span>
                <?php else: ?>
                    <span class="branch-tag audit-tag"><i class="fas fa-shield-alt"></i> AUDIT</span>
                <?php endif; ?>
                <span class="branch-tag count-tag">
                    <i class="fas fa-pills"></i> <?= number_format($total_items) ?> Items
                </span>
                <span class="branch-tag" style="background:linear-gradient(135deg,#7C3AED,#6D28D9);">
                    <i class="fas fa-prescription"></i> <?= count($visit_prescriptions) ?> Rx
                </span>
                <?php if ($total_paid_items > 0): ?>
                <span class="branch-tag" style="background:linear-gradient(135deg,#059669,#047857);">
                    <i class="fas fa-check-circle"></i> <?= $total_paid_items ?> Paid
                </span>
                <?php endif; ?>
                <?php if ($total_pending_items > 0): ?>
                <span class="branch-tag" style="background:linear-gradient(135deg,#D97706,#B45309);">
                    <i class="fas fa-clock"></i> <?= $total_pending_items ?> Pending
                </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-clipboard-check"></i>
                <strong><?= htmlspecialchars($prescription['visit_number'] ?? 'N/A') ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($prescription['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-user-injured"></i> <?= htmlspecialchars($prescription['patient_name'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($prescription['visit_date'] ?? 'now')) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- STATUS BANNER -->
    <?php 
    $status = strtolower($prescription['visit_status'] ?? 'confirmed');
    if (empty($status) || $status === 'waiting') $status = 'confirmed';
    
    if ($related_bill && strtolower($related_bill['status'] ?? '') === 'paid') {
        $status = 'paid';
    }
    
    $status_icon = 'fa-clock';
    $status_label = 'PENDING';
    
    switch ($status) {
        case 'dispensed': $status_icon = 'fa-check-circle'; $status_label = 'DISPENSED'; break;
        case 'confirmed': $status_icon = 'fa-check'; $status_label = 'CONFIRMED'; break;
        case 'paid': $status_icon = 'fa-check-double'; $status_label = 'PAID'; break;
        case 'cancelled': $status_icon = 'fa-times-circle'; $status_label = 'CANCELLED'; break;
        default: $status_icon = 'fa-clock'; $status_label = 'PENDING';
    }
    ?>
    <div class="status-banner <?= htmlspecialchars($status) ?>">
        <div class="status-left">
            <div class="status-icon">
                <i class="fas <?= $status_icon ?>"></i>
            </div>
            <div class="status-info">
                <div class="status-label"><?= $status_label ?></div>
                <div class="status-sub">
                    <i class="fas fa-pills"></i> <?= number_format($total_items) ?> medication(s) • 
                    Qty: <?= number_format($total_qty) ?>
                    <?php if ($total_paid_items > 0): ?>
                        • <i class="fas fa-check-circle"></i> <?= $total_paid_items ?> paid
                    <?php endif; ?>
                    <?php if ($total_pending_items > 0): ?>
                        • <i class="fas fa-clock"></i> <?= $total_pending_items ?> pending
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="status-amount">
            <div class="label">Total Amount</div>
            <div class="value">
                <span class="currency-prefix"><?= $currency ?></span>
                <?= number_format($total_final, 0) ?>
            </div>
        </div>
    </div>

    <!-- PATIENT + VISIT INFO GRID -->
    <div class="view-grid">
        
        <div class="info-card blue-theme">
            <div class="card-header">
                <span class="title"><i class="fas fa-user-injured"></i> Patient Information</span>
            </div>
            
            <?php if (!empty($prescription['patient_name'])): ?>
            <div class="patient-profile">
                <div class="patient-avatar">
                    <?= strtoupper(substr($prescription['patient_name'] ?? 'P', 0, 1)) ?>
                </div>
                <div style="flex:1;">
                    <div class="patient-name"><?= htmlspecialchars($prescription['patient_name']) ?></div>
                    <div class="patient-meta">
                        <?php if (!empty($prescription['patient_number'])): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-id-card"></i> <?= htmlspecialchars($prescription['patient_number']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($prescription['patient_gender'])): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-<?= strtolower($prescription['patient_gender']) === 'female' ? 'venus' : 'mars' ?>"></i> 
                                <?= htmlspecialchars($prescription['patient_gender']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($prescription['patient_dob'])): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-birthday-cake"></i> <?= calculateAge($prescription['patient_dob']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="value mono"><?= htmlspecialchars($prescription['patient_phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-tint"></i> Blood Group</span>
                    <span class="value mono" style="color:var(--danger);"><?= htmlspecialchars($prescription['patient_blood'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-exclamation-triangle"></i> Allergies</span>
                    <span class="value" style="color:var(--danger);"><?= htmlspecialchars($prescription['patient_allergies'] ?? 'None') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-map-marker-alt"></i> Address</span>
                    <span class="value"><?= htmlspecialchars($prescription['patient_address'] ?? 'N/A') ?></span>
                </div>
            </div>
        </div>

        <div class="info-card blue-theme">
            <div class="card-header">
                <span class="title"><i class="fas fa-info-circle"></i> Visit Information</span>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-clipboard-check"></i> Visit #</span>
                    <span class="value mono" style="color:var(--primary);"><?= htmlspecialchars($prescription['visit_number'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-calendar-plus"></i> Date</span>
                    <span class="value"><?= date('d M Y, H:i', strtotime($prescription['visit_date'] ?? 'now')) ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-user-md"></i> Doctor</span>
                    <span class="value">
                        <?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?>
                        <?php if (!empty($prescription['doctor_specialty'])): ?>
                            <span style="font-size:0.6rem;background:var(--primary-bg);color:var(--primary);padding:1px 6px;border-radius:4px;font-weight:800;text-transform:uppercase;margin-left:4px;">
                                <?= htmlspecialchars($prescription['doctor_specialty']) ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if (!empty($prescription['branch_name'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-store-alt"></i> Branch</span>
                    <span class="value"><?= htmlspecialchars($prescription['branch_name']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($prescription['diagnosis'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-stethoscope"></i> Diagnosis</span>
                    <span class="value"><?= htmlspecialchars($prescription['diagnosis']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- PRESCRIPTION ITEMS TABLE -->
    <div class="items-table-card">
        <div class="card-header">
            <span class="title">
                <i class="fas fa-pills"></i>
                All Prescribed Medications
                <span style="font-size:0.65rem;font-weight:600;background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:8px;margin-left:6px;">
                    Visit: <?= htmlspecialchars($prescription['visit_number'] ?? 'N/A') ?>
                </span>
                <span style="font-size:0.65rem;font-weight:600;background:rgba(16,185,129,0.3);padding:2px 10px;border-radius:8px;">
                    <?= count($prescription_items) ?> items
                </span>
            </span>
            
            <div class="header-actions">
                <div class="scroll-buttons" title="Scroll left / right">
                    <button type="button" class="btn-scroll" onclick="scrollItems('left')" title="Scroll Left (Alt+←)">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="btn-scroll" onclick="scrollItems('right')" title="Scroll Right (Alt+→)">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <span class="count"><?= count($visit_prescriptions) ?> Rx</span>
            </div>
        </div>
        
        <?php if (count($prescription_items) > 0): ?>
        <div class="items-table-wrapper" id="itemsWrapper">
            <table class="items-table" id="itemsTable">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th style="width:150px;">Rx #</th>
                        <th>Medication</th>
                        <th>Category</th>
                        <th style="text-align:center;">Qty</th>
                        <th>Dosage</th>
                        <th>Frequency</th>
                        <th>Route</th>
                        <th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:center;">Status</th>
                        <?php if ($is_admin): ?>
                        <th style="text-align:center;width:100px;">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $item_num = 1; foreach ($prescription_items as $item): 
                        $item_status = strtolower($item['bill_item_status'] ?? $item['rx_status'] ?? 'pending');
                        
                        $item_status_class = 'pending';
                        $item_status_icon = 'fa-clock';
                        $item_status_text = 'PENDING';
                        
                        if ($item_status === 'paid') {
                            $item_status_class = 'paid-badge';
                            $item_status_icon = 'fa-check-circle';
                            $item_status_text = 'PAID';
                        } elseif ($item_status === 'dispensed') {
                            $item_status_class = 'success';
                            $item_status_icon = 'fa-check-double';
                            $item_status_text = 'DISPENSED';
                        } elseif ($item_status === 'confirmed') {
                            $item_status_class = 'info';
                            $item_status_icon = 'fa-check';
                            $item_status_text = 'CONFIRMED';
                        } elseif ($item_status === 'cancelled') {
                            $item_status_class = 'danger';
                            $item_status_icon = 'fa-times-circle';
                            $item_status_text = 'CANCELLED';
                        } else {
                            $item_status_class = 'pending-badge';
                            $item_status_icon = 'fa-clock';
                            $item_status_text = 'PENDING';
                        }
                        
                        $item_qty = (int)($item['quantity'] ?? 0);
                        $item_price = (float)($item['unit_price'] ?? 0);
                        $item_total = (float)($item['total_price'] ?? ($item_price * $item_qty));
                        
                        $pi_id = (int)($item['id'] ?? 0);
                        $item_med_name = $item['medication_name'] ?? 'N/A';
                        $item_rx_number = $item['prescription_number'] ?? 'N/A';
                        $item_rx_id = (int)($item['prescription_id'] ?? 0);
                        
                        $will_return_stock = ($item_status !== 'paid' && $item_status !== 'dispensed');
                        $will_reduce_bill = ($item_status === 'paid' || $item_status === 'dispensed');
                    ?>
                        <tr id="item-row-<?= $pi_id ?>">
                            <td style="text-align:center;font-weight:700;color:var(--text-secondary);font-family:var(--font-mono);"><?= $item_num++ ?></td>
                            <td>
                                <span class="rx-badge">
                                    <i class="fas fa-prescription"></i>
                                    <?= htmlspecialchars($item_rx_number) ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight:700;color:var(--primary);display:flex;align-items:center;gap:6px;">
                                    <i class="fas fa-pills"></i>
                                    <?= htmlspecialchars($item_med_name) ?>
                                </div>
                            </td>
                            <td style="font-size:0.75rem;color:var(--text-secondary);">
                                <?= htmlspecialchars($item['med_category'] ?? '—') ?>
                            </td>
                            <td style="text-align:center;">
                                <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:8px;background:linear-gradient(135deg,#0B5ED7,#3B82F6);color:white;font-weight:800;font-size:0.72rem;font-family:var(--font-mono);">
                                    <i class="fas fa-cube" style="font-size:0.65rem;"></i>
                                    <?= number_format($item_qty) ?>
                                </span>
                            </td>
                            <td style="font-size:0.75rem;"><?= htmlspecialchars($item['dosage'] ?? '—') ?></td>
                            <td style="font-size:0.75rem;"><?= htmlspecialchars($item['frequency'] ?? '—') ?></td>
                            <td style="font-size:0.75rem;"><?= htmlspecialchars($item['route'] ?? '—') ?></td>
                            <td class="money-cell">
                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($item_price, 0) ?>
                            </td>
                            <td class="money-cell blue">
                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($item_total, 0) ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="status-badge <?= $item_status_class ?>">
                                    <i class="fas <?= $item_status_icon ?>"></i>
                                    <?= $item_status_text ?>
                                </span>
                            </td>
                            
                            <?php if ($is_admin): ?>
                            <td style="text-align:center;">
                                <button type="button" 
                                        class="btn-item-delete" 
                                        title="Delete this medication"
                                        onclick="confirmDeleteItem(
                                            <?= $pi_id ?>, 
                                            '<?= htmlspecialchars(addslashes($item_med_name)) ?>', 
                                            <?= $item_rx_id ?>, 
                                            <?= $related_bill ? (int)$related_bill['id'] : 0 ?>,
                                            '<?= htmlspecialchars($item_status) ?>',
                                            <?= $item_qty ?>,
                                            <?= $will_return_stock ? 'true' : 'false' ?>,
                                            <?= $will_reduce_bill ? 'true' : 'false' ?>
                                        )">
                                    <i class="fas fa-trash"></i>
                                    <span>Delete</span>
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="scroll-hint">
            <i class="fas fa-arrows-alt-h"></i>
            Use <kbd>‹</kbd> <kbd>›</kbd> buttons to scroll left/right 
            <span style="opacity:0.5;">•</span>
            Keyboard: <kbd>Alt</kbd> + <kbd>←</kbd> / <kbd>→</kbd>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No medications found for this visit</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- TOTALS -->
    <div class="totals-card">
        <div class="card-header">
            <i class="fas fa-calculator"></i> Prescription Summary
        </div>
        <div class="totals-body">
            <div class="total-row">
                <span class="label"><i class="fas fa-cubes"></i> Total Items</span>
                <span class="value blue"><?= number_format($total_items) ?></span>
            </div>
            
            <div class="total-row">
                <span class="label"><i class="fas fa-sort-numeric-up"></i> Total Quantity</span>
                <span class="value blue"><?= number_format($total_qty) ?></span>
            </div>
            
            <div class="total-row">
                <span class="label"><i class="fas fa-prescription"></i> Prescriptions</span>
                <span class="value purple"><?= number_format(count($visit_prescriptions)) ?></span>
            </div>
            
            <div class="total-row">
                <span class="label"><i class="fas fa-check-circle"></i> Paid Items</span>
                <span class="value green"><?= number_format($total_paid_items) ?></span>
            </div>
            
            <?php if ($total_pending_items > 0): ?>
            <div class="total-row">
                <span class="label"><i class="fas fa-clock"></i> Pending Items</span>
                <span class="value" style="color:var(--warning);"><?= number_format($total_pending_items) ?></span>
            </div>
            <?php endif; ?>
            
            <div class="total-row grand">
                <span class="label"><i class="fas fa-money-bill-wave"></i> Total Amount</span>
                <span class="value"><span class="currency-prefix"><?= $currency ?></span><?= number_format($total_final, 0) ?></span>
            </div>
            
            <?php if ($related_bill): ?>
            <div class="total-row">
                <span class="label"><i class="fas fa-file-invoice"></i> Related Bill</span>
                <span class="value mono" style="font-size:0.75rem;"><?= htmlspecialchars($related_bill['bill_number'] ?? 'N/A') ?></span>
            </div>
            <div class="total-row">
                <span class="label"><i class="fas fa-flag"></i> Bill Status</span>
                <span class="value">
                    <span class="status-badge <?= getStatusClass($related_bill['status'] ?? 'pending') ?>">
                        <?= strtoupper($related_bill['status'] ?? 'N/A') ?>
                    </span>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ACTION BAR -->
    <div class="action-bar">
        <div class="action-info">
            <div class="info-icon">
                <i class="fas fa-prescription"></i>
            </div>
            <div class="info-text">
                <div class="info-title">Prescription Actions</div>
                <div class="info-sub">
                    <?php if ($is_admin): ?>
                        Edit, delete items, or delete whole prescription
                    <?php else: ?>
                        View-only mode (Audit Role)
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="action-buttons">
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print
            </button>
            
            <?php if ($is_admin): ?>
                <a href="edit_prescription.php?id=<?= $prescription_id ?>&visit_id=<?= $visit_id ?>&patient_id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" 
                   class="btn btn-edit">
                    <i class="fas fa-edit"></i> Edit
                </a>
                
                <button type="button" class="btn btn-delete" 
                        onclick="confirmDeletePrescription(
                            <?= $prescription_id ?>, 
                            <?= $visit_id ?>, 
                            <?= $patient_id ?>,
                            '<?= htmlspecialchars(addslashes($prescription['visit_number'] ?? 'N/A')) ?>'
                        )">
                    <i class="fas fa-trash"></i> Delete All Rx
                </button>
            <?php endif; ?>
            
            <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

</main>

<?php if ($is_admin): ?>
<!-- DELETE ITEM MODAL -->
<div class="modal-overlay" id="deleteItemModal">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-pills"></i>
        </div>
        <h3 class="modal-title">Delete Medication?</h3>
        <p class="modal-text">
            Are you sure you want to delete this medication?<br>
            <strong id="deleteItemName">-</strong>
        </p>
        
        <div class="modal-info" id="deleteItemInfoBox">
            <i class="fas fa-info-circle"></i>
            <span id="deleteItemInfoText">
                This will remove the medication from this prescription.
            </span>
        </div>
        
        <form method="POST" id="deleteItemForm">
            <input type="hidden" name="action" value="delete_prescription_item">
            <input type="hidden" name="item_id" id="deleteItemId" value="">
            <input type="hidden" name="prescription_id" id="deleteItemPrescId" value="">
            <input type="hidden" name="bill_id" id="deleteItemBillId" value="">
            <input type="hidden" name="item_name" id="deleteItemNameHidden" value="">
            <div class="modal-actions">
                <button type="button" class="modal-btn cancel" onclick="closeDeleteItemModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="modal-btn danger">
                    <i class="fas fa-trash"></i> Yes, Delete
                </button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE WHOLE PRESCRIPTION MODAL -->
<div class="modal-overlay" id="deletePrescriptionModal">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="modal-title">Delete ALL Prescriptions?</h3>
        <p class="modal-text">
            Are you sure you want to delete ALL prescriptions for this visit?<br>
            <strong id="deletePrescNumber">-</strong>
        </p>
        
        <div class="modal-info">
            <i class="fas fa-info-circle"></i>
            <span>
                This will permanently delete <strong>ALL prescriptions (<?= count($visit_prescriptions) ?>) and their items (<?= count($prescription_items) ?>)</strong> for this visit.<br><br>
                
                <strong>📦 Stock Logic:</strong><br>
                • PAID/DISPENSED → Stock <strong style="color:#DC2626;">NOT RETURNED</strong><br>
                • PENDING/CONFIRMED → Stock <strong style="color:#059669;">RETURNED</strong><br><br>
                
                <strong>💰 Bill Logic (Revenue):</strong><br>
                • If bill is <strong>PAID</strong> → Bill <strong style="color:#DC2626;">REDUCED</strong><br>
                • If bill is <strong>PENDING/PARTIAL</strong> → Bill <strong style="color:#D97706;">NOT REDUCED</strong>
            </span>
        </div>
        
        <form method="POST" id="deletePrescriptionForm">
            <input type="hidden" name="action" value="delete_prescription">
            <input type="hidden" name="prescription_id" id="deletePrescId" value="">
            <input type="hidden" name="visit_id" id="deleteVisitId" value="">
            <input type="hidden" name="patient_id" id="deletePatientId" value="">
            <div class="modal-actions">
                <button type="button" class="modal-btn cancel" onclick="closeDeletePrescriptionModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="modal-btn danger">
                    <i class="fas fa-trash"></i> Yes, Delete All
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// ✅ SCROLL ITEMS
function scrollItems(direction) {
    var wrapper = document.getElementById('itemsWrapper');
    if (!wrapper) return;
    if (wrapper.scrollWidth <= wrapper.clientWidth) return;
    
    var scrollAmount = 400;
    var currentScroll = wrapper.scrollLeft;
    var maxScroll = wrapper.scrollWidth - wrapper.clientWidth;
    
    var targetScroll = direction === 'left' 
        ? Math.max(0, currentScroll - scrollAmount)
        : Math.min(maxScroll, currentScroll + scrollAmount);
    
    wrapper.scrollTo({ left: targetScroll, behavior: 'smooth' });
    
    var btns = document.querySelectorAll('.btn-scroll');
    btns.forEach(function(btn) {
        btn.style.transform = 'scale(0.9)';
        setTimeout(function() { btn.style.transform = ''; }, 150);
    });
}

// ✅ KEYBOARD SHORTCUTS
document.addEventListener('keydown', function(e) {
    if (e.altKey && e.key === 'ArrowLeft') { e.preventDefault(); scrollItems('left'); }
    if (e.altKey && e.key === 'ArrowRight') { e.preventDefault(); scrollItems('right'); }
});

// ✅ confirmDeleteItem - ENGLISH ONLY
function confirmDeleteItem(itemId, itemName, prescId, billId, itemStatus, itemQty, willReturnStock, willReduceBill) {
    document.getElementById('deleteItemId').value = itemId;
    document.getElementById('deleteItemName').textContent = itemName;
    document.getElementById('deleteItemNameHidden').value = itemName;
    document.getElementById('deleteItemPrescId').value = prescId;
    document.getElementById('deleteItemBillId').value = billId;
    
    var infoText = document.getElementById('deleteItemInfoText');
    var infoBox = document.getElementById('deleteItemInfoBox');
    
    var statusUpper = (itemStatus || 'pending').toUpperCase();
    
    if (infoText) {
        var html = '<strong>Status:</strong> ' + statusUpper + '<br>';
        
        // Stock info
        if (willReturnStock) {
            html += '<br>📦 <strong>Stock:</strong> <span style="color:#059669;">✅ WILL BE RETURNED (+' + itemQty + ' units)</span>';
        } else {
            html += '<br>📦 <strong>Stock:</strong> <span style="color:#DC2626;">❌ WILL NOT BE RETURNED (paid/dispensed)</span>';
        }
        
        // Bill info
        if (willReduceBill) {
            html += '<br>💰 <strong>Bill:</strong> <span style="color:#DC2626;">✅ WILL BE REDUCED (was in revenue)</span>';
        } else {
            html += '<br>💰 <strong>Bill:</strong> <span style="color:#D97706;">⏸️ WILL NOT BE REDUCED (not yet counted)</span>';
        }
        
        infoText.innerHTML = html;
        
        if (willReturnStock) {
            infoBox.style.background = 'var(--success-bg)';
            infoBox.style.color = 'var(--success)';
            infoBox.style.borderLeftColor = 'var(--success)';
        } else {
            infoBox.style.background = 'var(--warning-bg)';
            infoBox.style.color = 'var(--warning)';
            infoBox.style.borderLeftColor = 'var(--warning)';
        }
    }
    
    var row = document.getElementById('item-row-' + itemId);
    if (row) row.classList.add('deleting');
    
    document.getElementById('deleteItemModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeleteItemModal() {
    document.getElementById('deleteItemModal').classList.remove('active');
    document.body.style.overflow = '';
    document.querySelectorAll('.items-table tbody tr.deleting').forEach(function(row) {
        row.classList.remove('deleting');
    });
}

function confirmDeletePrescription(prescId, visitId, patientId, prescNumber) {
    document.getElementById('deletePrescId').value = prescId;
    document.getElementById('deleteVisitId').value = visitId;
    document.getElementById('deletePatientId').value = patientId;
    document.getElementById('deletePrescNumber').textContent = prescNumber;
    document.getElementById('deletePrescriptionModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeletePrescriptionModal() {
    document.getElementById('deletePrescriptionModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.querySelectorAll('.modal-overlay').forEach(function(modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
            document.body.style.overflow = '';
            document.querySelectorAll('.items-table tbody tr.deleting').forEach(function(row) {
                row.classList.remove('deleting');
            });
        }
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(function(m) {
            m.classList.remove('active');
        });
        document.body.style.overflow = '';
        document.querySelectorAll('.items-table tbody tr.deleting').forEach(function(row) {
            row.classList.remove('deleting');
        });
    }
});

console.log('%c💊 View Prescription V7 FINAL - ENGLISH ONLY', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Visit: <?= htmlspecialchars($prescription['visit_number'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
console.log('%c✅ Items: <?= count($prescription_items) ?> (<?= $total_paid_items ?> PAID, <?= $total_pending_items ?> PENDING)', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ Role: <?= strtoupper($user_role) ?>', 'font-size:13px; color:#FCD34D; font-weight:bold;');
console.log('%c📦 STOCK LOGIC:', 'font-size:13px; color:#FCD34D; font-weight:bold;');
console.log('%c   PAID/DISPENSED → Stock NOT RETURNED', 'font-size:12px; color:#DC2626;');
console.log('%c   PENDING/CONFIRMED → Stock RETURNED', 'font-size:12px; color:#059669;');
console.log('%c💰 BILL LOGIC (Revenue):', 'font-size:13px; color:#FCD34D; font-weight:bold;');
console.log('%c   PAID/DISPENSED → Bill REDUCED', 'font-size:12px; color:#DC2626;');
console.log('%c   PENDING/CONFIRMED → Bill NOT REDUCED', 'font-size:12px; color:#059669;');
</script>

</body>
</html>