<?php
// ================================================================
// FILE: frontend/pages/admin/audit/patient_lab_tests.php
// ADMIN AUDIT - ALL LAB TESTS FOR A PATIENT (Grouped by Visit)
// ✅ Branch-filtered catalog (Dodoma → Dodoma tests)
// ✅ All lab technicians can serve (no assignment needed)
// ✅ No lab tech column
// ✅ Edit All button per visit
// ✅ Add Test with catalog search/scroll toggle
// ✅ Bill auto-update
// ✅ BLUE THEME
// ✅ ALL ENGLISH INSTRUCTIONS
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$patient_id = (int)($_GET['patient_id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($patient_id <= 0) {
    header('Location: lab_tests.php?branch=' . $selected_branch_id);
    exit;
}

require_once __DIR__ . '/../../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// CURRENCY
$currency = 'TSh';
try {
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['setting_value'])) $currency = $row['setting_value'];
} catch (Exception $e) {}

// ================================================================
// HELPER: Parse money
// ================================================================
function parseMoney($value) {
    if (is_numeric($value)) return (float)$value;
    $clean = preg_replace('/[^0-9.]/', '', (string)$value);
    return (float)($clean ?: 0);
}

// ================================================================
// HANDLE ACTIONS
// ================================================================
$alert_message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ============================================================
    // 1. ADD NEW TEST
    // ============================================================
    if ($_POST['action'] === 'add_test') {
        try {
            $db->beginTransaction();
            
            $visit_id = (int)($_POST['visit_id'] ?? 0);
            $test_name = trim($_POST['test_name'] ?? '');
            $test_code = trim($_POST['test_code'] ?? '');
            $test_type = trim($_POST['test_type'] ?? '');
            $sample_type = trim($_POST['sample_type'] ?? '');
            $test_date = $_POST['test_date'] ?? date('Y-m-d');
            $test_price = parseMoney($_POST['test_price'] ?? '0');
            $status = $_POST['status'] ?? 'pending';
            $reference_range = trim($_POST['reference_range'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
            if (empty($test_name) || $visit_id <= 0) {
                throw new Exception("Test name and visit are required!");
            }
            
            // Get visit info
            $stmt = $db->prepare("SELECT doctor_id, branch_id FROM visits WHERE id = ?");
            $stmt->execute([$visit_id]);
            $visit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$visit) {
                throw new Exception("Visit not found!");
            }
            
            $branch_id = $visit['branch_id'] ?? ($selected_branch_id !== 'all' ? (int)$selected_branch_id : 1);
            
            // Insert lab test (no lab_technician_id)
            $sql = "INSERT INTO lab_tests 
                (patient_id, visit_id, doctor_id, branch_id, test_name, test_code, test_type, 
                 sample_type, test_date, test_price, status, 
                 reference_range, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $db->prepare($sql)->execute([
                $patient_id, $visit_id, $visit['doctor_id'], $branch_id,
                $test_name, $test_code, $test_type, $sample_type,
                $test_date, $test_price, $status, $reference_range, $notes
            ]);
            
            $new_test_id = $db->lastInsertId();
            
            // Auto-add to bill
            $stmt = $db->prepare("SELECT id, bill_number FROM bills WHERE visit_id = ? AND status != 'cancelled' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$visit_id]);
            $existing_bill = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_bill) {
                $bill_id = $existing_bill['id'];
                
                $sql_item = "INSERT INTO bill_items 
                    (bill_id, patient_id, visit_id, branch_id, item_type, item_name, 
                     quantity, unit_price, total_price, discount_amount, final_price, 
                     status, reference_id, reference_type, created_at) 
                    VALUES (?, ?, ?, ?, 'lab_test', ?, 1, ?, ?, 0, ?, 'pending', ?, 'lab_test', NOW())";
                $db->prepare($sql_item)->execute([
                    $bill_id, $patient_id, $visit_id, $branch_id,
                    $test_name, $test_price, $test_price, $test_price, $new_test_id
                ]);
                
                recalculateBill($db, $bill_id);
            }
            
            // Audit log
            try {
                $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) 
                              VALUES (?, ?, 'add_lab_test', ?, ?, NOW())")
                   ->execute([
                       $user_id, $branch_id,
                       "Added lab test: $test_name (ID: $new_test_id) to visit #$visit_id",
                       $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                   ]);
            } catch (Exception $e) {}
            
            $db->commit();
            
            $alert_message = "Lab test \"$test_name\" added successfully!";
            $alert_type = 'success';
            
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $alert_message = "Error adding test: " . $e->getMessage();
            $alert_type = 'error';
        }
    }
    
    // ============================================================
    // 2. UPDATE EXISTING TEST
    // ============================================================
    if ($_POST['action'] === 'update_test') {
        try {
            $db->beginTransaction();
            
            $test_id = (int)($_POST['test_id'] ?? 0);
            $test_name = trim($_POST['test_name'] ?? '');
            $test_type = trim($_POST['test_type'] ?? '');
            $sample_type = trim($_POST['sample_type'] ?? '');
            $test_date = $_POST['test_date'] ?? date('Y-m-d');
            $test_price = parseMoney($_POST['test_price'] ?? '0');
            $status = $_POST['status'] ?? 'pending';
            $results = trim($_POST['results'] ?? '');
            $reference_range = trim($_POST['reference_range'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
            $stmt = $db->prepare("SELECT test_name, test_price, status, visit_id FROM lab_tests WHERE id = ?");
            $stmt->execute([$test_id]);
            $old_test = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$old_test) {
                throw new Exception("Test not found!");
            }
            
            $sql = "UPDATE lab_tests SET 
                test_name = ?, test_type = ?, sample_type = ?,
                test_date = ?, test_price = ?,
                status = ?, results = ?, reference_range = ?, notes = ?,
                updated_at = NOW()
                WHERE id = ?";
            $db->prepare($sql)->execute([
                $test_name, $test_type, $sample_type,
                $test_date, $test_price,
                $status, $results, $reference_range, $notes,
                $test_id
            ]);
            
            // Update bill item
            $stmt = $db->prepare("
                SELECT bi.* FROM bill_items bi
                WHERE bi.item_type = 'lab_test'
                AND (
                    (bi.reference_id = ? AND bi.reference_type = 'lab_test')
                    OR (bi.item_name = ? AND bi.visit_id = ?)
                )
                ORDER BY bi.id DESC LIMIT 1
            ");
            $stmt->execute([$test_id, $old_test['test_name'], $old_test['visit_id']]);
            $bill_item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($bill_item) {
                $bill_id = $bill_item['bill_id'];
                $old_discount = (float)($bill_item['discount_amount'] ?? 0);
                $new_final = $test_price - $old_discount;
                
                $sql_update = "UPDATE bill_items SET 
                    item_name = ?, unit_price = ?, total_price = ?,
                    final_price = ?, updated_at = NOW()
                    WHERE id = ?";
                $db->prepare($sql_update)->execute([
                    $test_name, $test_price, $test_price,
                    $new_final, $bill_item['id']
                ]);
                
                recalculateBill($db, $bill_id);
            }
            
            try {
                $changes = [];
                if ($old_test['test_name'] !== $test_name) $changes[] = "Name: {$old_test['test_name']} → $test_name";
                if ((float)$old_test['test_price'] != $test_price) $changes[] = "Price: " . number_format($old_test['test_price'], 0) . " → " . number_format($test_price, 0);
                if ($old_test['status'] !== $status) $changes[] = "Status: {$old_test['status']} → $status";
                
                $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) 
                              VALUES (?, ?, 'update_lab_test', ?, ?, NOW())")
                   ->execute([
                       $user_id,
                       $selected_branch_id !== 'all' ? (int)$selected_branch_id : 1,
                       "Updated lab test: $test_name (ID: $test_id) | " . implode(' | ', $changes),
                       $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                   ]);
            } catch (Exception $e) {}
            
            $db->commit();
            
            $alert_message = "Lab test \"$test_name\" updated successfully!";
            $alert_type = 'success';
            
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $alert_message = "Error updating test: " . $e->getMessage();
            $alert_type = 'error';
        }
    }
    
    // ============================================================
    // 3. DELETE TEST
    // ============================================================
    if ($_POST['action'] === 'delete_test') {
        try {
            $db->beginTransaction();
            
            $test_id = (int)($_POST['test_id'] ?? 0);
            
            $stmt = $db->prepare("SELECT test_name, test_price, status, visit_id FROM lab_tests WHERE id = ?");
            $stmt->execute([$test_id]);
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$test) {
                throw new Exception("Test not found!");
            }
            
            $stmt = $db->prepare("
                SELECT bi.*, b.bill_number 
                FROM bill_items bi
                INNER JOIN bills b ON bi.bill_id = b.id
                WHERE bi.item_type = 'lab_test'
                AND (
                    (bi.reference_id = ? AND bi.reference_type = 'lab_test')
                    OR (bi.item_name = ? AND bi.visit_id = ?)
                )
                ORDER BY bi.id DESC LIMIT 1
            ");
            $stmt->execute([$test_id, $test['test_name'], $test['visit_id']]);
            $bill_item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($bill_item) {
                $bill_id = $bill_item['bill_id'];
                $bill_number = $bill_item['bill_number'];
                
                $db->prepare("DELETE FROM bill_items WHERE id = ?")->execute([$bill_item['id']]);
                
                $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM bill_items WHERE bill_id = ? AND status != 'cancelled'");
                $stmt->execute([$bill_id]);
                $remaining = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
                
                if ($remaining === 0) {
                    $db->prepare("DELETE FROM payments WHERE bill_id = ?")->execute([$bill_id]);
                    $db->prepare("DELETE FROM bills WHERE id = ?")->execute([$bill_id]);
                } else {
                    recalculateBill($db, $bill_id);
                }
            }
            
            $db->prepare("DELETE FROM lab_tests WHERE id = ?")->execute([$test_id]);
            
            try {
                $log = "Deleted lab test: {$test['test_name']} (ID: $test_id)";
                if ($bill_item) $log .= " | Bill $bill_number auto-updated";
                
                $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) 
                              VALUES (?, ?, 'delete_lab_test', ?, ?, NOW())")
                   ->execute([
                       $user_id,
                       $selected_branch_id !== 'all' ? (int)$selected_branch_id : 1,
                       $log,
                       $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                   ]);
            } catch (Exception $e) {}
            
            $db->commit();
            
            $alert_message = "Lab test \"{$test['test_name']}\" deleted successfully!";
            $alert_type = 'success';
            
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $alert_message = "Error deleting test: " . $e->getMessage();
            $alert_type = 'error';
        }
    }
}

