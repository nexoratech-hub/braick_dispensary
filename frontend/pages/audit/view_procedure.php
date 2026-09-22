<?php
// ================================================================
// FILE: frontend/pages/audit/view_procedure.php
// AUDIT - VIEW PROCEDURE / EQUIPMENT DETAILS (V2 - CLEAN)
// ✅ Branch ya aliye login TU
// ✅ ONDOA VIEW ONLY notice na badges
// ✅ Inatumia audit_header.php + audit_sidebar.php
// ✅ AUDIT ROLE TU
// ✅ HAKUNA Edit/Delete buttons
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ AUDIT ROLE TU
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'audit') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Audit User';
$user_role = $_SESSION['role'] ?? 'audit';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_audit = true;

// ================================================================
// PARAMETERS
// ================================================================
$reference_id = (int)($_GET['id'] ?? 0);
$bill_item_id = (int)($_GET['bill_item_id'] ?? 0);
$item_type = $_GET['type'] ?? 'procedure';

// ✅ AUDIT anaona branch yake TU
$selected_branch_id = (int)$user_branch_id;

if ($reference_id <= 0 && $bill_item_id <= 0) {
    header('Location: other_services.php?tab=procedures');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';

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
// HELPER: Log Activity
// ================================================================
function logActivity($db, $user_id, $branch_id, $patient_id, $action, $details) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $stmt = $db->prepare("
            INSERT INTO activity_logs 
            (user_id, branch_id, patient_id, action, details, ip_address, user_agent, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$user_id, $branch_id, $patient_id, $action, $details, $ip, $ua]);
    } catch (Exception $e) {
        error_log("Activity log failed: " . $e->getMessage());
    }
}

