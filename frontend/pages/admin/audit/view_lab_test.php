<?php
// ================================================================
// FILE: frontend/pages/admin/audit/view_lab_test.php
// ADMIN AUDIT - VIEW LAB TEST DETAILS
// ✅ View lab test full details
// ✅ Delete button (with bill update if paid)
// ✅ Print & PDF
// ✅ BLUE THEME
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

$test_id = (int)($_GET['id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($test_id <= 0) {
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
// GET LAB TEST DETAILS
// ================================================================
$test = null;
try {
    $sql = "SELECT 
        lt.*,
        pat.full_name as patient_name,
        pat.patient_id as patient_number,
        pat.phone as patient_phone,
        pat.gender as patient_gender,
        pat.date_of_birth,
        pat.address as patient_address,
        pat.blood_group as patient_blood,
        pat.allergies as patient_allergies,
        doc.full_name as doctor_name,
        tech.full_name as lab_technician_name,
        recv.full_name as received_by_name,
        b.name as branch_name,
        v.visit_number,
        v.visit_date,
        v.diagnosis,
        v.status as visit_status
    FROM lab_tests lt
    LEFT JOIN patients pat ON lt.patient_id = pat.id
    LEFT JOIN users doc ON lt.doctor_id = doc.id
    LEFT JOIN users tech ON lt.lab_technician_id = tech.id
    LEFT JOIN users recv ON lt.performed_by = recv.id
    LEFT JOIN branches b ON lt.branch_id = b.id
    LEFT JOIN visits v ON lt.visit_id = v.id
    WHERE lt.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$test_id]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Lab test fetch error: " . $e->getMessage());
}

if (!$test) {
    header('Location: lab_tests.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// FIND RELATED BILL ITEM & BILL (if paid)
// ================================================================
$related_bill = null;
$related_bill_item = null;
$is_paid = in_array($test['status'], ['completed', 'paid']);

try {
    // Tafuta bill_item inayohusiana na lab test hii
    // Tunatumia: item_type = 'lab_test', reference_id = test_id, au item_name = test_name
    $sql = "SELECT bi.*, b.bill_number, b.total_amount, b.subtotal, b.paid_amount, b.balance,
                   b.total_discount, b.discount_amount, b.premium_amount, b.status as bill_status
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE bi.item_type = 'lab_test'
            AND (
                (bi.reference_id = ? AND bi.reference_type = 'lab_test')
                OR bi.item_name = ?
            )
            AND bi.visit_id = ?
            ORDER BY bi.id DESC
            LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$test_id, $test['test_name'], $test['visit_id']]);
    $related_bill_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($related_bill_item) {
        $related_bill = [
            'id' => $related_bill_item['bill_id'],
            'bill_number' => $related_bill_item['bill_number'],
            'total_amount' => $related_bill_item['total_amount'],
            'subtotal' => $related_bill_item['subtotal'],
            'paid_amount' => $related_bill_item['paid_amount'],
            'balance' => $related_bill_item['balance'],
            'total_discount' => $related_bill_item['total_discount'],
            'discount_amount' => $related_bill_item['discount_amount'],
            'premium_amount' => $related_bill_item['premium_amount'],
            'status' => $related_bill_item['bill_status'],
        ];
    }
} catch (Exception $e) {
    error_log("Related bill fetch error: " . $e->getMessage());
}

// ================================================================
// HANDLE DELETE
// ================================================================
$alert_message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_lab_test') {
    try {
        $db->beginTransaction();
        
        $delete_type = $_POST['delete_type'] ?? 'unpaid';
        $log_details = "Deleted lab test: " . ($test['test_name'] ?? 'N/A') . " (ID: $test_id)";
        
        // ============================================================
        // KAMA NI PAID → Futa bill_item NA update bill
        // ============================================================
        if ($delete_type === 'paid' && $related_bill_item && $related_bill) {
            $bill_item_id = $related_bill_item['id'];
            $bill_id = $related_bill['id'];
            $item_total = (float)($related_bill_item['total_price'] ?? 0);
            $item_discount = (float)($related_bill_item['discount_amount'] ?? 0);
            $item_final = $item_total - $item_discount;
            
            // 1. Futa bill_item
            $db->prepare("DELETE FROM bill_items WHERE id = ?")->execute([$bill_item_id]);
            
            // 2. Recalculate bill totals
            // Pata items zilizobaki
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
            
            // Chukua discounts za bill
            $bill_discount = (float)($related_bill['discount_amount'] ?? 0);
            $premium = (float)($related_bill['premium_amount'] ?? 0);
            
            // Total discount = items discount + bill discount
            $new_total_discount = $new_items_discount + $bill_discount;
            
            // ✅ FORMULA: Subtotal - Discount + Premium
            $new_total_amount = $new_subtotal - $new_total_discount + $premium;
            if ($new_total_amount < 0) $new_total_amount = 0;
            
            // Update balance
            $paid = (float)($related_bill['paid_amount'] ?? 0);
            $new_balance = $new_total_amount - $paid;
            if ($new_balance < 0) $new_balance = 0;
            
            // Determine new status
            $new_status = 'pending';
            if ($new_balance <= 0 && $new_total_amount > 0) {
                $new_status = 'paid';
            } elseif ($paid > 0 && $new_balance > 0) {
                $new_status = 'partial';
            }
            
            // Kama hakuna items zilizobaki
            $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM bill_items WHERE bill_id = ? AND status != 'cancelled'");
            $stmt->execute([$bill_id]);
            $remaining_items = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            
            if ($remaining_items === 0) {
                // Futa bill nzima
                $db->prepare("DELETE FROM payments WHERE bill_id = ?")->execute([$bill_id]);
                $db->prepare("DELETE FROM bills WHERE id = ?")->execute([$bill_id]);
                $log_details .= " | Bill " . $related_bill['bill_number'] . " deleted (no items left)";
            } else {
                // Update bill
                $sql_update = "UPDATE bills SET 
                    subtotal = ?,
                    total_discount = ?,
                    total_amount = ?,
                    balance = ?,
                    status = ?,
                    updated_at = NOW()
                    WHERE id = ?";
                $db->prepare($sql_update)->execute([
                    $new_subtotal,
                    $new_total_discount,
                    $new_total_amount,
                    $new_balance,
                    $new_status,
                    $bill_id
                ]);
                $log_details .= " | Bill " . $related_bill['bill_number'] . " updated: " 
                              . $currency . " " . number_format($related_bill['total_amount'], 0) 
                              . " → " . $currency . " " . number_format($new_total_amount, 0);
            }
        }
        
        // ============================================================
        // Futa lab test yenyewe
        // ============================================================
        $db->prepare("DELETE FROM lab_tests WHERE id = ?")->execute([$test_id]);
        
        // ============================================================
        // Log activity
        // ============================================================
        try {
            $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) 
                          VALUES (?, ?, 'delete_lab_test', ?, ?, NOW())")
               ->execute([
                   $user_id,
                   $selected_branch_id !== 'all' ? (int)$selected_branch_id : (int)($test['branch_id'] ?? 1),
                   $log_details,
                   $_SERVER['REMOTE_ADDR'] ?? 'unknown'
               ]);
        } catch (Exception $e) {}
        
        $db->commit();
        
        $alert_message = "Lab test deleted successfully!";
        $alert_type = 'success';
        
        // Redirect baada ya 2s
        header("refresh:2;url=lab_tests.php?branch=" . $selected_branch_id);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $alert_message = "Error deleting lab test: " . $e->getMessage();
        $alert_type = 'error';
    }
}