// ================================================================
// RECALCULATE BILL HELPER
// ================================================================
function recalculateBill($db, $bill_id) {
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(total_price), 0) as new_subtotal,
            COALESCE(SUM(discount_amount), 0) as new_items_discount
        FROM bill_items 
        WHERE bill_id = ? AND status != 'cancelled'
    ");
    $stmt->execute([$bill_id]);
    $recalc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $new_subtotal = (float)($recalc['new_subtotal'] ?? 0);
    $new_items_discount = (float)($recalc['new_items_discount'] ?? 0);
    
    $stmt = $db->prepare("SELECT discount_amount, premium_amount, paid_amount FROM bills WHERE id = ?");
    $stmt->execute([$bill_id]);
    $bill_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $bill_discount = (float)($bill_data['discount_amount'] ?? 0);
    $premium = (float)($bill_data['premium_amount'] ?? 0);
    $paid = (float)($bill_data['paid_amount'] ?? 0);
    
    $new_total_discount = $new_items_discount + $bill_discount;
    $new_total_amount = $new_subtotal - $new_total_discount + $premium;
    if ($new_total_amount < 0) $new_total_amount = 0;
    
    $new_balance = $new_total_amount - $paid;
    if ($new_balance < 0) $new_balance = 0;
    
    $new_status = 'pending';
    if ($new_balance <= 0 && $new_total_amount > 0) {
        $new_status = 'paid';
    } elseif ($paid > 0 && $new_balance > 0) {
        $new_status = 'partial';
    }
    
    $sql = "UPDATE bills SET 
        subtotal = ?, total_discount = ?, total_amount = ?,
        balance = ?, status = ?, updated_at = NOW()
        WHERE id = ?";
    $db->prepare($sql)->execute([
        $new_subtotal, $new_total_discount, $new_total_amount,
        $new_balance, $new_status, $bill_id
    ]);
}

// ================================================================
// GET PATIENT INFO
// ================================================================
$patient = null;
try {
    $stmt = $db->prepare("
        SELECT p.*, b.name as branch_name
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE p.id = ?
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if (!$patient) {
    header('Location: lab_tests.php?branch=' . $selected_branch_id);
    exit;
}

$patient_branch_id = (int)($patient['branch_id'] ?? 0);

// ================================================================
// GET LAB TEST CATALOG (STRICTLY BRANCH)
// ================================================================
$lab_catalog = [];
try {
    if ($patient_branch_id > 0) {
        $sql = "SELECT 
                    id, test_name, test_code, category, price, 
                    description, reference_range,
                    required_equipment_id, equipment_quantity_used
                FROM lab_tests_catalog
                WHERE is_active = 1
                AND branch_id = ?
                ORDER BY category, test_name";
        $stmt = $db->prepare($sql);
        $stmt->execute([$patient_branch_id]);
        $lab_catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Catalog fetch error: " . $e->getMessage());
}

// Group catalog by category
$catalog_by_category = [];
foreach ($lab_catalog as $item) {
    $cat = $item['category'] ?? 'Other';
    if (!isset($catalog_by_category[$cat])) {
        $catalog_by_category[$cat] = [];
    }
    $catalog_by_category[$cat][] = $item;
}

// ================================================================
// GET ALL LAB TESTS GROUPED BY VISIT
// ================================================================
$visits_data = [];
$all_tests = [];
try {
    $sql = "
        SELECT 
            lt.*,
            doc.full_name as doctor_name,
            recv.full_name as received_by_name,
            v.visit_number,
            v.visit_date,
            v.diagnosis,
            v.status as visit_status,
            b.name as branch_name
        FROM lab_tests lt
        LEFT JOIN users doc ON lt.doctor_id = doc.id
        LEFT JOIN users recv ON lt.performed_by = recv.id
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN branches b ON lt.branch_id = b.id
        WHERE lt.patient_id = ?
        ORDER BY v.visit_date DESC, lt.created_at DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$patient_id]);
    $all_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_tests as $test) {
        $vid = $test['visit_id'] ?? 0;
        if (!isset($visits_data[$vid])) {
            $visits_data[$vid] = [
                'visit_id' => $vid,
                'visit_number' => $test['visit_number'] ?? 'N/A',
                'visit_date' => $test['visit_date'],
                'diagnosis' => $test['diagnosis'] ?? '',
                'visit_status' => $test['visit_status'] ?? 'N/A',
                'doctor_name' => $test['doctor_name'] ?? 'N/A',
                'branch_name' => $test['branch_name'] ?? 'N/A',
                'tests' => [],
                'total_amount' => 0,
                'total_paid' => 0
            ];
        }
        
        $visits_data[$vid]['tests'][] = $test;
        $visits_data[$vid]['total_amount'] += (float)($test['test_price'] ?? 0);
        if (in_array($test['status'], ['completed', 'paid'])) {
            $visits_data[$vid]['total_paid'] += (float)($test['test_price'] ?? 0);
        }
    }
} catch (Exception $e) {
    error_log("Fetch tests error: " . $e->getMessage());
}

$visits_array = array_values($visits_data);

// ================================================================
// STATS
// ================================================================
$total_tests = 0;
$total_amount = 0;
$total_paid = 0;
$total_pending = 0;

foreach ($all_tests as $t) {
    $total_tests++;
    $total_amount += (float)($t['test_price'] ?? 0);
    if (in_array($t['status'], ['completed', 'paid'])) {
        $total_paid += (float)($t['test_price'] ?? 0);
    } else {
        $total_pending += (float)($t['test_price'] ?? 0);
    }
}

$total_visits = count($visits_array);

// HELPERS
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    try {
        return (new DateTime($dob))->diff(new DateTime('today'))->y . ' yrs';
    } catch (Exception $e) { return 'N/A'; }
}

function getStatusBadge($status) {
    $map = [
        'pending' => ['class' => 'warning', 'icon' => '⏳', 'label' => 'Pending'],
        'in_progress' => ['class' => 'info', 'icon' => '🔄', 'label' => 'In Progress'],
        'completed' => ['class' => 'success', 'icon' => '✅', 'label' => 'Completed'],
        'cancelled' => ['class' => 'danger', 'icon' => '❌', 'label' => 'Cancelled']
    ];
    return $map[$status] ?? ['class' => 'warning', 'icon' => '❓', 'label' => ucfirst($status)];
}

$patient_age = calculateAge($patient['date_of_birth']);
$initials = strtoupper(substr($patient['full_name'] ?? 'P', 0, 1));

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
    <title>Patient Lab Tests - <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></title>
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
    --primary-soft: #DBEAFE;
    
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
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
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
    --primary-bg: #1E3A5F;
    --primary-soft: #1E40AF;
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3A2A1A;
    --purple-bg: #2D1B4E;
    --cyan-bg: #0E3A47;
}

* { font-family: var(--font-primary); -webkit-font-smoothing: antialiased; }

html, body { font-family: var(--font-primary); background: var(--bg-body); color: var(--text-primary); }