// ================================================================
// ✅ FETCH ITEM - LAZIMA branch ya mtumiaji
// ================================================================
$item = null;
try {
    if ($bill_item_id > 0) {
        $stmt = $db->prepare("
            SELECT bi.*,
                   bi.id as bill_item_row_id,
                   bi.reference_id,
                   bi.item_type,
                   bi.item_name as procedure_name,
                   bi.unit_price as procedure_price,
                   bi.quantity,
                   bi.total_price as procedure_total,
                   bi.discount_amount as item_discount,
                   bi.status as item_status,
                   bi.created_at as item_created_at,
                   bi.description,
                   b.id as bill_id, b.bill_number, b.status as bill_status,
                   b.subtotal as bill_subtotal, b.total_amount as bill_total,
                   b.paid_amount as bill_paid, b.balance as bill_balance,
                   b.total_discount as bill_discount,
                   b.premium_amount as bill_premium, b.payment_method,
                   b.created_at as bill_created_at,
                   pat.id as patient_db_id, pat.full_name as patient_name, 
                   pat.patient_id as patient_number, pat.phone as patient_phone,
                   pat.gender as patient_gender, pat.date_of_birth, 
                   pat.address as patient_address, pat.blood_group, pat.allergies,
                   v.id as visit_db_id, v.visit_number, v.visit_date, v.status as visit_status,
                   v.visit_type, v.diagnosis, v.symptoms, v.consultation_fee,
                   doc.full_name as doctor_name, doc.specialty as doctor_specialty,
                   rec.full_name as receptionist_name,
                   br.name as branch_name,
                   pc.procedure_code, pc.category as procedure_category,
                   pc.description as procedure_description,
                   me.batch_number as equipment_batch,
                   me.supplier as equipment_supplier,
                   me.expiry_date as equipment_expiry
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            LEFT JOIN patients pat ON bi.patient_id = pat.id
            LEFT JOIN visits v ON b.visit_id = v.id
            LEFT JOIN users doc ON v.doctor_id = doc.id
            LEFT JOIN users rec ON v.receptionist_id = rec.id
            LEFT JOIN branches br ON bi.branch_id = br.id
            LEFT JOIN procedures_catalog pc ON (bi.item_type = 'procedure' AND bi.reference_id = pc.id)
            LEFT JOIN medical_equipment me ON (bi.item_type = 'equipment' AND bi.reference_id = me.id)
            WHERE bi.id = ? AND bi.branch_id = ?
            LIMIT 1
        ");
        $stmt->execute([$bill_item_id, $user_branch_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$item && $reference_id > 0) {
        $stmt = $db->prepare("
            SELECT bi.*,
                   bi.id as bill_item_row_id,
                   bi.reference_id,
                   bi.item_type,
                   bi.item_name as procedure_name,
                   bi.unit_price as procedure_price,
                   bi.quantity,
                   bi.total_price as procedure_total,
                   bi.discount_amount as item_discount,
                   bi.status as item_status,
                   bi.created_at as item_created_at,
                   bi.description,
                   b.id as bill_id, b.bill_number, b.status as bill_status,
                   b.subtotal as bill_subtotal, b.total_amount as bill_total,
                   b.paid_amount as bill_paid, b.balance as bill_balance,
                   b.total_discount as bill_discount,
                   b.premium_amount as bill_premium, b.payment_method,
                   b.created_at as bill_created_at,
                   pat.id as patient_db_id, pat.full_name as patient_name, 
                   pat.patient_id as patient_number, pat.phone as patient_phone,
                   pat.gender as patient_gender, pat.date_of_birth, 
                   pat.address as patient_address, pat.blood_group, pat.allergies,
                   v.id as visit_db_id, v.visit_number, v.visit_date, v.status as visit_status,
                   v.visit_type, v.diagnosis, v.symptoms, v.consultation_fee,
                   doc.full_name as doctor_name, doc.specialty as doctor_specialty,
                   rec.full_name as receptionist_name,
                   br.name as branch_name,
                   pc.procedure_code, pc.category as procedure_category,
                   pc.description as procedure_description,
                   me.batch_number as equipment_batch,
                   me.supplier as equipment_supplier,
                   me.expiry_date as equipment_expiry
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            LEFT JOIN patients pat ON bi.patient_id = pat.id
            LEFT JOIN visits v ON b.visit_id = v.id
            LEFT JOIN users doc ON v.doctor_id = doc.id
            LEFT JOIN users rec ON v.receptionist_id = rec.id
            LEFT JOIN branches br ON bi.branch_id = br.id
            LEFT JOIN procedures_catalog pc ON (bi.item_type = 'procedure' AND bi.reference_id = pc.id)
            LEFT JOIN medical_equipment me ON (bi.item_type = 'equipment' AND bi.reference_id = me.id)
            WHERE bi.reference_id = ?
              AND bi.item_type IN ('procedure', 'equipment')
              AND bi.branch_id = ?
            ORDER BY bi.id DESC
            LIMIT 1
        ");
        $stmt->execute([$reference_id, $user_branch_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$item && $reference_id > 0) {
        $stmt = $db->prepare("
            SELECT p.*,
                   NULL as bill_item_row_id,
                   p.id as reference_id,
                   'procedure' as item_type,
                   p.procedure_name,
                   p.procedure_price,
                   1 as quantity,
                   p.procedure_price as procedure_total,
                   0 as item_discount,
                   p.status as item_status,
                   p.created_at as item_created_at,
                   p.notes as description,
                   NULL as bill_id, NULL as bill_number, NULL as bill_status,
                   NULL as bill_subtotal, NULL as bill_total,
                   NULL as bill_paid, NULL as bill_balance,
                   NULL as bill_discount, NULL as bill_premium, NULL as payment_method,
                   NULL as bill_created_at,
                   pat.id as patient_db_id, pat.full_name as patient_name, 
                   pat.patient_id as patient_number, pat.phone as patient_phone,
                   pat.gender as patient_gender, pat.date_of_birth, 
                   pat.address as patient_address, pat.blood_group, pat.allergies,
                   v.id as visit_db_id, v.visit_number, v.visit_date, v.status as visit_status,
                   v.visit_type, v.diagnosis, v.symptoms, v.consultation_fee,
                   doc.full_name as doctor_name, doc.specialty as doctor_specialty,
                   rec.full_name as receptionist_name,
                   br.name as branch_name,
                   p.procedure_code, p.category as procedure_category,
                   NULL as procedure_description,
                   NULL as equipment_batch, NULL as equipment_supplier, NULL as equipment_expiry
            FROM procedures p
            LEFT JOIN patients pat ON p.patient_id = pat.id
            LEFT JOIN visits v ON p.visit_id = v.id
            LEFT JOIN users doc ON p.doctor_id = doc.id
            LEFT JOIN users rec ON v.receptionist_id = rec.id
            LEFT JOIN branches br ON p.branch_id = br.id
            WHERE p.id = ? AND p.branch_id = ?
            LIMIT 1
        ");
        $stmt->execute([$reference_id, $user_branch_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Item fetch error: " . $e->getMessage());
}

if (!$item) {
    $_SESSION['error_message'] = "Procedure not found in your branch.";
    header('Location: other_services.php?tab=procedures');
    exit;
}

// Normalize
$item['procedure_name'] = $item['procedure_name'] ?? $item['item_name'] ?? 'N/A';
$item['procedure_price'] = $item['procedure_price'] ?? $item['unit_price'] ?? 0;
$item['item_status'] = $item['item_status'] ?? $item['status'] ?? 'pending';
$item['item_created_at'] = $item['item_created_at'] ?? $item['created_at'] ?? date('Y-m-d H:i:s');
$is_equipment = ($item['item_type'] ?? '') === 'equipment';
$item_label = $is_equipment ? 'Equipment' : 'Procedure';
$item_id = $item['reference_id'] ?? $item['id'] ?? 0;
$bill_item_row_id = $item['bill_item_row_id'] ?? 0;

// ================================================================
// ✅ FETCH PAYMENTS
// ================================================================
$payments = [];
if (!empty($item['bill_id']) && ($item['bill_paid'] ?? 0) > 0) {
    try {
        $stmt = $db->prepare("
            SELECT p.*, u.full_name as received_by_name, u.role as received_by_role
            FROM payments p
            INNER JOIN bills b ON p.bill_id = b.id
            LEFT JOIN users u ON p.received_by = u.id
            WHERE p.bill_id = ? AND b.branch_id = ?
            ORDER BY p.received_at DESC
        ");
        $stmt->execute([$item['bill_id'], $user_branch_id]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Payments fetch error: " . $e->getMessage());
    }
}

// ================================================================
// ✅ FETCH AUDIT LOGS
// ================================================================
$audit_logs = [];
try {
    $stmt = $db->prepare("
        SELECT al.*, u.full_name as performed_by_name, b.name as branch_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN branches b ON al.branch_id = b.id
        WHERE al.patient_id = ?
          AND al.branch_id = ?
          AND al.action IN ('procedure_created', 'procedure_updated', 'procedure_deleted', 'bill_item_deleted')
        ORDER BY al.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$item['patient_db_id'] ?? 0, $user_branch_id]);
    $audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Audit fetch error: " . $e->getMessage());
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

function getStatusBadge($status) {
    $map = [
        'pending' => ['class' => 'warning', 'icon' => '⏳', 'label' => 'Pending'],
        'in_progress' => ['class' => 'info', 'icon' => '🔄', 'label' => 'In Progress'],
        'completed' => ['class' => 'success', 'icon' => '✅', 'label' => 'Completed'],
        'paid' => ['class' => 'success', 'icon' => '✅', 'label' => 'Paid'],
        'partial' => ['class' => 'purple', 'icon' => '⚡', 'label' => 'Partial'],
        'cancelled' => ['class' => 'danger', 'icon' => '❌', 'label' => 'Cancelled'],
        'refunded' => ['class' => 'danger', 'icon' => '↩️', 'label' => 'Refunded'],
    ];
    return $map[$status] ?? ['class' => 'warning', 'icon' => '❓', 'label' => ucfirst($status ?: 'Unknown')];
}

function getInitials($name) {
    if (empty($name)) return 'NA';
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

$status_info = getStatusBadge($item['item_status']);
$patient_age = calculateAge($item['date_of_birth'] ?? '');
$bill_s = getStatusBadge($item['bill_status'] ?? 'pending');

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ✅ AUDIT HEADER + SIDEBAR
include_once __DIR__ . '/../../components/audit_header.php';
include_once __DIR__ . '/../../components/audit_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View <?= $item_label ?> - <?= htmlspecialchars($item['procedure_name']) ?></title>
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
* { font-family: var(--font-primary); -webkit-font-smoothing: antialiased; box-sizing: border-box; }
html, body { font-family: var(--font-primary); background: var(--bg-body); color: var(--text-primary); margin: 0; }
.money-number, .money-cell, .font-mono, .mono { font-family: var(--font-mono) !important; font-variant-numeric: tabular-nums; letter-spacing: -0.02em; }

.alert { padding: 12px 18px; border-radius: 12px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 0.82rem; animation: slideDown 0.4s ease; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.alert.success { background: var(--success-bg); color: var(--success); border-left: 4px solid var(--success); }
.alert.error { background: var(--danger-bg); color: var(--danger); border-left: 4px solid var(--danger); }
.alert.warning { background: var(--warning-bg); color: var(--warning); border-left: 4px solid var(--warning); }

.page-header { background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%); border-radius: 16px; padding: 20px 24px; margin-bottom: 18px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px; box-shadow: 0 6px 20px rgba(11, 94, 215, 0.25); position: relative; overflow: hidden; }
.page-header::before { content: ''; position: absolute; top: -50%; right: -20%; width: 350px; height: 350px; background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%); border-radius: 50%; pointer-events: none; }
.page-header .page-title { color: white; font-size: 1.4rem; font-weight: 800; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; position: relative; z-index: 1; letter-spacing: -0.02em; margin: 0; }
.page-header .page-title i { font-size: 1.5rem; color: #93C5FD; }
.page-header .page-subtitle { color: rgba(255,255,255,0.85); font-size: 0.78rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px; position: relative; z-index: 1; }
.branch-tag { background: rgba(255,255,255,0.15); color: white; padding: 3px 10px; border-radius: 16px; font-size: 0.65rem; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.1); }
.branch-tag.audit-tag { background: linear-gradient(135deg, #0EA5E9, #0284C7); font-weight: 800; }
.branch-tag.count-tag { background: linear-gradient(135deg, #10B981, #059669); font-weight: 700; }
.branch-tag.cyan-tag { background: linear-gradient(135deg, #06B6D4, #0891B2); font-weight: 700; }
.branch-tag.purple-tag { background: linear-gradient(135deg, #7C3AED, #6D28D9); font-weight: 700; }

.btn-header { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 8px 14px; border-radius: 9px; font-weight: 600; font-size: 0.75rem; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; backdrop-filter: blur(4px); position: relative; z-index: 1; cursor: pointer; }
.btn-header:hover { background: rgba(255,255,255,0.28); transform: translateY(-2px); color: white; }

.status-banner { border-radius: 14px; padding: 16px 22px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; position: relative; overflow: hidden; box-shadow: var(--shadow-md); }
.status-banner.pending { background: linear-gradient(135deg, #D97706, #B45309); color: white; }
.status-banner.in_progress { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; }
.status-banner.completed, .status-banner.paid { background: linear-gradient(135deg, #059669, #047857); color: white; }
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
.info-row .label { color: var(--text-secondary); font-weight: 600; font-size: 0.72rem; display: flex; align-items: center; gap: 6px; flex-shrink: 0; min-width: 120px; }
.info-row .label i { font-size: 0.7rem; opacity: 0.8; color: var(--primary); }
.info-row .value { color: var(--text-primary); font-weight: 700; text-align: right; word-break: break-word; flex: 1; }
.info-row .value.mono { font-family: var(--font-mono); font-size: 0.8rem; letter-spacing: -0.02em; }

.profile-header { display: flex; align-items: center; gap: 14px; padding: 16px 18px; background: linear-gradient(135deg, var(--primary-bg), var(--primary-bg)); border-bottom: 2px solid var(--border-color); }
.profile-avatar { width: 56px; height: 56px; border-radius: 14px; background: linear-gradient(135deg, #0B5ED7, #3B82F6); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 900; text-transform: uppercase; flex-shrink: 0; box-shadow: 0 6px 16px rgba(11, 94, 215, 0.3); border: 3px solid var(--bg-card); }
.profile-name { font-size: 1.1rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.02em; line-height: 1.2; margin-bottom: 4px; }
.profile-meta { display: flex; flex-wrap: wrap; gap: 8px; }
.profile-meta-item { font-size: 0.68rem; font-weight: 600; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 4px; background: var(--bg-card); padding: 3px 10px; border-radius: 12px; border: 1px solid var(--border-color); }
.profile-meta-item i { color: var(--primary); font-size: 0.65rem; }

.procedure-detail-card { background: linear-gradient(135deg, #E8F0FE, #D6E4FF); border-radius: 14px; border: 2px solid var(--primary-light); padding: 20px 24px; margin-bottom: 16px; }
[data-theme="dark"] .procedure-detail-card { background: linear-gradient(135deg, #1E3A5F, #16294A); border-color: #334155; }
.procedure-detail-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; padding-bottom: 14px; border-bottom: 2px solid rgba(11, 94, 215, 0.2); }
.procedure-detail-header .proc-name { font-size: 1.3rem; font-weight: 800; color: #083C8A; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
[data-theme="dark"] .procedure-detail-header .proc-name { color: #93C5FD; }
.procedure-detail-header .proc-name i { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }

.status-badge { padding: 4px 12px; border-radius: 18px; font-size: 0.68rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
.status-badge.warning { background: #FEF3C7; color: #D97706; }
.status-badge.success { background: #D1FAE5; color: #059669; }
.status-badge.danger  { background: #FEE2E2; color: #EF4444; }
.status-badge.info    { background: #E8F0FE; color: #0B5ED7; }
.status-badge.purple  { background: #EDE9FE; color: #7C3AED; }
.status-badge.cyan    { background: #CFFAFE; color: #0891B2; }

.bill-box { border-radius: 14px; border: 2px solid var(--purple); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 18px; background: var(--bg-card); }
.bill-box .bill-header { padding: 14px 20px; background: linear-gradient(135deg, #7C3AED, #6D28D9); color: white; font-size: 0.9rem; font-weight: 800; display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.bill-box .bill-header i { color: #C4B5FD; }
.bill-box .bill-body { padding: 16px 20px; }
.bill-totals-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; }
.bill-total-item { background: var(--bg-body); border-radius: 10px; padding: 12px 14px; border: 1px solid var(--border-color); text-align: center; }
.bill-total-item .bt-label { font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-secondary); font-weight: 800; margin-bottom: 4px; }
.bill-total-item .bt-value { font-family: var(--font-mono); font-size: 0.95rem; font-weight: 900; color: var(--text-primary); letter-spacing: -0.02em; }
.bill-total-item.total .bt-value { color: var(--primary); }
.bill-total-item.paid .bt-value { color: var(--success); }
.bill-total-item.balance .bt-value { color: var(--danger); }
.bill-total-item.discount .bt-value { color: var(--cyan); }
.bill-total-item.premium .bt-value { color: var(--purple); }

.payments-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; margin-top: 12px; }
.payments-table thead th { text-align: left; padding: 10px 12px; font-weight: 700; font-size: 0.6rem; text-transform: uppercase; color: white; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); white-space: nowrap; }
.payments-table tbody td { padding: 10px 12px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); }
.payments-table tbody tr:hover td { background: var(--primary-bg); }

.received-by-cell { display: flex; align-items: center; gap: 8px; }
.received-by-avatar { width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #059669, #34D399); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.6rem; text-transform: uppercase; flex-shrink: 0; font-family: var(--font-mono); }
.received-by-name { font-size: 0.75rem; font-weight: 700; color: var(--text-primary); }
.received-by-meta { font-size: 0.58rem; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); }

.audit-log-item { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border-color); font-size: 0.78rem; }
.audit-log-item:last-child { border-bottom: none; }
.audit-log-item .audit-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; flex-shrink: 0; }
.audit-log-item .audit-content { flex: 1; min-width: 0; }
.audit-log-item .audit-action { font-weight: 800; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.03em; }
.audit-log-item .audit-details { font-size: 0.75rem; color: var(--text-primary); margin-top: 4px; line-height: 1.5; word-break: break-word; }
.audit-log-item .audit-meta { font-size: 0.68rem; color: var(--text-secondary); margin-top: 6px; display: flex; gap: 12px; flex-wrap: wrap; }
.audit-log-item .audit-meta span { display: inline-flex; align-items: center; gap: 4px; }

.action-bar { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); padding: 16px 20px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; box-shadow: var(--shadow-sm); position: sticky; bottom: 20px; z-index: 10; }
.action-bar .action-info { display: flex; align-items: center; gap: 12px; }
.action-bar .action-info .info-icon { width: 42px; height: 42px; border-radius: 12px; background: var(--primary-bg); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
.action-bar .action-info .info-text .info-title { font-size: 0.85rem; font-weight: 800; color: var(--text-primary); }
.action-bar .action-info .info-text .info-sub { font-size: 0.7rem; color: var(--text-secondary); font-weight: 500; }
.action-bar .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
.btn { padding: 10px 20px; border-radius: 10px; font-weight: 700; font-size: 0.8rem; border: none; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 7px; text-decoration: none; white-space: nowrap; }
.btn:hover { transform: translateY(-2px); }
.btn-primary { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
.btn-primary:hover { box-shadow: 0 6px 20px rgba(11, 94, 215, 0.5); color: white; }
.btn-secondary { background: var(--bg-card); color: var(--text-primary); border: 2px solid var(--border-color); }
.btn-secondary:hover { border-color: var(--primary); color: var(--primary); }

.footer { padding: 14px 0; border-top: 1px solid var(--border-color); margin-top: 24px; text-align: center; font-size: 0.7rem; color: var(--text-secondary); }
.footer .footer-brand { color: var(--primary); font-weight: 600; }

@media (max-width: 1024px) { .view-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .status-banner { padding: 14px 16px; }
    .status-banner .status-amount .value { font-size: 1.35rem; }
    .status-banner .status-icon { width: 44px; height: 44px; font-size: 1.2rem; }
    .status-banner .status-info .status-label { font-size: 1.1rem; }
    .action-bar { padding: 12px 14px; flex-direction: column; align-items: stretch; }
    .action-bar .action-buttons { justify-content: center; }
    .btn { padding: 8px 14px; font-size: 0.75rem; }
}
@media print {
    .action-bar, .btn-header { display: none !important; }
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
                if (el) { el.style.transition = 'all 0.5s ease'; el.style.opacity = '0'; setTimeout(function() { el.remove(); }, 500); }
            }, 4000);
        </script>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas <?= $is_equipment ? 'fa-tools' : 'fa-syringe' ?>"></i>
                <?= $item_label ?> Details
                <span class="branch-tag audit-tag"><i class="fas fa-shield-alt"></i> AUDIT</span>
                <span class="branch-tag count-tag">
                    <i class="fas fa-hashtag"></i> #<?= $item_id ?>
                </span>
                <?php if ($is_equipment): ?>
                    <span class="branch-tag cyan-tag"><i class="fas fa-tools"></i> EQUIPMENT</span>
                <?php else: ?>
                    <span class="branch-tag purple-tag"><i class="fas fa-syringe"></i> PROCEDURE</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas <?= $is_equipment ? 'fa-tools' : 'fa-syringe' ?>"></i>
                <strong><?= htmlspecialchars($item['procedure_name']) ?></strong>
                <?php if (!empty($item['branch_name'])): ?>
                    <span class="branch-tag">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($item['branch_name']) ?>
                    </span>
                <?php endif; ?>
                <span class="branch-tag">
                    <i class="fas fa-calendar"></i> <?= date('d M Y, H:i', strtotime($item['item_created_at'])) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="all_procedures.php?patient_id=<?= $item['patient_db_id'] ?? 0 ?>" class="btn-header">
                <i class="fas fa-list"></i> All Items
            </a>
            <a href="other_services.php?tab=procedures" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- STATUS BANNER -->
    <div class="status-banner <?= htmlspecialchars($item['item_status']) ?>">
        <div class="status-left">
            <div class="status-icon">
                <i class="fas fa-<?= in_array($item['item_status'], ['completed', 'paid']) ? 'check-double' : ($item['item_status'] === 'in_progress' ? 'spinner' : ($item['item_status'] === 'cancelled' ? 'times-circle' : 'clock')) ?>"></i>
            </div>
            <div class="status-info">
                <div class="status-label"><?= strtoupper($status_info['label']) ?></div>
                <div class="status-sub">
                    <i class="fas <?= $is_equipment ? 'fa-tools' : 'fa-syringe' ?>"></i> 
                    <?= htmlspecialchars($item['procedure_name']) ?>
                    <?php if (!empty($item['procedure_category'])): ?>
                        • <i class="fas fa-folder"></i> <?= htmlspecialchars($item['procedure_category']) ?>
                    <?php endif; ?>
                    <?php if (!empty($item['procedure_code'])): ?>
                        • <i class="fas fa-code"></i> <?= htmlspecialchars($item['procedure_code']) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="status-amount">
            <div class="label"><?= $item_label ?> Price</div>
            <div class="value">
                <span class="currency-prefix"><?= $currency ?></span>
                <?= number_format($item['procedure_price'] ?? 0, 0) ?>
            </div>
        </div>
    </div>

    <!-- PATIENT + VISIT INFO -->
    <div class="view-grid">
        
        <?php if (!empty($item['patient_name'])): ?>
        <div class="info-card blue-theme">
            <div class="card-header">
                <span class="title"><i class="fas fa-user"></i> Patient Information</span>
            </div>
            
            <div class="profile-header">
                <div class="profile-avatar">
                    <?= strtoupper(substr($item['patient_name'], 0, 1)) ?>
                </div>
                <div style="flex:1;">
                    <div class="profile-name"><?= htmlspecialchars($item['patient_name']) ?></div>
                    <div class="profile-meta">
                        <?php if (!empty($item['patient_number'])): ?>
                            <span class="profile-meta-item">
                                <i class="fas fa-id-card"></i> <?= htmlspecialchars($item['patient_number']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($item['patient_gender'])): ?>
                            <span class="profile-meta-item">
                                <i class="fas fa-<?= strtolower($item['patient_gender']) === 'female' ? 'venus' : 'mars' ?>"></i>
                                <?= htmlspecialchars($item['patient_gender']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($patient_age !== 'N/A'): ?>
                            <span class="profile-meta-item">
                                <i class="fas fa-birthday-cake"></i> <?= $patient_age ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="value mono"><?= htmlspecialchars($item['patient_phone'] ?? 'N/A') ?></span>
                </div>
                <?php if (!empty($item['patient_address'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-map-marker-alt"></i> Address</span>
                    <span class="value"><?= htmlspecialchars($item['patient_address']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($item['blood_group'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-tint"></i> Blood Group</span>
                    <span class="value" style="color:var(--danger);"><?= htmlspecialchars($item['blood_group']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($item['allergies'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-exclamation-triangle"></i> Allergies</span>
                    <span class="value" style="color:var(--danger);"><?= htmlspecialchars($item['allergies']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($item['visit_number'])): ?>
        <div class="info-card blue-theme">
            <div class="card-header">
                <span class="title"><i class="fas fa-calendar-check"></i> Visit Information</span>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-hashtag"></i> Visit #</span>
                    <span class="value mono" style="color:var(--primary);"><?= htmlspecialchars($item['visit_number']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-calendar"></i> Visit Date</span>
                    <span class="value"><?= !empty($item['visit_date']) ? date('d M Y', strtotime($item['visit_date'])) : 'N/A' ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-user-md"></i> Doctor</span>
                    <span class="value"><?= htmlspecialchars($item['doctor_name'] ?? 'N/A') ?></span>
                </div>
                <?php if (!empty($item['receptionist_name'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-user-tie"></i> Receptionist</span>
                    <span class="value"><?= htmlspecialchars($item['receptionist_name']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($item['diagnosis'])): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-notes-medical"></i> Diagnosis</span>
                    <span class="value" style="font-size:0.78rem;color:var(--purple);">
                        <?= htmlspecialchars($item['diagnosis']) ?>
                    </span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-info-circle"></i> Visit Status</span>
                    <span class="value"><?= htmlspecialchars(ucfirst($item['visit_status'] ?? 'N/A')) ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
    </div>

    <!-- PROCEDURE DETAILS -->
    <div class="procedure-detail-card">
        <div class="procedure-detail-header">
            <div class="proc-name">
                <i class="fas <?= $is_equipment ? 'fa-tools' : 'fa-syringe' ?>"></i>
                <?= htmlspecialchars($item['procedure_name']) ?>
                <?php if (!empty($item['procedure_code'])): ?>
                    <span style="font-size:0.7rem;background:rgba(11,94,215,0.15);padding:3px 10px;border-radius:12px;font-family:var(--font-mono);">
                        <?= htmlspecialchars($item['procedure_code']) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php if (!empty($item['procedure_category'])): ?>
                    <span class="status-badge purple" style="font-size:0.65rem;">
                        <i class="fas fa-folder"></i> <?= htmlspecialchars($item['procedure_category']) ?>
                    </span>
                <?php endif; ?>
                <span class="status-badge <?= $status_info['class'] ?>">
                    <?= $status_info['icon'] ?> <?= $status_info['label'] ?>
                </span>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;">
            <div style="display:flex;flex-direction:column;gap:4px;">
                <span style="font-size:0.62rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;display:flex;align-items:center;gap:4px;">
                    <i class="fas fa-tag"></i> Code
                </span>
                <span class="mono" style="font-size:0.88rem;font-weight:600;color:var(--text-primary);">
                    <?= htmlspecialchars($item['procedure_code'] ?? 'N/A') ?>
                </span>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
                <span style="font-size:0.62rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;display:flex;align-items:center;gap:4px;">
                    <i class="fas fa-folder"></i> Category
                </span>
                <span style="font-size:0.88rem;font-weight:600;color:var(--text-primary);">
                    <?= htmlspecialchars($item['procedure_category'] ?? 'N/A') ?>
                </span>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
                <span style="font-size:0.62rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;display:flex;align-items:center;gap:4px;">
                    <i class="fas fa-money-bill-wave"></i> Unit Price
                </span>
                <span style="font-size:0.95rem;font-weight:800;color:var(--primary);font-family:var(--font-mono);">
                    <?= $currency ?> <?= number_format($item['procedure_price'] ?? 0, 0) ?>
                </span>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
                <span style="font-size:0.62rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;display:flex;align-items:center;gap:4px;">
                    <i class="fas fa-hashtag"></i> Quantity
                </span>
                <span style="font-size:0.88rem;font-weight:600;color:var(--text-primary);">
                    <?= (int)($item['quantity'] ?? 1) ?>
                </span>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
                <span style="font-size:0.62rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;display:flex;align-items:center;gap:4px;">
                    <i class="fas fa-money-bill"></i> Total Price
                </span>
                <span style="font-size:0.95rem;font-weight:800;color:var(--primary);font-family:var(--font-mono);">
                    <?= $currency ?> <?= number_format($item['procedure_total'] ?? $item['total_price'] ?? 0, 0) ?>
                </span>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
                <span style="font-size:0.62rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;display:flex;align-items:center;gap:4px;">
                    <i class="fas fa-clock"></i> Created At
                </span>
                <span style="font-size:0.88rem;font-weight:600;color:var(--text-primary);">
                    <?= date('d M Y, H:i', strtotime($item['item_created_at'])) ?>
                </span>
            </div>
        </div>

        <?php if (!empty($item['procedure_description']) || !empty($item['description'])): ?>
        <div style="margin-top:16px;padding:14px 18px;background:var(--bg-card);border-radius:10px;border:2px solid var(--border-color);">
            <div style="font-size:0.62rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">
                <i class="fas fa-info-circle"></i> Description
            </div>
            <div style="font-size:0.82rem;color:var(--text-primary);line-height:1.6;">
                <?= nl2br(htmlspecialchars($item['procedure_description'] ?? $item['description'] ?? '')) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- RELATED BILL -->
    <?php if (!empty($item['bill_id'])): ?>
    <div class="bill-box">
        <div class="bill-header">
            <span><i class="fas fa-file-invoice"></i> Related Bill: <?= htmlspecialchars($item['bill_number']) ?></span>
            <span class="status-badge <?= $bill_s['class'] ?>" style="font-size:0.6rem;background:rgba(255,255,255,0.25);color:white;">
                <?= $bill_s['icon'] ?> <?= $bill_s['label'] ?>
            </span>
        </div>
        <div class="bill-body">
            <div class="bill-totals-grid">
                <div class="bill-total-item">
                    <div class="bt-label">Bill Subtotal</div>
                    <div class="bt-value"><?= $currency ?> <?= number_format($item['bill_subtotal'] ?? 0, 0) ?></div>
                </div>
                <?php if (($item['bill_discount'] ?? 0) > 0): ?>
                <div class="bill-total-item discount">
                    <div class="bt-label">Discount</div>
                    <div class="bt-value">-<?= $currency ?> <?= number_format($item['bill_discount'], 0) ?></div>
                </div>
                <?php endif; ?>
                <?php if (($item['bill_premium'] ?? 0) > 0): ?>
                <div class="bill-total-item premium">
                    <div class="bt-label">Premium</div>
                    <div class="bt-value">+<?= $currency ?> <?= number_format($item['bill_premium'], 0) ?></div>
                </div>
                <?php endif; ?>
                <div class="bill-total-item total">
                    <div class="bt-label">Bill Total</div>
                    <div class="bt-value"><?= $currency ?> <?= number_format($item['bill_total'] ?? 0, 0) ?></div>
                </div>
                <div class="bill-total-item paid">
                    <div class="bt-label">Paid</div>
                    <div class="bt-value"><?= $currency ?> <?= number_format($item['bill_paid'] ?? 0, 0) ?></div>
                </div>
                <div class="bill-total-item balance">
                    <div class="bt-label">Balance</div>
                    <div class="bt-value"><?= $currency ?> <?= number_format($item['bill_balance'] ?? 0, 0) ?></div>
                </div>
            </div>
            
            <div style="margin-top:14px;padding-top:12px;border-top:1px dashed var(--border-color);font-size:0.75rem;color:var(--text-secondary);font-weight:600;">
                <i class="fas fa-info-circle" style="color:var(--purple);"></i>
                This <?= strtolower($item_label) ?> contributes: 
                <strong style="color:var(--purple);font-family:var(--font-mono);">
                    <?= $currency ?> <?= number_format($item['procedure_total'] ?? 0, 0) ?>
                </strong>
                to the bill.
            </div>
            
            <div style="margin-top:10px;">
                <a href="view_bill.php?id=<?= $item['bill_id'] ?>" 
                   class="btn btn-secondary" style="font-size:0.72rem;padding:7px 14px;">
                    <i class="fas fa-file-invoice"></i> View Full Bill
                </a>
            </div>
            
            <?php if (!empty($payments)): ?>
                <div style="margin-top:20px;">
                    <h4 style="font-size:0.75rem;font-weight:800;color:var(--primary);text-transform:uppercase;margin-bottom:10px;">
                        <i class="fas fa-money-bill-wave"></i> Payment History (<?= count($payments) ?>)
                    </h4>
                    <div style="overflow-x:auto;border-radius:8px;">
                        <table class="payments-table">
                            <thead>
                                <tr>
                                    <th>Receipt #</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Received By</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $p): 
                                    $rb_initials = getInitials($p['received_by_name'] ?? '');
                                ?>
                                    <tr>
                                        <td style="font-family:var(--font-mono);font-weight:700;color:var(--primary);">
                                            <?= htmlspecialchars($p['receipt_number']) ?>
                                        </td>
                                        <td style="font-family:var(--font-mono);font-weight:700;color:var(--success);">
                                            <?= $currency ?> <?= number_format($p['amount'], 0) ?>
                                        </td>
                                        <td><?= strtoupper(str_replace('_', ' ', $p['payment_method'] ?? 'cash')) ?></td>
                                        <td>
                                            <div class="received-by-cell">
                                                <div class="received-by-avatar"><?= htmlspecialchars($rb_initials) ?></div>
                                                <div>
                                                    <div class="received-by-name"><?= htmlspecialchars($p['received_by_name'] ?? 'N/A') ?></div>
                                                    <div class="received-by-meta"><?= htmlspecialchars(strtoupper($p['received_by_role'] ?? 'user')) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="font-size:0.72rem;"><?= date('d M Y, H:i', strtotime($p['received_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- AUDIT LOG -->
    <?php if (count($audit_logs) > 0): ?>
    <div class="info-card">
        <div class="card-header" style="background:var(--bg-body);">
            <span class="title" style="font-size:0.85rem;font-weight:800;color:var(--primary);text-transform:uppercase;letter-spacing:0.05em;">
                <i class="fas fa-history"></i> Activity Log (<?= count($audit_logs) ?>)
            </span>
        </div>
        <div class="card-body">
            <?php foreach ($audit_logs as $log): ?>
                <div class="audit-log-item">
                    <div class="audit-icon" style="background:#E8F0FE;color:#0B5ED7;">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="audit-content">
                        <div class="audit-action" style="color:#0B5ED7;">
                            <?= htmlspecialchars(str_replace('_', ' ', $log['action'])) ?>
                        </div>
                        <div class="audit-details">
                            <?= htmlspecialchars($log['details'] ?? '') ?>
                        </div>
                        <div class="audit-meta">
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($log['performed_by_name'] ?? 'System') ?></span>
                            <span><i class="fas fa-store-alt"></i> <?= htmlspecialchars($log['branch_name'] ?? 'N/A') ?></span>
                            <span><i class="fas fa-clock"></i> <?= date('d M Y, H:i', strtotime($log['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ACTION BAR -->
    <div class="action-bar">
        <div class="action-info">
            <div class="info-icon">
                <i class="fas <?= $is_equipment ? 'fa-tools' : 'fa-syringe' ?>"></i>
            </div>
            <div class="info-text">
                <div class="info-title"><?= $item_label ?> Details</div>
                <div class="info-sub">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($item['branch_name'] ?? $user_branch_name) ?>
                </div>
            </div>
        </div>
        <div class="action-buttons">
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print
            </button>
            
            <a href="all_procedures.php?patient_id=<?= $item['patient_db_id'] ?? 0 ?>" class="btn btn-primary">
                <i class="fas fa-list"></i> All Items
            </a>
            
            <a href="other_services.php?tab=procedures" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            View <?= $item_label ?> #<?= $item_id ?>
            <span style="margin:0 8px;">|</span>
            <span><?= date('d M Y, H:i:s') ?></span>
        </p>
    </footer>

</main>

<script>
console.log('%c🔍 Audit - View <?= $item_label ?> (CLEAN)', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px;color:#10B981;font-weight:bold;');
console.log('%c✅ NO Edit/Delete buttons', 'font-size:13px;color:#F59E0B;font-weight:bold;');
console.log('%c✅ VIEW ONLY notice na badges zimeondolewa', 'font-size:13px;color:#34D399;font-weight:bold;');
console.log('%c✅ <?= htmlspecialchars($item['procedure_name']) ?>', 'font-size:13px;color:#34D399;font-weight:bold;');
console.log('%c✅ Patient: <?= htmlspecialchars($item['patient_name'] ?? 'N/A') ?>', 'font-size:13px;color:#34D399;');
console.log('%c💰 Price: <?= $currency ?> <?= number_format($item['procedure_price'] ?? 0, 0) ?>', 'font-size:13px;color:#0B5ED7;font-weight:bold;');
<?php if (!empty($item['bill_id'])): ?>
console.log('%c📄 Bill: <?= htmlspecialchars($item['bill_number']) ?>', 'font-size:13px;color:#7C3AED;font-weight:bold;');
<?php endif; ?>
</script>

</body>
</html>