// ================================================================
// HELPERS
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    try {
        return (new DateTime($dob))->diff(new DateTime('today'))->y . ' yrs';
    } catch (Exception $e) { return 'N/A'; }
}

function getLabStatusBadge($status) {
    $map = [
        'pending' => ['class' => 'warning', 'icon' => '⏳', 'label' => 'Pending'],
        'in_progress' => ['class' => 'info', 'icon' => '🔄', 'label' => 'In Progress'],
        'completed' => ['class' => 'success', 'icon' => '✅', 'label' => 'Completed'],
        'cancelled' => ['class' => 'danger', 'icon' => '❌', 'label' => 'Cancelled']
    ];
    return $map[$status] ?? ['class' => 'warning', 'icon' => '❓', 'label' => ucfirst($status)];
}

$status_info = getLabStatusBadge($test['status']);
$patient_age = calculateAge($test['date_of_birth']);

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
    <title>View Lab Test - <?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></title>
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
    --danger-dark: #B91C1C;
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

.money-number, .money-cell, .font-mono, .mono {
    font-family: var(--font-mono) !important;
    font-feature-settings: 'tnum';
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

/* ALERT */
.alert {
    padding: 12px 18px;
    border-radius: 12px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    font-size: 0.82rem;
    animation: slideDown 0.4s ease;
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
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 18px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    box-shadow: 0 6px 20px rgba(11, 94, 215, 0.25);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 350px; height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.page-header .page-title {
    color: white;
    font-size: 1.4rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    letter-spacing: -0.02em;
}

.page-header .page-title i { font-size: 1.5rem; color: #93C5FD; }

.page-header .page-subtitle {
    color: rgba(255,255,255,0.85);
    font-size: 0.78rem;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 4px;
    position: relative;
    z-index: 1;
}

.branch-tag {
    background: rgba(255,255,255,0.15);
    color: white;
    padding: 3px 10px;
    border-radius: 16px;
    font-size: 0.65rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.1);
}

.btn-header {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1px solid rgba(255,255,255,0.25);
    padding: 8px 14px;
    border-radius: 9px;
    font-weight: 600;
    font-size: 0.75rem;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    backdrop-filter: blur(4px);
    position: relative;
    z-index: 1;
    cursor: pointer;
}

.btn-header:hover {
    background: rgba(255,255,255,0.28);
    transform: translateY(-2px);
}

.btn-header.danger {
    background: rgba(220, 38, 38, 0.25);
    border-color: rgba(220, 38, 38, 0.5);
}

.btn-header.danger:hover {
    background: rgba(220, 38, 38, 0.5);
}

/* STATUS BANNER */
.status-banner {
    border-radius: 14px;
    padding: 18px 24px;
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-md);
}

.status-banner.pending { background: linear-gradient(135deg, #D97706, #B45309); color: white; }
.status-banner.in_progress { background: linear-gradient(135deg, #0891B2, #0E7490); color: white; }
.status-banner.completed { background: linear-gradient(135deg, #059669, #047857); color: white; }
.status-banner.cancelled { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; }

.status-banner::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 300px; height: 300px;
    background: rgba(255,255,255,0.08);
    border-radius: 50%;
    pointer-events: none;
}

.status-banner .status-left {
    display: flex;
    align-items: center;
    gap: 16px;
    position: relative;
    z-index: 1;
}

.status-banner .status-icon {
    width: 58px;
    height: 58px;
    border-radius: 16px;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    border: 2px solid rgba(255,255,255,0.3);
    backdrop-filter: blur(4px);
}

.status-banner .status-label {
    font-size: 1.35rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    line-height: 1.1;
}

.status-banner .status-sub {
    font-size: 0.75rem;
    opacity: 0.9;
    font-weight: 500;
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.status-banner .status-amount {
    text-align: right;
    position: relative;
    z-index: 1;
}

.status-banner .status-amount .label {
    font-size: 0.65rem;
    opacity: 0.8;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
    margin-bottom: 4px;
}

.status-banner .status-amount .value {
    font-size: 2rem;
    font-weight: 900;
    font-family: var(--font-mono);
    letter-spacing: -0.03em;
    line-height: 1;
}

.status-banner .status-amount .currency-prefix {
    font-size: 1rem;
    opacity: 0.85;
    font-family: var(--font-primary);
    font-weight: 700;
}

/* GRID */
.view-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 18px;
}

.info-card {
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px solid var(--border-color);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
}

.info-card:hover {
    box-shadow: var(--shadow-md);
    border-color: var(--primary);
}

.info-card .card-header {
    padding: 14px 18px;
    background: var(--primary-bg);
    border-bottom: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 0.85rem;
    font-weight: 800;
    color: var(--text-primary);
}

.info-card .card-header i { color: var(--primary); font-size: 0.95rem; }

.info-card .card-body { padding: 18px 20px; }

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.82rem;
}

.info-row:last-child { border-bottom: none; padding-bottom: 0; }
.info-row:first-child { padding-top: 0; }

.info-row .label {
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 0.72rem;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    min-width: 110px;
}

.info-row .label i { font-size: 0.7rem; opacity: 0.8; color: var(--primary); }

.info-row .value {
    color: var(--text-primary);
    font-weight: 700;
    text-align: right;
    word-break: break-word;
    flex: 1;
}

.info-row .value.mono {
    font-family: var(--font-mono);
    font-size: 0.8rem;
    letter-spacing: -0.02em;
}

.info-row .value.amount {
    font-family: var(--font-mono);
    font-size: 1.05rem;
    font-weight: 900;
    color: var(--primary);
}

/* STATUS BADGE */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 18px;
    font-size: 0.62rem;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
}

.status-badge.warning { background: var(--warning-bg); color: var(--warning); }
.status-badge.success { background: var(--success-bg); color: var(--success); }
.status-badge.danger { background: var(--danger-bg); color: var(--danger); }
.status-badge.info { background: var(--primary-bg); color: var(--primary); }
.status-badge.purple { background: var(--purple-bg); color: var(--purple); }
.status-badge.cyan { background: var(--cyan-bg); color: var(--cyan); }

/* PATIENT PROFILE */
.patient-profile {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 18px;
    background: linear-gradient(135deg, var(--primary-bg), var(--primary-soft));
    border-bottom: 2px solid var(--border-color);
}

.patient-avatar {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: linear-gradient(135deg, #0B5ED7, #3B82F6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 900;
    text-transform: uppercase;
    flex-shrink: 0;
    box-shadow: 0 6px 16px rgba(11, 94, 215, 0.3);
    border: 3px solid var(--bg-card);
}

.patient-name {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--text-primary);
    letter-spacing: -0.02em;
    line-height: 1.2;
    margin-bottom: 4px;
}

.patient-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.patient-meta-item {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--bg-card);
    padding: 3px 10px;
    border-radius: 12px;
    border: 1px solid var(--border-color);
}

.patient-meta-item i { color: var(--primary); font-size: 0.65rem; }

/* RESULT BOX */
.result-box {
    border-radius: 14px;
    border: 2px solid var(--border-color);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    margin-bottom: 18px;
    background: var(--bg-card);
}

.result-box .result-header {
    padding: 14px 20px;
    background: linear-gradient(135deg, #059669, #047857);
    color: white;
    font-size: 0.9rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
}

.result-box .result-header i { color: #A7F3D0; }

.result-box .result-body {
    padding: 20px 24px;
}

.result-value {
    font-family: var(--font-mono);
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--success);
    letter-spacing: -0.02em;
    padding: 16px;
    background: var(--success-bg);
    border-radius: 10px;
    border-left: 4px solid var(--success);
    margin-bottom: 12px;
    word-break: break-word;
}

.result-value.pending {
    color: var(--text-secondary);
    background: var(--border-color);
    border-left-color: var(--text-secondary);
    font-style: italic;
    font-size: 1rem;
    font-weight: 500;
}

.result-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-secondary);
    font-weight: 800;
    margin-bottom: 6px;
}

/* RELATED BILL BOX */
.bill-box {
    border-radius: 14px;
    border: 2px solid var(--purple);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    margin-bottom: 18px;
    background: var(--bg-card);
}

.bill-box .bill-header {
    padding: 14px 20px;
    background: linear-gradient(135deg, #7C3AED, #6D28D9);
    color: white;
    font-size: 0.9rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
}

.bill-box .bill-header i { color: #C4B5FD; }

.bill-box .bill-body { padding: 16px 20px; }

.bill-totals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
}

.bill-total-item {
    background: var(--bg-body);
    border-radius: 10px;
    padding: 12px 14px;
    border: 1px solid var(--border-color);
    text-align: center;
}

.bill-total-item .bt-label {
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-secondary);
    font-weight: 800;
    margin-bottom: 4px;
}

.bill-total-item .bt-value {
    font-family: var(--font-mono);
    font-size: 0.95rem;
    font-weight: 900;
    color: var(--text-primary);
    letter-spacing: -0.02em;
}

.bill-total-item.total .bt-value { color: var(--primary); }
.bill-total-item.paid .bt-value { color: var(--success); }
.bill-total-item.balance .bt-value { color: var(--danger); }

/* INFO NOTICE */
.info-notice {
    background: var(--warning-bg);
    border-left: 4px solid var(--warning);
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 0.78rem;
    color: #78350F;
    font-weight: 500;
}

.info-notice i { color: var(--warning); font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }
[data-theme="dark"] .info-notice { color: #FEF3C7; }

/* ACTION BAR */
.action-bar {
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px solid var(--border-color);
    padding: 16px 20px;
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    box-shadow: var(--shadow-sm);
}

.action-bar .action-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.action-bar .action-info .info-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: var(--primary-bg);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.action-bar .action-info .info-text .info-title {
    font-size: 0.85rem;
    font-weight: 800;
    color: var(--text-primary);
}

.action-bar .action-info .info-text .info-sub {
    font-size: 0.7rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.action-bar .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }

.btn {
    padding: 10px 18px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.8rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    text-decoration: none;
    white-space: nowrap;
}

.btn:hover { transform: translateY(-2px); }

.btn-primary {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}

.btn-primary:hover { box-shadow: 0 6px 20px rgba(11, 94, 215, 0.5); }

.btn-secondary {
    background: var(--bg-card);
    color: var(--text-primary);
    border: 2px solid var(--border-color);
}

.btn-secondary:hover { border-color: var(--primary); color: var(--primary); }

.btn-edit {
    background: linear-gradient(135deg, #F59E0B, #D97706);
    color: white;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.btn-edit:hover { box-shadow: 0 6px 20px rgba(245, 158, 11, 0.5); }

.btn-delete {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    color: white;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}

.btn-delete:hover { box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5); }

/* MODAL */
.modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(6px);
    z-index: 99999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-overlay.active { display: flex; }

.modal-box {
    background: var(--bg-card);
    border-radius: 20px;
    max-width: 480px;
    width: 100%;
    padding: 30px;
    box-shadow: var(--shadow-xl);
    animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    text-align: center;
    max-height: 90vh;
    overflow-y: auto;
}

@keyframes modalPop {
    0% { opacity: 0; transform: scale(0.8) translateY(20px); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}

.modal-icon {
    width: 68px;
    height: 68px;
    border-radius: 50%;
    background: var(--danger-bg);
    color: var(--danger);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin: 0 auto 16px;
    animation: iconPulse 1.5s infinite;
}

@keyframes iconPulse {
    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); }
    50% { transform: scale(1.05); box-shadow: 0 0 0 12px rgba(220, 38, 38, 0); }
}

.modal-title {
    font-size: 1.2rem;
    font-weight: 800;
    margin-bottom: 8px;
    color: var(--text-primary);
}

.modal-text {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-bottom: 22px;
    line-height: 1.7;
}

.modal-text strong {
    color: var(--primary);
    background: var(--primary-bg);
    padding: 2px 8px;
    border-radius: 6px;
    font-family: var(--font-mono);
    font-weight: 700;
}

.modal-warning {
    color: var(--danger);
    font-weight: 700;
    display: block;
    margin-top: 6px;
    font-size: 0.78rem;
}

.modal-warning-box {
    background: var(--danger-bg);
    border: 2px solid var(--danger);
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 18px;
    text-align: left;
    font-size: 0.78rem;
    color: var(--danger);
    font-weight: 600;
    line-height: 1.6;
}

.modal-warning-box .mb-title {
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
    font-size: 0.82rem;
}

.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

.modal-btn {
    padding: 10px 24px;
    border-radius: 11px;
    font-weight: 700;
    font-size: 0.82rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.modal-btn.cancel { background: var(--border-color); color: var(--text-primary); }
.modal-btn.cancel:hover { background: #CBD5E1; transform: translateY(-2px); }

.modal-btn.danger { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; }
.modal-btn.danger:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5); }

/* RESPONSIVE */
@media (max-width: 1024px) {
    .view-grid { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .status-banner { padding: 14px 16px; }
    .status-banner .status-amount .value { font-size: 1.5rem; }
    .status-banner .status-icon { width: 48px; height: 48px; font-size: 1.3rem; }
    .status-banner .status-label { font-size: 1.1rem; }
    .action-bar { padding: 12px 14px; }
    .btn { padding: 8px 14px; font-size: 0.75rem; }
    .info-row .label { min-width: 90px; font-size: 0.68rem; }
}

@media print {
    .action-bar, .btn-header { display: none !important; }
    .page-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .status-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
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
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                Lab Test Details
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-microscope"></i>
                <strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($test['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-hashtag"></i> Test #<?= $test['id'] ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="edit_lab_test.php?id=<?= $test_id ?>&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-edit"></i> Edit
            </a>
            <?php if ($is_paid): ?>
                <button onclick="openDeleteModal()" class="btn-header danger">
                    <i class="fas fa-trash"></i> Delete (PAID)
                </button>
            <?php else: ?>
                <button onclick="openDeleteModal()" class="btn-header danger">
                    <i class="fas fa-trash"></i> Delete
                </button>
            <?php endif; ?>
            <a href="lab_tests.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- STATUS BANNER -->
    <div class="status-banner <?= htmlspecialchars($test['status'] ?? 'pending') ?>">
        <div class="status-left">
            <div class="status-icon">
                <i class="fas fa-<?= 
                    $test['status'] === 'completed' ? 'check-circle' : 
                    ($test['status'] === 'in_progress' ? 'spinner' : 
                    ($test['status'] === 'cancelled' ? 'times-circle' : 'clock')) 
                ?>"></i>
            </div>
            <div class="status-info">
                <div class="status-label">
                    <?= htmlspecialchars($status_info['label']) ?>
                    <?php if (!empty($test['test_type'])): ?>
                        <span style="font-size:0.7rem;background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-weight:700;">
                            <?= htmlspecialchars($test['test_type']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="status-sub">
                    <i class="fas fa-user-nurse"></i>
                    <?= !empty($test['lab_technician_name']) ? htmlspecialchars($test['lab_technician_name']) : 'Not assigned' ?>
                    <span style="opacity:0.6;">•</span>
                    <i class="fas fa-calendar"></i>
                    <?= date('d M Y, H:i', strtotime($test['test_date'] ?? $test['created_at'])) ?>
                </div>
            </div>
        </div>
        <div class="status-amount">
            <div class="label">Test Price</div>
            <div class="value">
                <span class="currency-prefix"><?= $currency ?></span>
                <?= number_format($test['test_price'] ?? 0, 0) ?>
            </div>
        </div>
    </div>

    <!-- ✅ PAID NOTICE -->
    <?php if ($is_paid && $related_bill): ?>
    <div class="info-notice">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>This lab test is PAID.</strong> 
            It is linked to bill <strong><?= htmlspecialchars($related_bill['bill_number']) ?></strong>. 
            Deleting this test will also <strong>remove the bill item</strong> and 
            <strong>recalculate the bill totals</strong> (subtotal, discount, total amount, balance).
        </div>
    </div>
    <?php elseif ($is_paid && !$related_bill): ?>
    <div class="info-notice">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>This lab test is PAID</strong> but no related bill item was found. 
            Deleting will only remove the lab test.
        </div>
    </div>
    <?php endif; ?>

    <!-- MAIN GRID -->
    <div class="view-grid">
        
        <!-- PATIENT INFO -->
        <div class="info-card">
            <div class="card-header">
                <span><i class="fas fa-user-injured"></i> Patient Information</span>
            </div>
            
            <?php if (!empty($test['patient_name'])): ?>
            <div class="patient-profile">
                <div class="patient-avatar">
                    <?= strtoupper(substr($test['patient_name'], 0, 1)) ?>
                </div>
                <div>
                    <div class="patient-name"><?= htmlspecialchars($test['patient_name']) ?></div>
                    <div class="patient-meta">
                        <?php if (!empty($test['patient_number'])): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-id-card"></i> <?= htmlspecialchars($test['patient_number']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($test['patient_gender'])): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-<?= strtolower($test['patient_gender']) === 'female' ? 'venus' : 'mars' ?>"></i>
                                <?= htmlspecialchars($test['patient_gender']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($patient_age !== 'N/A'): ?>
                            <span class="patient-meta-item">
                                <i class="fas fa-birthday-cake"></i> <?= $patient_age ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="value mono"><?= htmlspecialchars($test['patient_phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-map-marker-alt"></i> Address</span>
                    <span class="value"><?= htmlspecialchars($test['patient_address'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-tint"></i> Blood Group</span>
                    <span class="value"><?= htmlspecialchars($test['patient_blood'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-exclamation-triangle"></i> Allergies</span>
                    <span class="value" style="color:var(--danger);"><?= htmlspecialchars($test['patient_allergies'] ?? 'None') ?></span>
                </div>
            </div>
        </div>

        <!-- TEST INFO -->
        <div class="info-card">
            <div class="card-header">
                <span><i class="fas fa-flask"></i> Test Information</span>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-microscope"></i> Test Name</span>
                    <span class="value"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-tag"></i> Test Type</span>
                    <span class="value">
                        <?php if (!empty($test['test_type'])): ?>
                            <span class="status-badge purple"><?= htmlspecialchars($test['test_type']) ?></span>
                        <?php else: ?>
                            <span style="color:var(--text-secondary);">—</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-vial"></i> Sample Type</span>
                    <span class="value"><?= htmlspecialchars($test['sample_type'] ?? '—') ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-calendar"></i> Test Date</span>
                    <span class="value"><?= date('d M Y, H:i', strtotime($test['test_date'] ?? $test['created_at'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-money-bill-wave"></i> Price</span>
                    <span class="value amount">
                        <span style="font-size:0.72rem;color:var(--text-secondary);font-family:var(--font-primary);"><?= $currency ?></span>
                        <?= number_format($test['test_price'] ?? 0, 0) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-flag"></i> Status</span>
                    <span class="value">
                        <span class="status-badge <?= $status_info['class'] ?>">
                            <?= $status_info['icon'] ?> <?= $status_info['label'] ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>
        
    </div>

    <!-- VISIT INFO -->
    <div class="info-card" style="margin-bottom:18px;">
        <div class="card-header">
            <span><i class="fas fa-calendar-check"></i> Visit Information</span>
        </div>
        <div class="card-body">
            <div class="info-row">
                <span class="label"><i class="fas fa-hashtag"></i> Visit Number</span>
                <span class="value mono" style="color:var(--primary);"><?= htmlspecialchars($test['visit_number'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="label"><i class="fas fa-calendar-day"></i> Visit Date</span>
                <span class="value"><?= !empty($test['visit_date']) ? date('d M Y, H:i', strtotime($test['visit_date'])) : 'N/A' ?></span>
            </div>
            <div class="info-row">
                <span class="label"><i class="fas fa-user-md"></i> Doctor</span>
                <span class="value"><?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="label"><i class="fas fa-stethoscope"></i> Diagnosis</span>
                <span class="value"><?= htmlspecialchars($test['diagnosis'] ?? 'N/A') ?></span>
            </div>
        </div>
    </div>

    <!-- RESULT -->
    <div class="result-box">
        <div class="result-header">
            <span><i class="fas fa-file-medical"></i> Test Result</span>
            <?php if (!empty($test['lab_technician_name'])): ?>
                <span style="font-size:0.7rem;background:rgba(255,255,255,0.2);padding:3px 10px;border-radius:10px;">
                    <i class="fas fa-user-nurse"></i> <?= htmlspecialchars($test['lab_technician_name']) ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="result-body">
            <?php if (!empty($test['results'])): ?>
                <div class="result-label">Result</div>
                <div class="result-value"><?= htmlspecialchars($test['results']) ?></div>
                
                <?php if (!empty($test['formatted_result'])): ?>
                    <div class="result-label">Formatted Result</div>
                    <div class="result-value" style="font-size:1rem;"><?= htmlspecialchars($test['formatted_result']) ?></div>
                <?php endif; ?>
                
                <?php if (!empty($test['reference_range'])): ?>
                    <div class="result-label">Reference Range</div>
                    <div style="font-family:var(--font-mono);font-size:0.85rem;font-weight:600;color:var(--text-secondary);padding:8px 0;">
                        <?= htmlspecialchars($test['reference_range']) ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="result-value pending">
                    <i class="fas fa-hourglass-half"></i> Result not yet available
                </div>
            <?php endif; ?>
            
            <?php if (!empty($test['notes'])): ?>
                <div class="result-label" style="margin-top:14px;">Notes</div>
                <div style="font-size:0.82rem;color:var(--text-primary);line-height:1.6;font-weight:500;">
                    <?= nl2br(htmlspecialchars($test['notes'])) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ✅ RELATED BILL (kama test ni paid) -->
    <?php if ($is_paid && $related_bill): ?>
    <div class="bill-box">
        <div class="bill-header">
            <span><i class="fas fa-file-invoice"></i> Related Bill: <?= htmlspecialchars($related_bill['bill_number']) ?></span>
            <span style="font-size:0.7rem;background:rgba(255,255,255,0.2);padding:3px 10px;border-radius:10px;">
                <i class="fas fa-check-circle"></i> <?= strtoupper($related_bill['status']) ?>
            </span>
        </div>
        <div class="bill-body">
            <div class="bill-totals-grid">
                <div class="bill-total-item">
                    <div class="bt-label">Subtotal</div>
                    <div class="bt-value"><?= $currency ?> <?= number_format($related_bill['subtotal'], 0) ?></div>
                </div>
                <div class="bill-total-item">
                    <div class="bt-label">Discount</div>
                    <div class="bt-value" style="color:var(--danger);">
                        - <?= $currency ?> <?= number_format($related_bill['total_discount'], 0) ?>
                    </div>
                </div>
                <?php if ($related_bill['premium_amount'] > 0): ?>
                <div class="bill-total-item">
                    <div class="bt-label">Premium</div>
                    <div class="bt-value" style="color:var(--purple);">
                        + <?= $currency ?> <?= number_format($related_bill['premium_amount'], 0) ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="bill-total-item total">
                    <div class="bt-label">Total Amount</div>
                    <div class="bt-value"><?= $currency ?> <?= number_format($related_bill['total_amount'], 0) ?></div>
                </div>
                <div class="bill-total-item paid">
                    <div class="bt-label">Paid</div>
                    <div class="bt-value"><?= $currency ?> <?= number_format($related_bill['paid_amount'], 0) ?></div>
                </div>
                <div class="bill-total-item balance">
                    <div class="bt-label">Balance</div>
                    <div class="bt-value"><?= $currency ?> <?= number_format($related_bill['balance'], 0) ?></div>
                </div>
            </div>
            
            <div style="margin-top:14px;padding-top:12px;border-top:1px dashed var(--border-color);font-size:0.75rem;color:var(--text-secondary);font-weight:600;">
                <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                This test contributes <strong style="color:var(--primary);font-family:var(--font-mono);"><?= $currency ?> <?= number_format($related_bill_item['total_price'] ?? 0, 0) ?></strong> to this bill.
                <?php if (($related_bill_item['discount_amount'] ?? 0) > 0): ?>
                    Discount: <strong style="color:var(--danger);font-family:var(--font-mono);">-<?= $currency ?> <?= number_format($related_bill_item['discount_amount'], 0) ?></strong>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ACTION BAR -->
    <div class="action-bar">
        <div class="action-info">
            <div class="info-icon">
                <i class="fas fa-flask"></i>
            </div>
            <div class="info-text">
                <div class="info-title">Lab Test Actions</div>
                <div class="info-sub">
                    <?php if ($is_paid): ?>
                        Paid test - Delete will update bill
                    <?php else: ?>
                        View, edit, or delete this lab test
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="action-buttons">
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="edit_lab_test.php?id=<?= $test_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-edit">
                <i class="fas fa-edit"></i> Edit Test
            </a>
            <button onclick="openDeleteModal()" class="btn btn-delete">
                <i class="fas fa-trash"></i> Delete
            </button>
            <a href="lab_tests.php?branch=<?= $selected_branch_id ?>" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

</main>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="modal-title">Delete Lab Test?</h3>
        <p class="modal-text">
            Are you sure you want to delete<br>
            <strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong>
        </p>
        
        <?php if ($is_paid && $related_bill): ?>
        <div class="modal-warning-box">
            <div class="mb-title">
                <i class="fas fa-exclamation-circle"></i>
                This test is PAID - Bill will be updated!
            </div>
            <ul style="margin:8px 0 0 20px;padding:0;font-weight:500;">
                <li>Bill item will be <strong>removed</strong></li>
                <li>Bill <strong><?= htmlspecialchars($related_bill['bill_number']) ?></strong> totals will be <strong>recalculated</strong></li>
                <li>Subtotal, discount, total amount, and balance will be <strong>updated</strong></li>
            </ul>
        </div>
        <?php endif; ?>
        
        <span class="modal-warning">
            <i class="fas fa-ban"></i> This action cannot be undone!
        </span>
        
        <form method="POST" id="deleteForm" style="margin-top:20px;">
            <input type="hidden" name="action" value="delete_lab_test">
            <input type="hidden" name="delete_type" value="<?= $is_paid ? 'paid' : 'unpaid' ?>">
            <div class="modal-actions">
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

<script>
function openDeleteModal() {
    document.getElementById('deleteModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDeleteModal();
});

console.log('%c🧪 View Lab Test', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Test: <?= htmlspecialchars($test['test_name'] ?? 'N/A') ?>', 'font-size:12px;color:#34D399;');
console.log('%c✅ Patient: <?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?>', 'font-size:12px;color:#34D399;');
console.log('%c💰 Price: <?= $currency ?> <?= number_format($test['test_price'] ?? 0, 0) ?>', 'font-size:12px;color:#0B5ED7;font-weight:bold;');
<?php if ($is_paid && $related_bill): ?>
console.log('%c⚠️ PAID - Bill <?= htmlspecialchars($related_bill['bill_number']) ?> will be updated on delete', 'font-size:12px;color:#DC2626;font-weight:bold;');
<?php endif; ?>
</script>

</body>
</html>