.money-number, .money-cell, .font-mono, .money-input, .money-input-m, .mono {
    font-family: var(--font-mono) !important;
    font-feature-settings: 'tnum';
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

/* ALERT */
.alert {
    padding: 12px 18px; border-radius: 12px; margin-bottom: 16px;
    display: flex; align-items: center; gap: 10px;
    font-weight: 600; font-size: 0.82rem; animation: slideDown 0.4s ease;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.alert.success { background: var(--success-bg); color: var(--success); border-left: 4px solid var(--success); }
.alert.error { background: var(--danger-bg); color: var(--danger); border-left: 4px solid var(--danger); }
.alert i { font-size: 1.1rem; }

/* PAGE HEADER */
.page-header {
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
    border-radius: 16px; padding: 20px 24px; margin-bottom: 18px;
    display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center;
    gap: 14px; box-shadow: 0 6px 20px rgba(11, 94, 215, 0.25);
    position: relative; overflow: hidden;
}
.page-header::before {
    content: ''; position: absolute; top: -50%; right: -20%;
    width: 350px; height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius: 50%; pointer-events: none;
}
.page-header .page-title {
    color: white; font-size: 1.35rem; font-weight: 800;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    position: relative; z-index: 1; letter-spacing: -0.02em;
}
.page-header .page-title i { font-size: 1.5rem; color: #93C5FD; }
.page-header .page-subtitle {
    color: rgba(255,255,255,0.85); font-size: 0.78rem;
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    margin-top: 4px; position: relative; z-index: 1;
}
.branch-tag {
    background: rgba(255,255,255,0.15); color: white;
    padding: 3px 10px; border-radius: 16px; font-size: 0.65rem;
    font-weight: 500; display: inline-flex; align-items: center; gap: 4px;
    backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.1);
}
.btn-header {
    background: rgba(255,255,255,0.15); color: white;
    border: 1px solid rgba(255,255,255,0.25);
    padding: 8px 14px; border-radius: 9px;
    font-weight: 600; font-size: 0.75rem;
    transition: all 0.3s ease; text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
    backdrop-filter: blur(4px); position: relative; z-index: 1; cursor: pointer;
}
.btn-header:hover { background: rgba(255,255,255,0.28); transform: translateY(-2px); }

/* PATIENT CARD */
.patient-card {
    background: var(--bg-card); border-radius: 14px;
    border: 2px solid var(--border-color); padding: 18px 22px;
    margin-bottom: 18px; display: flex; align-items: center; gap: 16px;
    flex-wrap: wrap; box-shadow: var(--shadow-sm);
    position: relative; overflow: hidden;
}
.patient-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0;
    height: 4px; background: linear-gradient(90deg, #0B5ED7, #3B82F6);
}
.patient-avatar-lg {
    width: 72px; height: 72px; border-radius: 16px;
    background: linear-gradient(135deg, #0B5ED7, #3B82F6);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 2rem; font-weight: 900; text-transform: uppercase;
    flex-shrink: 0; box-shadow: 0 8px 20px rgba(11, 94, 215, 0.3);
    border: 3px solid var(--bg-card);
}
.patient-info-main { flex: 1; min-width: 250px; }
.patient-name-lg {
    font-size: 1.35rem; font-weight: 800; color: var(--text-primary);
    letter-spacing: -0.02em; line-height: 1.2; margin-bottom: 6px;
}
.patient-meta-lg { display: flex; flex-wrap: wrap; gap: 8px; }
.patient-meta-lg .meta-pill {
    font-size: 0.7rem; font-weight: 600; color: var(--text-secondary);
    display: inline-flex; align-items: center; gap: 4px;
    background: var(--bg-body); padding: 4px 10px;
    border-radius: 12px; border: 1px solid var(--border-color);
}
.patient-meta-lg .meta-pill i { color: var(--primary); font-size: 0.68rem; }

/* STATS */
.stats-grid-4 {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 12px; margin-bottom: 18px;
}
.stat-mini {
    background: var(--bg-card); border-radius: 12px;
    padding: 14px 16px; border: 2px solid var(--border-color);
    display: flex; align-items: center; gap: 12px;
    box-shadow: var(--shadow-sm); transition: all 0.3s ease;
}
.stat-mini:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.stat-mini .icon-box {
    width: 42px; height: 42px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; color: white; flex-shrink: 0;
}
.stat-mini.blue .icon-box { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.stat-mini.green .icon-box { background: linear-gradient(135deg, #059669, #34D399); }
.stat-mini.orange .icon-box { background: linear-gradient(135deg, #D97706, #FBBF24); }
.stat-mini.purple .icon-box { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
.stat-mini .stat-info .stat-lbl {
    font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.06em;
    color: var(--text-secondary); font-weight: 800; margin-bottom: 2px;
}
.stat-mini .stat-info .stat-val {
    font-size: 1.15rem; font-weight: 900; color: var(--text-primary);
    font-family: var(--font-mono); letter-spacing: -0.03em; line-height: 1.1;
}
.stat-mini.blue .stat-val { color: var(--primary); }
.stat-mini.green .stat-val { color: var(--success); }
.stat-mini.orange .stat-val { color: var(--warning); }
.stat-mini.purple .stat-val { color: var(--purple); }

/* VISIT CARD */
.visit-card {
    background: var(--bg-card); border-radius: 14px;
    border: 2px solid var(--border-color); margin-bottom: 18px;
    overflow: hidden; box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
}
.visit-card:hover { border-color: var(--primary); box-shadow: var(--shadow-md); }
.visit-header {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    padding: 14px 20px; display: flex; justify-content: space-between;
    align-items: center; gap: 12px; flex-wrap: wrap;
}
.visit-header-left {
    display: flex; align-items: center; gap: 12px;
    flex-wrap: wrap; flex: 1;
}
.visit-icon-badge {
    width: 42px; height: 42px; border-radius: 11px;
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F; display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);
    flex-shrink: 0;
}
.visit-number {
    font-family: var(--font-mono); font-size: 0.95rem; font-weight: 800;
    color: white; background: rgba(255,255,255,0.2);
    padding: 5px 12px; border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.25);
}
.visit-meta-item {
    color: rgba(255,255,255,0.9); font-size: 0.72rem;
    font-weight: 600; display: inline-flex; align-items: center; gap: 5px;
}
.visit-meta-item i { font-size: 0.7rem; }
.visit-header-right {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.visit-stat-pill {
    background: rgba(255,255,255,0.2); color: white;
    padding: 4px 12px; border-radius: 16px;
    font-size: 0.68rem; font-weight: 700;
    display: inline-flex; align-items: center; gap: 5px;
    border: 1px solid rgba(255,255,255,0.15);
}
.visit-stat-pill .num {
    font-family: var(--font-mono); font-weight: 900; font-size: 0.8rem;
}
.btn-edit-all {
    background: linear-gradient(135deg, #F59E0B, #D97706);
    color: white; padding: 7px 14px; border-radius: 8px;
    font-weight: 700; font-size: 0.72rem; border: none;
    cursor: pointer; display: inline-flex; align-items: center;
    gap: 6px; transition: all 0.3s ease; text-decoration: none;
}
.btn-edit-all:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(245, 158, 11, 0.5);
    color: white;
}

/* TABLE */
.table-wrapper { overflow-x: auto; scroll-behavior: smooth; }
.table-wrapper::-webkit-scrollbar { height: 6px; }
.table-wrapper::-webkit-scrollbar-track { background: var(--border-color); border-radius: 10px; }
.table-wrapper::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }

.data-table {
    width: 100%; border-collapse: collapse;
    font-size: 0.78rem; min-width: 900px;
}
.data-table thead th {
    text-align: left; padding: 10px 12px;
    font-weight: 800; font-size: 0.6rem; text-transform: uppercase;
    letter-spacing: 0.06em; color: white;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    white-space: nowrap;
}
.data-table tbody td {
    padding: 10px 12px; border-bottom: 1px solid var(--border-color);
    color: var(--text-primary); vertical-align: middle;
}
.data-table tbody tr:hover td { background: var(--primary-bg); }
.data-table tbody tr:last-child td { border-bottom: none; }

/* STATUS BADGE */
.status-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 18px;
    font-size: 0.62rem; font-weight: 700;
    text-transform: uppercase; white-space: nowrap;
}
.status-badge.warning { background: var(--warning-bg); color: var(--warning); }
.status-badge.success { background: var(--success-bg); color: var(--success); }
.status-badge.danger { background: var(--danger-bg); color: var(--danger); }
.status-badge.info { background: var(--primary-bg); color: var(--primary); }
.status-badge.purple { background: var(--purple-bg); color: var(--purple); }

/* ACTION BUTTONS */
.action-group { display: flex; gap: 4px; align-items: center; }
.btn-act {
    width: 30px; height: 30px; border-radius: 7px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.75rem; border: none; cursor: pointer;
    transition: all 0.25s ease; text-decoration: none; color: white;
}
.btn-act:hover { transform: translateY(-2px); color: white; }
.btn-act.view { background: #0B5ED7; }
.btn-act.view:hover { background: #0A4CA8; box-shadow: 0 4px 10px rgba(11, 94, 215, 0.4); }
.btn-act.edit { background: #F59E0B; }
.btn-act.edit:hover { background: #D97706; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.4); }
.btn-act.delete { background: #DC2626; }
.btn-act.delete:hover { background: #B91C1C; box-shadow: 0 4px 10px rgba(220, 38, 38, 0.4); }

/* MONEY CELL */
.money-cell {
    font-family: var(--font-mono); font-weight: 800;
    color: var(--primary); font-size: 0.82rem;
    text-align: right; white-space: nowrap;
}

/* EMPTY STATE */
.empty-state {
    text-align: center; padding: 60px 20px;
    color: var(--text-secondary); background: var(--bg-card);
    border-radius: 14px; border: 2px dashed var(--border-color);
}
.empty-state i {
    font-size: 3.5rem; color: var(--primary);
    display: block; margin-bottom: 12px; opacity: 0.4;
}
.empty-state p { font-size: 1rem; font-weight: 700; color: var(--text-primary); }
.empty-state .sub { font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px; font-weight: 400; }

/* MODAL */
.modal-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(6px);
    z-index: 99999; display: none; align-items: center; justify-content: center;
    padding: 20px; overflow-y: auto;
}
.modal-overlay.active { display: flex; }
.modal-box {
    background: var(--bg-card); border-radius: 20px;
    max-width: 700px; width: 100%; padding: 28px;
    box-shadow: var(--shadow-xl);
    animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    max-height: 90vh; overflow-y: auto; margin: auto;
}
.modal-box.sm { max-width: 440px; text-align: center; }
@keyframes modalPop {
    0% { opacity: 0; transform: scale(0.85) translateY(20px); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}
.modal-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 20px; padding-bottom: 14px;
    border-bottom: 2px solid var(--border-color);
}
.modal-title {
    font-size: 1.15rem; font-weight: 800; color: var(--text-primary);
    display: flex; align-items: center; gap: 8px;
}
.modal-title i { color: var(--primary); }
.modal-close {
    width: 34px; height: 34px; border-radius: 8px;
    background: var(--bg-body); border: 1px solid var(--border-color);
    color: var(--text-secondary); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem; transition: all 0.3s ease;
}
.modal-close:hover {
    background: var(--danger-bg); color: var(--danger);
    border-color: var(--danger);
}
.modal-form-grid {
    display: grid; grid-template-columns: repeat(2, 1fr);
    gap: 14px; margin-bottom: 20px;
}
.modal-form-grid .full-width { grid-column: 1 / -1; }
.modal-form-group { display: flex; flex-direction: column; gap: 5px; }
.modal-form-group label {
    font-size: 0.68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.04em;
    color: var(--text-secondary);
    display: flex; align-items: center; gap: 5px;
}
.modal-form-group label i { color: var(--primary); font-size: 0.68rem; }
.modal-form-group label .required { color: var(--danger); }
.modal-form-group input,
.modal-form-group select,
.modal-form-group textarea {
    padding: 9px 12px; border-radius: 9px;
    border: 2px solid var(--border-color);
    background: var(--bg-card); color: var(--text-primary);
    font-size: 0.82rem; font-weight: 600;
    outline: none; transition: all 0.3s ease;
}
.modal-form-group input:focus,
.modal-form-group select:focus,
.modal-form-group textarea:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
}
.modal-form-group textarea {
    resize: vertical; min-height: 60px;
    font-weight: 500; line-height: 1.5;
}
.money-wrapper-m {
    position: relative; display: flex; align-items: center;
}
.money-wrapper-m .currency-tag {
    position: absolute; left: 12px;
    font-size: 0.75rem; font-weight: 800;
    color: var(--primary); pointer-events: none; z-index: 2;
}
.money-wrapper-m input {
    padding-left: 48px !important; text-align: right;
    font-family: var(--font-mono) !important;
    font-weight: 800; color: var(--primary);
}
.modal-actions {
    display: flex; gap: 10px; justify-content: flex-end;
    flex-wrap: wrap; padding-top: 16px;
    border-top: 2px solid var(--border-color);
}
.modal-actions.center { justify-content: center; }
.modal-btn {
    padding: 10px 22px; border-radius: 10px;
    font-weight: 700; font-size: 0.8rem;
    border: none; cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex; align-items: center; gap: 7px;
}
.modal-btn:hover { transform: translateY(-2px); }
.modal-btn.cancel { background: var(--border-color); color: var(--text-primary); }
.modal-btn.cancel:hover { background: #CBD5E1; }
.modal-btn.primary { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; }
.modal-btn.primary:hover { box-shadow: 0 6px 20px rgba(11, 94, 215, 0.5); }
.modal-btn.success { background: linear-gradient(135deg, #059669, #047857); color: white; }
.modal-btn.success:hover { box-shadow: 0 6px 20px rgba(5, 150, 105, 0.5); }
.modal-btn.danger { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; }
.modal-btn.danger:hover { box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5); }

/* DELETE MODAL */
.modal-icon-danger {
    width: 68px; height: 68px; border-radius: 50%;
    background: var(--danger-bg); color: var(--danger);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem; margin: 0 auto 16px;
    animation: iconPulse 1.5s infinite;
}
@keyframes iconPulse {
    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); }
    50% { transform: scale(1.05); box-shadow: 0 0 0 12px rgba(220, 38, 38, 0); }
}
.delete-title {
    font-size: 1.2rem; font-weight: 800;
    margin-bottom: 8px; color: var(--text-primary);
}
.delete-text {
    font-size: 0.85rem; color: var(--text-secondary);
    margin-bottom: 20px; line-height: 1.7;
}
.delete-text strong {
    color: var(--primary); background: var(--primary-bg);
    padding: 2px 8px; border-radius: 6px;
    font-family: var(--font-mono); font-weight: 700;
}

/* CATALOG SELECTOR */
.catalog-box {
    background: linear-gradient(135deg, var(--primary-bg), var(--primary-soft));
    border: 2px solid var(--primary);
    border-radius: 12px;
    padding: 14px;
    margin-bottom: 18px;
}
.catalog-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.catalog-label {
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 6px;
}
.catalog-label .count-badge {
    background: var(--primary);
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.6rem;
    font-weight: 800;
    font-family: var(--font-mono);
}
.catalog-toggle-group {
    display: inline-flex;
    gap: 4px;
    background: var(--bg-card);
    border-radius: 8px;
    padding: 3px;
    border: 1px solid var(--primary);
}
.catalog-toggle-btn {
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 0.68rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    background: transparent;
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.25s ease;
}
.catalog-toggle-btn:hover { color: var(--primary); }
.catalog-toggle-btn.active {
    background: var(--primary);
    color: white;
    box-shadow: 0 2px 8px rgba(11, 94, 215, 0.3);
}
.catalog-mode { display: none; }
.catalog-mode.active { display: block; }
.catalog-search-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}
.catalog-search-icon {
    position: absolute;
    left: 12px;
    color: var(--primary);
    font-size: 0.85rem;
    pointer-events: none;
    z-index: 2;
}
.catalog-search-input {
    width: 100%;
    padding: 11px 40px 11px 38px;
    border-radius: 10px;
    border: 2px solid var(--primary);
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 0.85rem;
    font-weight: 600;
    outline: none;
    transition: all 0.3s ease;
    font-family: var(--font-primary);
}
.catalog-search-input:focus { box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.15); }
.catalog-search-input::placeholder { color: var(--text-secondary); font-weight: 400; }
.catalog-search-clear {
    position: absolute;
    right: 10px;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    background: var(--border-color);
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    transition: all 0.25s ease;
    z-index: 2;
}
.catalog-search-clear:hover { background: var(--danger-bg); color: var(--danger); }
.catalog-search-clear.visible { display: flex; }
.catalog-search-results {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    max-height: 320px;
    overflow-y: auto;
    background: var(--bg-card);
    border: 2px solid var(--primary);
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    z-index: 100;
    display: none;
}
.catalog-search-results.visible { display: block; }
.catalog-search-results::-webkit-scrollbar { width: 6px; }
.catalog-search-results::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 10px; }
.catalog-search-results::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
.catalog-result-item {
    padding: 10px 14px;
    cursor: pointer;
    border-bottom: 1px solid var(--border-color);
    transition: all 0.2s ease;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.catalog-result-item:last-child { border-bottom: none; }
.catalog-result-item:hover {
    background: var(--primary-bg);
    border-left: 3px solid var(--primary);
    padding-left: 11px;
}
.catalog-result-item .cr-left { flex: 1; min-width: 180px; }
.catalog-result-item .cr-name {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 3px;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.catalog-result-item .cr-code {
    font-family: var(--font-mono);
    font-size: 0.6rem;
    font-weight: 800;
    background: var(--primary-bg);
    color: var(--primary);
    padding: 2px 6px;
    border-radius: 4px;
    border: 1px solid var(--primary);
}
.catalog-result-item .cr-meta {
    font-size: 0.65rem;
    color: var(--text-secondary);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.catalog-result-item .cr-category {
    background: var(--purple-bg);
    color: var(--purple);
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 0.58rem;
    font-weight: 800;
    text-transform: uppercase;
}
.catalog-result-item .cr-price {
    font-family: var(--font-mono);
    font-weight: 800;
    color: var(--success);
    font-size: 0.82rem;
    white-space: nowrap;
    background: var(--success-bg);
    padding: 3px 8px;
    border-radius: 6px;
}
.catalog-no-results {
    padding: 20px;
    text-align: center;
    color: var(--text-secondary);
    font-size: 0.78rem;
    font-weight: 600;
}
.catalog-no-results i {
    display: block;
    font-size: 1.8rem;
    opacity: 0.3;
    margin-bottom: 8px;
    color: var(--primary);
}
.catalog-scroll-select {
    width: 100%;
    padding: 11px 14px;
    border-radius: 10px;
    border: 2px solid var(--primary);
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 0.85rem;
    font-weight: 700;
    outline: none;
    cursor: pointer;
    transition: all 0.3s ease;
}
.catalog-scroll-select:focus { box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.15); }
.catalog-preview {
    margin-top: 10px;
    padding: 10px 14px;
    background: var(--bg-card);
    border-radius: 10px;
    border-left: 4px solid var(--success);
    display: none;
    animation: slideDown 0.3s ease;
}
.catalog-preview.visible { display: block; }
.catalog-preview .cp-title {
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--success);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.catalog-preview .cp-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 8px;
}
.catalog-preview .cp-item {
    font-size: 0.7rem;
    color: var(--text-primary);
    font-weight: 600;
}
.catalog-preview .cp-item strong {
    color: var(--text-secondary);
    font-weight: 700;
    font-size: 0.6rem;
    text-transform: uppercase;
    display: block;
    margin-bottom: 2px;
}

/* BRANCH INFO NOTICE */
.branch-notice {
    background: linear-gradient(135deg, rgba(11,94,215,0.08), rgba(59,130,246,0.05));
    border-left: 4px solid var(--primary);
    border-radius: 8px;
    padding: 10px 14px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.72rem;
    color: var(--text-primary);
    font-weight: 600;
}
.branch-notice i {
    color: var(--primary);
    font-size: 0.9rem;
    flex-shrink: 0;
}
.branch-notice strong {
    color: var(--primary);
    font-weight: 800;
}

/* RESPONSIVE */
@media (max-width: 1024px) {
    .stats-grid-4 { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .stats-grid-4 { grid-template-columns: 1fr 1fr; gap: 10px; }
    .stat-mini { padding: 12px; }
    .stat-mini .icon-box { width: 36px; height: 36px; font-size: 0.95rem; }
    .stat-mini .stat-info .stat-val { font-size: 1rem; }
    .patient-avatar-lg { width: 58px; height: 58px; font-size: 1.6rem; }
    .patient-name-lg { font-size: 1.1rem; }
    .visit-header { padding: 12px 14px; }
    .modal-form-grid { grid-template-columns: 1fr; }
    .catalog-toggle-group { width: 100%; justify-content: center; }
}
@media (max-width: 480px) {
    .stats-grid-4 { grid-template-columns: 1fr; }
}
    </style>
</head>
<body>

<main class="main-content">

    <!-- ALERT -->
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
            }, 4000);
        </script>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                Patient Lab Tests
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user-injured"></i>
                <strong><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-clipboard-list"></i> <?= $total_visits ?> Visit(s)
                </span>
                <span class="branch-tag" style="background:rgba(52,211,153,0.25);">
                    <i class="fas fa-book"></i> <?= count($lab_catalog) ?> Tests in <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="patient_details.php?id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-user-injured"></i> Patient
            </a>
            <a href="lab_tests.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> All Lab Tests
            </a>
        </div>
    </div>

    <!-- BRANCH INFO NOTICE -->
    <div class="branch-notice">
        <i class="fas fa-info-circle"></i>
        <div>
            Catalog shows tests from <strong><?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></strong> branch only. 
            All <strong><?= count($lab_catalog) ?></strong> tests are available for this patient.
        </div>
    </div>

    <!-- PATIENT CARD -->
    <div class="patient-card">
        <div class="patient-avatar-lg"><?= $initials ?></div>
        <div class="patient-info-main">
            <div class="patient-name-lg"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></div>
            <div class="patient-meta-lg">
                <span class="meta-pill">
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                </span>
                <?php if (!empty($patient['gender'])): ?>
                <span class="meta-pill">
                    <i class="fas fa-<?= strtolower($patient['gender']) === 'female' ? 'venus' : 'mars' ?>"></i>
                    <?= htmlspecialchars($patient['gender']) ?>
                </span>
                <?php endif; ?>
                <?php if ($patient_age !== 'N/A'): ?>
                <span class="meta-pill">
                    <i class="fas fa-birthday-cake"></i> <?= $patient_age ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($patient['phone'])): ?>
                <span class="meta-pill">
                    <i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone']) ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($patient['branch_name'])): ?>
                <span class="meta-pill">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($patient['branch_name']) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats-grid-4">
        <div class="stat-mini blue">
            <div class="icon-box"><i class="fas fa-flask"></i></div>
            <div class="stat-info">
                <div class="stat-lbl">Total Tests</div>
                <div class="stat-val"><?= number_format($total_tests) ?></div>
            </div>
        </div>
        <div class="stat-mini green">
            <div class="icon-box"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-lbl">Paid / Completed</div>
                <div class="stat-val"><?= $currency ?> <?= number_format($total_paid, 0) ?></div>
            </div>
        </div>
        <div class="stat-mini orange">
            <div class="icon-box"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <div class="stat-lbl">Pending</div>
                <div class="stat-val"><?= $currency ?> <?= number_format($total_pending, 0) ?></div>
            </div>
        </div>
        <div class="stat-mini purple">
            <div class="icon-box"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-info">
                <div class="stat-lbl">Total Amount</div>
                <div class="stat-val"><?= $currency ?> <?= number_format($total_amount, 0) ?></div>
            </div>
        </div>
    </div>

    <!-- VISITS -->
    <?php if (count($visits_array) > 0): ?>
        <?php foreach ($visits_array as $visit): 
            $vid = $visit['visit_id'];
            $visit_tests = $visit['tests'];
            $test_count = count($visit_tests);
            $visit_total = $visit['total_amount'];
        ?>
            <div class="visit-card">
                <div class="visit-header">
                    <div class="visit-header-left">
                        <div class="visit-icon-badge">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <span class="visit-number"><?= htmlspecialchars($visit['visit_number']) ?></span>
                        <span class="visit-meta-item">
                            <i class="fas fa-calendar-day"></i>
                            <?= !empty($visit['visit_date']) ? date('d M Y', strtotime($visit['visit_date'])) : 'N/A' ?>
                        </span>
                        <?php if (!empty($visit['doctor_name']) && $visit['doctor_name'] !== 'N/A'): ?>
                        <span class="visit-meta-item">
                            <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($visit['doctor_name']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="visit-header-right">
                        <span class="visit-stat-pill">
                            <i class="fas fa-flask"></i>
                            <span class="num"><?= $test_count ?></span> Tests
                        </span>
                        <span class="visit-stat-pill" style="background:rgba(52,211,153,0.3);">
                            <i class="fas fa-money-bill-wave"></i>
                            <span class="num"><?= $currency ?> <?= number_format($visit_total, 0) ?></span>
                        </span>
                        <button type="button" 
                                class="btn-edit-all"
                                onclick="openEditAllModal(<?= $vid ?>, '<?= htmlspecialchars(addslashes($visit['visit_number'])) ?>')">
                            <i class="fas fa-edit"></i> EDIT ALL
                        </button>
                    </div>
                </div>
                
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:40px;">#</th>
                                <th><i class="fas fa-flask"></i> Test Name</th>
                                <th><i class="fas fa-tag"></i> Type</th>
                                <th><i class="fas fa-vial"></i> Sample</th>
                                <th><i class="fas fa-file-medical"></i> Result</th>
                                <th style="text-align:right;"><i class="fas fa-money-bill-wave"></i> Price</th>
                                <th style="text-align:center;"><i class="fas fa-info-circle"></i> Status</th>
                                <th style="text-align:center;"><i class="fas fa-cog"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $ti = 1; foreach ($visit_tests as $test): 
                                $badge = getStatusBadge($test['status']);
                            ?>
                                <tr>
                                    <td style="text-align:center;font-weight:700;color:var(--text-secondary);font-family:var(--font-mono);font-size:0.7rem;">
                                        <?= $ti++ ?>
                                    </td>
                                    <td>
                                        <span style="font-weight:700;color:var(--primary);">
                                            <?= htmlspecialchars($test['test_name'] ?? 'N/A') ?>
                                        </span>
                                        <?php if (!empty($test['test_code'])): ?>
                                            <span style="font-family:var(--font-mono);font-size:0.6rem;background:var(--primary-bg);color:var(--primary);padding:1px 6px;border-radius:4px;margin-left:4px;font-weight:800;">
                                                <?= htmlspecialchars($test['test_code']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($test['test_type'])): ?>
                                            <span class="status-badge purple"><?= htmlspecialchars($test['test_type']) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:0.75rem;">
                                        <?= htmlspecialchars($test['sample_type'] ?? '—') ?>
                                    </td>
                                    <td style="max-width:180px;">
                                        <?php if (!empty($test['results'])): ?>
                                            <span style="font-family:var(--font-mono);font-weight:700;color:var(--success);font-size:0.72rem;">
                                                <?= htmlspecialchars(substr($test['results'], 0, 35)) ?>
                                                <?= strlen($test['results']) > 35 ? '...' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary);font-style:italic;font-size:0.7rem;">
                                                Waiting...
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="money-cell">
                                        <?= $currency ?> <?= number_format($test['test_price'] ?? 0, 0) ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="status-badge <?= $badge['class'] ?>">
                                            <?= $badge['icon'] ?> <?= $badge['label'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-group">
                                            <a href="view_lab_test.php?id=<?= $test['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                               class="btn-act view" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_lab_test.php?id=<?= $test['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                               class="btn-act edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn-act delete" 
                                                    title="Delete"
                                                    onclick="confirmDeleteTest(<?= $test['id'] ?>, '<?= htmlspecialchars(addslashes($test['test_name'])) ?>', '<?= htmlspecialchars(addslashes($test['status'])) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-flask"></i>
            <p>No lab tests found for this patient</p>
            <p class="sub">This patient has no lab tests recorded yet</p>
        </div>
    <?php endif; ?>

</main>

<!-- ============================================================ -->
<!-- MODAL: EDIT ALL -->
<!-- ============================================================ -->
<div class="modal-overlay" id="editAllModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-edit"></i>
                Edit All Tests - <span id="editAllVisitNumber" style="color:var(--primary);font-family:var(--font-mono);">#</span>
            </div>
            <button type="button" class="modal-close" onclick="closeEditAllModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div id="editAllTestList">
            <p style="text-align:center;color:var(--text-secondary);padding:20px;">
                <i class="fas fa-spinner fa-spin"></i> Loading tests...
            </p>
        </div>
        
        <div style="margin-top:16px;padding-top:16px;border-top:2px dashed var(--border-color);text-align:center;">
            <button type="button" class="modal-btn success" onclick="openAddTestInline()">
                <i class="fas fa-plus"></i> Add New Test
            </button>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: ADD NEW TEST -->
<!-- ============================================================ -->
<div class="modal-overlay" id="addTestModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-plus-circle"></i>
                Add New Lab Test
            </div>
            <button type="button" class="modal-close" onclick="closeAddTestModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" id="addTestForm">
            <input type="hidden" name="action" value="add_test">
            <input type="hidden" name="visit_id" id="addTestVisitId" value="">
            
            <!-- CATALOG SELECTOR -->
            <div class="catalog-box">
                <div class="catalog-header">
                    <div class="catalog-label">
                        <i class="fas fa-book-medical"></i>
                        Lab Test Catalog — <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?> Branch
                        <span class="count-badge"><?= count($lab_catalog) ?> tests</span>
                    </div>
                    
                    <div class="catalog-toggle-group">
                        <button type="button" 
                                class="catalog-toggle-btn active" 
                                id="toggleSearchBtn"
                                onclick="setCatalogMode('search')">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <button type="button" 
                                class="catalog-toggle-btn" 
                                id="toggleScrollBtn"
                                onclick="setCatalogMode('scroll')">
                            <i class="fas fa-list"></i> Scroll
                        </button>
                    </div>
                </div>
                
                <!-- SEARCH MODE -->
                <div class="catalog-mode active" id="catalogSearchMode">
                    <div class="catalog-search-wrapper" style="position:relative;">
                        <i class="fas fa-search catalog-search-icon"></i>
                        <input type="text" 
                               class="catalog-search-input" 
                               id="catalogSearchInput"
                               placeholder="Search test by name, code, or category..."
                               autocomplete="off">
                        <button type="button" 
                                class="catalog-search-clear" 
                                id="catalogSearchClear"
                                onclick="clearCatalogSearch()">
                            <i class="fas fa-times"></i>
                        </button>
                        
                        <div class="catalog-search-results" id="catalogSearchResults"></div>
                    </div>
                </div>
                
                <!-- SCROLL MODE -->
                <div class="catalog-mode" id="catalogScrollMode">
                    <select class="catalog-scroll-select" 
                            id="catalogScrollSelect"
                            onchange="loadCatalogTest(this.value)">
                        <option value="">-- Select Test from Catalog --</option>
                        <?php foreach ($catalog_by_category as $cat => $items): ?>
                            <optgroup label="📁 <?= htmlspecialchars($cat) ?> (<?= count($items) ?>)">
                                <?php foreach ($items as $item): ?>
                                    <option value="<?= $item['id'] ?>"
                                            data-name="<?= htmlspecialchars($item['test_name']) ?>"
                                            data-code="<?= htmlspecialchars($item['test_code'] ?? '') ?>"
                                            data-category="<?= htmlspecialchars($item['category'] ?? '') ?>"
                                            data-price="<?= htmlspecialchars($item['price'] ?? 0) ?>"
                                            data-range="<?= htmlspecialchars($item['reference_range'] ?? '') ?>"
                                            data-desc="<?= htmlspecialchars($item['description'] ?? '') ?>">
                                        <?= htmlspecialchars($item['test_name']) ?>
                                        <?php if (!empty($item['test_code'])): ?>
                                            (<?= htmlspecialchars($item['test_code']) ?>)
                                        <?php endif; ?>
                                        — <?= $currency ?> <?= number_format((float)$item['price'], 0) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- PREVIEW -->
                <div class="catalog-preview" id="catalogPreview">
                    <div class="cp-title">
                        <i class="fas fa-check-circle"></i>
                        Selected Test
                        <button type="button" onclick="clearCatalogSelection()" 
                                style="margin-left:auto;background:none;border:none;color:var(--danger);cursor:pointer;font-size:0.7rem;font-weight:700;">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                    <div class="cp-content">
                        <div class="cp-item"><strong>Test Name</strong><span id="previewName">—</span></div>
                        <div class="cp-item"><strong>Code</strong><span id="previewCode">—</span></div>
                        <div class="cp-item"><strong>Category</strong><span id="previewCategory">—</span></div>
                        <div class="cp-item"><strong>Price</strong><span id="previewPrice">—</span></div>
                    </div>
                </div>
                
                <?php if (empty($lab_catalog)): ?>
                <div style="margin-top:10px;padding:10px 14px;background:var(--warning-bg);border-radius:8px;border-left:3px solid var(--warning);font-size:0.72rem;color:var(--warning);font-weight:600;">
                    <i class="fas fa-exclamation-triangle"></i>
                    No tests in <strong><?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></strong> branch catalog. 
                    You can add a test manually below.
                </div>
                <?php endif; ?>
            </div>
            
            <div class="modal-form-grid">
                <div class="modal-form-group full-width">
                    <label><i class="fas fa-microscope"></i> Test Name <span class="required">*</span></label>
                    <input type="text" name="test_name" id="addTestName" placeholder="e.g. Complete Blood Count" required>
                </div>
                
                <div class="modal-form-group">
                    <label><i class="fas fa-barcode"></i> Test Code</label>
                    <input type="text" name="test_code" id="addTestCode" placeholder="e.g. CBC-001" readonly 
                           style="background:var(--bg-body);cursor:not-allowed;font-family:var(--font-mono);">
                </div>
                
                <div class="modal-form-group">
                    <label><i class="fas fa-tag"></i> Category / Type</label>
                    <input type="text" name="test_type" id="addTestType" placeholder="e.g. Hematology">
                </div>
                
                <div class="modal-form-group">
                    <label><i class="fas fa-vial"></i> Sample Type</label>
                    <input type="text" name="sample_type" id="addTestSample" placeholder="e.g. Blood">
                </div>
                
                <div class="modal-form-group">
                    <label><i class="fas fa-calendar"></i> Test Date</label>
                    <input type="date" name="test_date" id="addTestDate" value="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="modal-form-group">
                    <label><i class="fas fa-money-bill-wave"></i> Price <span class="required">*</span></label>
                    <div class="money-wrapper-m">
                        <span class="currency-tag"><?= $currency ?></span>
                        <input type="text" name="test_price" id="addTestPrice" class="money-input-m" value="0" inputmode="numeric" autocomplete="off" required>
                    </div>
                </div>
                
                <div class="modal-form-group">
                    <label><i class="fas fa-flag"></i> Status</label>
                    <select name="status" id="addTestStatus">
                        <option value="pending">⏳ Pending</option>
                        <option value="in_progress">🔄 In Progress</option>
                        <option value="completed">✅ Completed</option>
                    </select>
                </div>
                
                <div class="modal-form-group full-width">
                    <label><i class="fas fa-chart-line"></i> Reference Range</label>
                    <input type="text" name="reference_range" id="addTestRange" placeholder="e.g. RBC: 4.5-5.5M">
                </div>
                
                <div class="modal-form-group full-width">
                    <label><i class="fas fa-align-left"></i> Description / Notes</label>
                    <textarea name="notes" id="addTestNotes" rows="2" placeholder="Additional notes..."></textarea>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="modal-btn cancel" onclick="closeAddTestModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="modal-btn success">
                    <i class="fas fa-plus"></i> Add Test
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: EDIT SINGLE TEST -->
<!-- ============================================================ -->
<div class="modal-overlay" id="editSingleTestModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-edit"></i>
                Edit Lab Test
            </div>
            <button type="button" class="modal-close" onclick="closeEditSingleModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" id="editSingleForm">
            <input type="hidden" name="action" value="update_test">
            <input type="hidden" name="test_id" id="editSingleTestId" value="">
            
            <div class="modal-form-grid">
                <div class="modal-form-group full-width">
                    <label><i class="fas fa-microscope"></i> Test Name <span class="required">*</span></label>
                    <input type="text" name="test_name" id="editSingleName" required>
                </div>
                
                <div class="modal-form-group">
                    <label><i class="fas fa-tag"></i> Test Type</label>
                    <input type="text" name="test_type" id="editSingleType">
                </div>
                
                <div class="modal-form-group">
                    <label><i class="fas fa-vial"></i> Sample Type</label>
                    <input type="text" name="sample_type" id="editSingleSample">
                </div>
                
                <div class="modal-form-group">
                    <label><i class="fas fa-calendar"></i> Test Date</label>
                    <input type="date" name="test_date" id="editSingleDate">
                </div>
                
                <div class="modal-form-group">
                    <label><i class="fas fa-money-bill-wave"></i> Price <span class="required">*</span></label>
                    <div class="money-wrapper-m">
                        <span class="currency-tag"><?= $currency ?></span>
                        <input type="text" name="test_price" id="editSinglePrice" class="money-input-m" inputmode="numeric" autocomplete="off" required>
                    </div>
                </div>
                
                <div class="modal-form-group">
                    <label><i class="fas fa-flag"></i> Status</label>
                    <select name="status" id="editSingleStatus">
                        <option value="pending">⏳ Pending</option>
                        <option value="in_progress">🔄 In Progress</option>
                        <option value="completed">✅ Completed</option>
                        <option value="cancelled">❌ Cancelled</option>
                    </select>
                </div>
                
                <div class="modal-form-group full-width">
                    <label><i class="fas fa-clipboard-check"></i> Result</label>
                    <textarea name="results" id="editSingleResults" rows="2"></textarea>
                </div>
                
                <div class="modal-form-group">
                    <label><i class="fas fa-chart-line"></i> Reference Range</label>
                    <input type="text" name="reference_range" id="editSingleRange">
                </div>
                
                <div class="modal-form-group">
                    <label><i class="fas fa-sticky-note"></i> Notes</label>
                    <input type="text" name="notes" id="editSingleNotes">
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="modal-btn cancel" onclick="closeEditSingleModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="modal-btn primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: DELETE CONFIRM -->
<!-- ============================================================ -->
<div class="modal-overlay" id="deleteTestModal">
    <div class="modal-box sm">
        <div class="modal-icon-danger">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="delete-title">Delete Lab Test?</h3>
        <p class="delete-text">
            Are you sure you want to delete<br>
            <strong id="deleteTestName">#</strong>
            <span id="deletePaidWarning" style="display:none;color:var(--danger);font-weight:700;margin-top:6px;">
                <i class="fas fa-exclamation-circle"></i> This test is PAID - Bill will be updated!
            </span>
        </p>
        
        <form method="POST" id="deleteTestForm">
            <input type="hidden" name="action" value="delete_test">
            <input type="hidden" name="test_id" id="deleteTestId" value="">
            <div class="modal-actions center">
                <button type="button" class="modal-btn cancel" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="modal-btn danger">
                    <i class="fas fa-trash"></i> Yes, Delete
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- HIDDEN DATA -->
<!-- ============================================================ -->
<script id="visitsDataScript" type="application/json">
<?= json_encode(array_map(function($v) {
    return [
        'visit_id' => $v['visit_id'],
        'visit_number' => $v['visit_number'],
        'tests' => array_map(function($t) {
            return [
                'id' => $t['id'],
                'test_name' => $t['test_name'],
                'test_code' => $t['test_code'] ?? '',
                'test_type' => $t['test_type'],
                'sample_type' => $t['sample_type'],
                'test_date' => $t['test_date'],
                'test_price' => $t['test_price'],
                'status' => $t['status'],
                'results' => $t['results'],
                'reference_range' => $t['reference_range'],
                'notes' => $t['notes'],
            ];
        }, $v['tests'])
    ];
}, $visits_array), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>

<script id="catalogDataScript" type="application/json">
<?= json_encode($lab_catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>

<script>
// ================================================================
// GLOBAL DATA
// ================================================================
var VISITS_DATA = {};
var CATALOG_DATA = [];
var CURRENT_VISIT_ID = 0;
var CATALOG_MODE = 'search';
var SELECTED_CATALOG_ITEM = null;

try {
    var vScript = document.getElementById('visitsDataScript');
    if (vScript) {
        var arr = JSON.parse(vScript.textContent);
        arr.forEach(function(v) { VISITS_DATA[v.visit_id] = v; });
    }
} catch (e) { console.error('Parse visits error:', e); }

try {
    var cScript = document.getElementById('catalogDataScript');
    if (cScript) {
        CATALOG_DATA = JSON.parse(cScript.textContent);
    }
} catch (e) { console.error('Parse catalog error:', e); }

console.log('📋 Catalog loaded:', CATALOG_DATA.length, 'tests (branch-specific)');

// ================================================================
// MONEY FORMAT
// ================================================================
function formatMoney(value) {
    var cleaned = String(value).replace(/[^0-9.]/g, '');
    var parts = cleaned.split('.');
    var intPart = parts[0].replace(/^0+/, '') || '0';
    intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    if (parts.length > 1) return intPart + '.' + parts[1];
    return intPart;
}

function attachMoneyFormat(input) {
    if (!input || input.dataset.moneyAttached === '1') return;
    input.dataset.moneyAttached = '1';
    
    if (input.value && input.value !== '0') {
        input.value = formatMoney(input.value);
    }
    
    input.addEventListener('input', function() {
        var cursorPos = this.selectionStart;
        var oldLength = this.value.length;
        var formatted = formatMoney(this.value);
        this.value = formatted;
        var newPos = cursorPos + (formatted.length - oldLength);
        if (this.setSelectionRange) this.setSelectionRange(newPos, newPos);
    });
    
    input.addEventListener('blur', function() {
        if (!this.value || this.value === '.') this.value = '0';
        else this.value = formatMoney(this.value);
    });
    
    input.addEventListener('keypress', function(e) {
        var c = String.fromCharCode(e.which);
        if (!/[0-9.]/.test(c)) e.preventDefault();
    });
}

// ================================================================
// CATALOG MODE TOGGLE
// ================================================================
function setCatalogMode(mode) {
    CATALOG_MODE = mode;
    
    var searchBtn = document.getElementById('toggleSearchBtn');
    var scrollBtn = document.getElementById('toggleScrollBtn');
    var searchMode = document.getElementById('catalogSearchMode');
    var scrollMode = document.getElementById('catalogScrollMode');
    var results = document.getElementById('catalogSearchResults');
    
    if (mode === 'search') {
        searchBtn.classList.add('active');
        scrollBtn.classList.remove('active');
        searchMode.classList.add('active');
        scrollMode.classList.remove('active');
        setTimeout(function() {
            document.getElementById('catalogSearchInput').focus();
        }, 100);
    } else {
        searchBtn.classList.remove('active');
        scrollBtn.classList.add('active');
        searchMode.classList.remove('active');
        scrollMode.classList.add('active');
        results.classList.remove('visible');
    }
}

// ================================================================
// CATALOG SEARCH
// ================================================================
function initCatalogSearch() {
    var input = document.getElementById('catalogSearchInput');
    var clearBtn = document.getElementById('catalogSearchClear');
    var resultsBox = document.getElementById('catalogSearchResults');
    
    if (!input) return;
    
    input.addEventListener('input', function() {
        var query = this.value.trim().toLowerCase();
        
        if (query.length > 0) {
            clearBtn.classList.add('visible');
        } else {
            clearBtn.classList.remove('visible');
            resultsBox.classList.remove('visible');
            return;
        }
        
        var matches = CATALOG_DATA.filter(function(item) {
            var name = (item.test_name || '').toLowerCase();
            var code = (item.test_code || '').toLowerCase();
            var category = (item.category || '').toLowerCase();
            return name.indexOf(query) !== -1 || 
                   code.indexOf(query) !== -1 || 
                   category.indexOf(query) !== -1;
        });
        
        renderSearchResults(matches, query);
    });
    
    input.addEventListener('focus', function() {
        if (this.value.trim().length > 0) {
            var query = this.value.trim().toLowerCase();
            var matches = CATALOG_DATA.filter(function(item) {
                var name = (item.test_name || '').toLowerCase();
                var code = (item.test_code || '').toLowerCase();
                var category = (item.category || '').toLowerCase();
                return name.indexOf(query) !== -1 || 
                       code.indexOf(query) !== -1 || 
                       category.indexOf(query) !== -1;
            });
            renderSearchResults(matches, query);
        }
    });
    
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#catalogSearchMode')) {
            resultsBox.classList.remove('visible');
        }
    });
}

function renderSearchResults(matches, query) {
    var resultsBox = document.getElementById('catalogSearchResults');
    
    if (matches.length === 0) {
        resultsBox.innerHTML = '<div class="catalog-no-results">' +
            '<i class="fas fa-search"></i>' +
            '<div>No tests found for "' + escapeHtml(query) + '"</div>' +
            '</div>';
        resultsBox.classList.add('visible');
        return;
    }
    
    var html = '';
    matches.slice(0, 30).forEach(function(item, idx) {
        var name = item.test_name || '';
        var code = item.test_code || '';
        var category = item.category || '';
        var price = parseFloat(item.price || 0);
        
        var highlightedName = highlightMatch(name, query);
        
        html += '<div class="catalog-result-item" onclick="selectCatalogItem(' + item.id + ')">' +
            '<div class="cr-left">' +
                '<div class="cr-name">' +
                    highlightedName +
                    (code ? '<span class="cr-code">' + escapeHtml(code) + '</span>' : '') +
                '</div>' +
                '<div class="cr-meta">' +
                    (category ? '<span class="cr-category">' + escapeHtml(category) + '</span>' : '') +
                '</div>' +
            '</div>' +
            '<div class="cr-price"><?= $currency ?> ' + price.toLocaleString('en-US', { maximumFractionDigits: 0 }) + '</div>' +
        '</div>';
    });
    
    if (matches.length > 30) {
        html += '<div style="padding:10px 14px;text-align:center;font-size:0.7rem;color:var(--text-secondary);font-weight:600;background:var(--bg-body);">' +
            '<i class="fas fa-info-circle"></i> Showing 30 of ' + matches.length + ' results. Type more to narrow down.' +
            '</div>';
    }
    
    resultsBox.innerHTML = html;
    resultsBox.classList.add('visible');
}

function highlightMatch(text, query) {
    if (!text || !query) return escapeHtml(text);
    var escaped = escapeHtml(text);
    var regex = new RegExp('(' + escapeRegex(query) + ')', 'gi');
    return escaped.replace(regex, '<mark style="background:#FEF08A;color:#854D0E;padding:1px 3px;border-radius:3px;font-weight:800;">$1</mark>');
}

function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function escapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function clearCatalogSearch() {
    document.getElementById('catalogSearchInput').value = '';
    document.getElementById('catalogSearchClear').classList.remove('visible');
    document.getElementById('catalogSearchResults').classList.remove('visible');
    document.getElementById('catalogSearchInput').focus();
}

// ================================================================
// SELECT CATALOG ITEM
// ================================================================
function selectCatalogItem(catalogId) {
    var item = CATALOG_DATA.find(function(c) { return c.id == catalogId; });
    if (!item) {
        alert('Test not found in catalog!');
        return;
    }
    
    SELECTED_CATALOG_ITEM = item;
    
    document.getElementById('addTestName').value = item.test_name || '';
    document.getElementById('addTestCode').value = item.test_code || '';
    document.getElementById('addTestType').value = item.category || '';
    document.getElementById('addTestSample').value = item.sample_type || 'Blood';
    document.getElementById('addTestRange').value = item.reference_range || '';
    document.getElementById('addTestNotes').value = item.description || '';
    
    var priceInput = document.getElementById('addTestPrice');
    priceInput.value = formatMoney(item.price || 0);
    
    document.getElementById('previewName').textContent = item.test_name || '—';
    document.getElementById('previewCode').textContent = item.test_code || '—';
    document.getElementById('previewCategory').textContent = item.category || '—';
    document.getElementById('previewPrice').textContent = '<?= $currency ?> ' + parseFloat(item.price || 0).toLocaleString('en-US', { maximumFractionDigits: 0 });
    document.getElementById('catalogPreview').classList.add('visible');
    
    document.getElementById('catalogSearchResults').classList.remove('visible');
    
    console.log('✅ Selected:', item.test_name);
}

function loadCatalogTest(catalogId) {
    if (!catalogId) {
        clearCatalogSelection();
        return;
    }
    selectCatalogItem(catalogId);
}

function clearCatalogSelection() {
    SELECTED_CATALOG_ITEM = null;
    document.getElementById('addTestName').value = '';
    document.getElementById('addTestCode').value = '';
    document.getElementById('addTestType').value = '';
    document.getElementById('addTestSample').value = '';
    document.getElementById('addTestRange').value = '';
    document.getElementById('addTestNotes').value = '';
    document.getElementById('addTestPrice').value = '0';
    document.getElementById('catalogPreview').classList.remove('visible');
    document.getElementById('catalogScrollSelect').value = '';
    document.getElementById('catalogSearchInput').value = '';
    document.getElementById('catalogSearchClear').classList.remove('visible');
}

// ================================================================
// EDIT ALL MODAL
// ================================================================
function openEditAllModal(visitId, visitNumber) {
    CURRENT_VISIT_ID = visitId;
    document.getElementById('editAllVisitNumber').textContent = visitNumber;
    
    var visit = VISITS_DATA[visitId];
    var listContainer = document.getElementById('editAllTestList');
    
    if (!visit || !visit.tests || visit.tests.length === 0) {
        listContainer.innerHTML = '<p style="text-align:center;color:var(--text-secondary);padding:20px;">No tests in this visit.</p>';
    } else {
        var html = '<div style="display:flex;flex-direction:column;gap:8px;max-height:400px;overflow-y:auto;padding-right:4px;">';
        
        visit.tests.forEach(function(t, idx) {
            var badge = getStatusBadgeJS(t.status);
            html += '<div style="background:var(--bg-body);border:2px solid var(--border-color);border-radius:10px;padding:12px 14px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">' +
                '<div style="flex:1;min-width:180px;">' +
                    '<div style="font-weight:700;color:var(--primary);font-size:0.85rem;margin-bottom:4px;">' +
                        (idx + 1) + '. ' + escapeHtml(t.test_name) +
                        (t.test_code ? ' <span style="font-family:var(--font-mono);font-size:0.6rem;background:var(--primary-bg);color:var(--primary);padding:1px 6px;border-radius:4px;">' + escapeHtml(t.test_code) + '</span>' : '') +
                    '</div>' +
                    '<div style="font-size:0.68rem;color:var(--text-secondary);display:flex;gap:10px;flex-wrap:wrap;">' +
                        '<span><i class="fas fa-money-bill-wave"></i> <?= $currency ?> ' + parseFloat(t.test_price || 0).toLocaleString() + '</span>' +
                        '<span class="status-badge ' + badge.class + '">' + badge.label + '</span>' +
                    '</div>' +
                '</div>' +
                '<div style="display:flex;gap:6px;">' +
                    '<button type="button" class="btn-act edit" onclick="openEditSingle(' + t.id + ')" title="Edit">' +
                        '<i class="fas fa-edit"></i>' +
                    '</button>' +
                    '<button type="button" class="btn-act delete" onclick="confirmDeleteFromEditAll(' + t.id + ', \'' + escapeJs(t.test_name) + '\', \'' + escapeJs(t.status) + '\')" title="Delete">' +
                        '<i class="fas fa-trash"></i>' +
                    '</button>' +
                '</div>' +
            '</div>';
        });
        
        html += '</div>';
        listContainer.innerHTML = html;
    }
    
    document.getElementById('editAllModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeEditAllModal() {
    document.getElementById('editAllModal').classList.remove('active');
    document.body.style.overflow = '';
}

// ================================================================
// ADD TEST MODAL
// ================================================================
function openAddTestInline() {
    document.getElementById('addTestVisitId').value = CURRENT_VISIT_ID;
    document.getElementById('addTestDate').value = '<?= date('Y-m-d') ?>';
    
    clearCatalogSelection();
    
    document.getElementById('addTestModal').classList.add('active');
    
    setTimeout(function() {
        document.querySelectorAll('#addTestForm .money-input-m').forEach(function(inp) {
            attachMoneyFormat(inp);
        });
        setCatalogMode('search');
    }, 100);
}

function closeAddTestModal() {
    document.getElementById('addTestModal').classList.remove('active');
    document.getElementById('catalogSearchResults').classList.remove('visible');
    if (!document.getElementById('editAllModal').classList.contains('active')) {
        document.body.style.overflow = '';
    }
}

// ================================================================
// EDIT SINGLE MODAL
// ================================================================
function openEditSingle(testId) {
    var test = null;
    for (var vid in VISITS_DATA) {
        var found = VISITS_DATA[vid].tests.find(function(t) { return t.id == testId; });
        if (found) { test = found; break; }
    }
    
    if (!test) {
        alert('Test not found!');
        return;
    }
    
    document.getElementById('editSingleTestId').value = test.id;
    document.getElementById('editSingleName').value = test.test_name || '';
    document.getElementById('editSingleType').value = test.test_type || '';
    document.getElementById('editSingleSample').value = test.sample_type || '';
    document.getElementById('editSingleDate').value = test.test_date || '';
    document.getElementById('editSinglePrice').value = formatMoney(test.test_price || 0);
    document.getElementById('editSingleStatus').value = test.status || 'pending';
    document.getElementById('editSingleResults').value = test.results || '';
    document.getElementById('editSingleRange').value = test.reference_range || '';
    document.getElementById('editSingleNotes').value = test.notes || '';
    
    document.getElementById('editSingleTestModal').classList.add('active');
    
    setTimeout(function() {
        document.querySelectorAll('#editSingleForm .money-input-m').forEach(function(inp) {
            attachMoneyFormat(inp);
        });
    }, 100);
}

function closeEditSingleModal() {
    document.getElementById('editSingleTestModal').classList.remove('active');
    if (!document.getElementById('editAllModal').classList.contains('active')) {
        document.body.style.overflow = '';
    }
}

// ================================================================
// DELETE
// ================================================================
function confirmDeleteTest(testId, testName, status) {
    document.getElementById('deleteTestId').value = testId;
    document.getElementById('deleteTestName').textContent = testName;
    
    var warning = document.getElementById('deletePaidWarning');
    if (status === 'completed' || status === 'paid') {
        warning.style.display = 'block';
    } else {
        warning.style.display = 'none';
    }
    
    document.getElementById('deleteTestModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function confirmDeleteFromEditAll(testId, testName, status) {
    closeEditAllModal();
    confirmDeleteTest(testId, testName, status);
}

function closeDeleteModal() {
    document.getElementById('deleteTestModal').classList.remove('active');
    document.body.style.overflow = '';
}

// ================================================================
// HELPERS
// ================================================================
function getStatusBadgeJS(status) {
    var map = {
        'pending': { class: 'warning', label: '⏳ Pending' },
        'in_progress': { class: 'info', label: '🔄 In Progress' },
        'completed': { class: 'success', label: '✅ Completed' },
        'cancelled': { class: 'danger', label: '❌ Cancelled' }
    };
    return map[status] || { class: 'warning', label: status };
}

function escapeJs(str) {
    if (!str) return '';
    return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"').replace(/\n/g, '\\n');
}

// ================================================================
// CLOSE MODALS
// ================================================================
document.querySelectorAll('.modal-overlay').forEach(function(m) {
    m.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
            if (!document.querySelector('.modal-overlay.active')) {
                document.body.style.overflow = '';
            }
        }
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(function(m) {
            m.classList.remove('active');
        });
        document.body.style.overflow = '';
    }
});

// ================================================================
// INIT
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    initCatalogSearch();
});

console.log('%c🧪 Patient Lab Tests (Branch-Filtered, No Lab Tech)', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Patient: <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>', 'font-size:12px;color:#34D399;');
console.log('%c✅ Branch: <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?>', 'font-size:12px;color:#34D399;');
console.log('%c✅ Total Visits: <?= $total_visits ?>', 'font-size:12px;color:#34D399;');
console.log('%c✅ Total Tests: <?= $total_tests ?>', 'font-size:12px;color:#34D399;');
console.log('%c✅ Catalog: <?= count($lab_catalog) ?> tests (<?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?> branch only)', 'font-size:12px;color:#7C3AED;font-weight:bold;');
console.log('%c✅ No Lab Tech assignment needed', 'font-size:12px;color:#F59E0B;font-weight:bold;');
</script>

</body>
</html>