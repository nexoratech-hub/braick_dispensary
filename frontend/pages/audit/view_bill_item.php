<?php
// ================================================================
// FILE: frontend/pages/audit/view_bill_item.php
// AUDIT - VIEW BILL ITEM DETAILS (VIEW ONLY - SAWA NA ADMIN)
// ✅ Allow both admin and audit roles
// ✅ Full bill item information
// ✅ Related bill, patient, visit info
// ✅ View only - HAKUNA Edit/Delete (audit role)
// ✅ Timezone: Africa/Dar_es_Salaam
// ✅ Full absolute paths
// ================================================================

date_default_timezone_set('Africa/Dar_es_Salaam');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ ALLOW admin AND audit
$allowed_roles = ['admin', 'audit'];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Audit User';
$user_role = $_SESSION['role'] ?? 'audit';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$is_admin = ($user_role === 'admin');
$is_audit = ($user_role === 'audit');

// ✅ Base path
$base_path = $is_admin 
    ? '/dispensary_system/frontend/pages/admin/audit' 
    : '/dispensary_system/frontend/pages/audit';

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$item_type_param = $_GET['type'] ?? '';
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($item_id <= 0) {
    header('Location: ' . $base_path . '/revenue.php?branch=' . $selected_branch_id);
    exit;
}

// ✅ FULL ABSOLUTE PATHS
$document_root = $_SERVER['DOCUMENT_ROOT'] ?? 'C:/xampp/htdocs';
$system_root = $document_root . '/dispensary_system';

require_once $system_root . '/backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $db->exec("SET time_zone = '+03:00'");
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// DELETE ACTION (Admin only)
// ================================================================
$alert_message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($is_audit) {
        $alert_message = "Audit role cannot delete records. Read-only access.";
        $alert_type = 'error';
    } else {
        if ($_POST['action'] === 'delete_item') {
            try {
                $stmt = $db->prepare("SELECT item_name, bill_id, reference_type FROM bill_items WHERE id = ?");
                $stmt->execute([$item_id]);
                $item_del = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($item_del) {
                    $bill_id = (int)$item_del['bill_id'];
                    
                    // Get visit_id from bill
                    $stmt = $db->prepare("SELECT visit_id FROM bills WHERE id = ?");
                    $stmt->execute([$bill_id]);
                    $visit_id_del = (int)($stmt->fetch(PDO::FETCH_ASSOC)['visit_id'] ?? 0);
                    
                    $db->prepare("DELETE FROM bill_items WHERE id = ?")->execute([$item_id]);
                    
                    // Recalculate bill
                    $stmt = $db->prepare("SELECT COALESCE(SUM(total_price - discount_amount), 0) as subtotal FROM bill_items WHERE bill_id = ? AND status != 'cancelled'");
                    $stmt->execute([$bill_id]);
                    $new_subtotal = (float)$stmt->fetch(PDO::FETCH_ASSOC)['subtotal'];
                    $stmt = $db->prepare("SELECT pharmacy_discount, cashier_discount, pharmacy_premium, cashier_premium FROM bills WHERE id = ?");
                    $stmt->execute([$bill_id]);
                    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
                    $new_total = $new_subtotal - (float)$bill['pharmacy_discount'] - (float)$bill['cashier_discount'] + (float)$bill['pharmacy_premium'] + (float)$bill['cashier_premium'];
                    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as paid FROM payments WHERE bill_id = ?");
                    $stmt->execute([$bill_id]);
                    $paid = (float)$stmt->fetch(PDO::FETCH_ASSOC)['paid'];
                    $new_balance = max(0, $new_total - $paid);
                    $new_status = 'pending';
                    if ($new_balance <= 0 && $new_total > 0) $new_status = 'paid';
                    elseif ($paid > 0 && $new_balance > 0) $new_status = 'partial';
                    $db->prepare("UPDATE bills SET subtotal = ?, total_amount = ?, balance = ?, status = ?, updated_at = NOW() WHERE id = ?")
                       ->execute([$new_subtotal, $new_total, $new_balance, $new_status, $bill_id]);
                    
                    try {
                        $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) VALUES (?, ?, 'delete_bill_item', ?, ?, NOW())")
                           ->execute([$user_id, $user_branch_id, "Deleted bill item: " . ($item_del['item_name'] ?? 'N/A'), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                    } catch (Exception $e) {}
                    
                    if ($visit_id_del > 0) {
                        header('Location: ' . $base_path . '/view_visit.php?id=' . $visit_id_del . '&branch=' . $selected_branch_id . '&deleted=1');
                    } else {
                        header('Location: ' . $base_path . '/revenue.php?branch=' . $selected_branch_id . '&deleted=1');
                    }
                    exit;
                }
            } catch (Exception $e) {
                $alert_message = "Error: " . $e->getMessage();
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
// LOGO DETECTION
// ================================================================
$logo_base64 = '';
$logo_found = false;

$possible_logo_paths = [
    $system_root . '/frontend/assets/uploads/profiles/braick_logo.png',
    $system_root . '/frontend/assets/uploads/profiles/Braick_logo.png',
    $system_root . '/frontend/assets/uploads/profiles/logo.png',
    $system_root . '/frontend/assets/images/braick_logo.png',
    $system_root . '/frontend/assets/images/logo.png',
];

foreach ($possible_logo_paths as $path) {
    if (file_exists($path) && is_readable($path)) {
        $logo_found = true;
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = 'image/png';
        if ($ext === 'jpg' || $ext === 'jpeg') $mime = 'image/jpeg';
        elseif ($ext === 'svg') $mime = 'image/svg+xml';
        $logo_base64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
        break;
    }
}

if (!$logo_found) {
    $svg_logo = '<svg xmlns="http://www.w3.org/2000/svg" width="90" height="90" viewBox="0 0 90 90">' .
        '<defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">' .
        '<stop offset="0%" style="stop-color:#0B5ED7"/>' .
        '<stop offset="100%" style="stop-color:#7C3AED"/>' .
        '</linearGradient></defs>' .
        '<rect width="90" height="90" rx="12" fill="url(#g)"/>' .
        '<text x="45" y="62" text-anchor="middle" fill="white" font-size="48" font-weight="900" font-family="Arial,sans-serif">B</text>' .
        '</svg>';
    $logo_base64 = 'data:image/svg+xml;base64,' . base64_encode($svg_logo);
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// FETCH BILL ITEM DETAILS
// ================================================================
$item = null;
try {
    $sql = "SELECT 
                bi.*,
                b.bill_number,
                b.visit_id,
                b.patient_id as bill_patient_id,
                b.total_amount as bill_total,
                b.paid_amount as bill_paid,
                b.balance as bill_balance,
                b.status as bill_status,
                b.payment_method as bill_payment_method,
                b.total_discount as bill_discount,
                b.premium_amount as bill_premium,
                b.created_at as bill_created_at,
                pat.patient_id as patient_code,
                pat.full_name as patient_name,
                pat.phone as patient_phone,
                pat.gender as patient_gender,
                pat.date_of_birth as patient_dob,
                pat.blood_group as patient_blood_group,
                pat.address as patient_address,
                v.visit_number,
                v.visit_date,
                v.visit_type,
                v.status as visit_status,
                v.diagnosis,
                v.disease_code,
                v.treatment,
                d.full_name as doctor_name,
                d.role as doctor_role,
                r.full_name as receptionist_name,
                r.role as receptionist_role,
                u.full_name as bill_created_by_name,
                u.role as bill_created_by_role,
                br.name as branch_name
            FROM bill_items bi
            LEFT JOIN bills b ON bi.bill_id = b.id
            LEFT JOIN patients pat ON b.patient_id = pat.id
            LEFT JOIN visits v ON b.visit_id = v.id
            LEFT JOIN users d ON v.doctor_id = d.id
            LEFT JOIN users r ON v.receptionist_id = r.id
            LEFT JOIN users u ON b.created_by = u.id
            LEFT JOIN branches br ON b.branch_id = br.id
            WHERE bi.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching bill item: " . $e->getMessage());
}

if (!$item) {
    die("Bill item not found.");
}

// ✅ Include headers
include_once $system_root . '/frontend/components/audit_header.php';
include_once $system_root . '/frontend/components/audit_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Bill Item • <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></title>
<link rel="icon" href="<?= $logo_path ?>" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #3B82F6;
    --primary-bg: #E8F0FE;
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
    --teal: #0D9488;
    --teal-bg: #CCFBF1;
    --pink: #DB2777;
    --pink-bg: #FCE7F3;
    --indigo: #6366F1;
    --indigo-bg: #E0E7FF;
    --slate: #94A3B8;
    --slate-bg: #F1F5F9;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --text-muted: #94A3B8;
    --border-color: #E2E8F0;
    --border-strong: #CBD5E1;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.04);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1), 0 4px 10px rgba(0,0,0,0.05);
    --shadow-xl: 0 20px 50px rgba(0,0,0,0.15), 0 10px 20px rgba(0,0,0,0.08);
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --radius-full: 9999px;
}

[data-theme="dark"] {
    --bg-body: #0B1220;
    --bg-card: #111C33;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --text-muted: #64748B;
    --border-color: #1E2E4A;
    --border-strong: #2A3E5F;
    --primary-bg: #12294A;
    --success-bg: #0F2E22;
    --danger-bg: #3A1414;
    --warning-bg: #3A2A0F;
    --purple-bg: #2A1A4A;
    --cyan-bg: #0A2E3A;
    --teal-bg: #0A2E2A;
    --pink-bg: #3A1A2A;
    --indigo-bg: #1E1B4B;
    --slate-bg: #1E2A3D;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    font-family: var(--font-primary);
    background: var(--bg-body);
    color: var(--text-primary);
    -webkit-font-smoothing: antialiased;
    line-height: 1.5;
    min-height: 100vh;
}

.money-number, .money-cell, .font-mono {
    font-family: var(--font-mono) !important;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

.alert { padding: 14px 20px; border-radius: var(--radius-md); margin-bottom: 18px; display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 0.85rem; animation: slideDown 0.4s ease; border-left: 4px solid; box-shadow: var(--shadow-sm); }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.alert.success { background: var(--success-bg); color: var(--success); border-left-color: var(--success); }
.alert.error { background: var(--danger-bg); color: var(--danger); border-left-color: var(--danger); }

/* ================================================================ */
/* PAGE HEADER */
/* ================================================================ */
.page-header { 
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #7C3AED 100%); 
    border-radius: var(--radius-lg); 
    padding: 24px 28px; 
    margin-bottom: 20px; 
    display: flex; 
    flex-wrap: wrap; 
    justify-content: space-between; 
    align-items: center; 
    gap: 16px; 
    box-shadow: 0 10px 40px rgba(11, 94, 215, 0.35); 
    position: relative; 
    overflow: hidden; 
}
.page-header::before { 
    content: ''; 
    position: absolute; 
    top: -50%; right: -10%; 
    width: 400px; height: 400px; 
    background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%); 
    border-radius: 50%; 
    pointer-events: none; 
}
.page-header .page-title { 
    color: white; 
    font-size: 1.5rem; 
    font-weight: 900; 
    display: flex; 
    align-items: center; 
    gap: 12px; 
    flex-wrap: wrap; 
    position: relative; 
    z-index: 1; 
}
.page-header .page-title i { font-size: 1.6rem; color: #93C5FD; }
.page-header .page-subtitle { 
    color: rgba(255,255,255,0.9); 
    font-size: 0.8rem; 
    display: flex; 
    align-items: center; 
    gap: 8px; 
    flex-wrap: wrap; 
    margin-top: 8px; 
    position: relative; 
    z-index: 1; 
}
.header-badge {
    background: rgba(255,255,255,0.15);
    color: white;
    padding: 4px 12px;
    border-radius: var(--radius-full);
    font-size: 0.68rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.15);
}
.header-badge.green { background: linear-gradient(135deg, #10B981, #059669); border-color: rgba(255,255,255,0.25); font-weight: 800; }
.header-badge.orange { background: linear-gradient(135deg, #F59E0B, #D97706); border-color: rgba(255,255,255,0.25); font-weight: 800; }
.header-badge.purple { background: linear-gradient(135deg, #7C3AED, #A78BFA); border-color: rgba(255,255,255,0.25); font-weight: 800; }
.header-badge.cyan { background: linear-gradient(135deg, #0891B2, #22D3EE); border-color: rgba(255,255,255,0.25); font-weight: 800; }
.header-badge.audit-tag { background: linear-gradient(135deg, #3B82F6, #2563EB); font-weight: 800; }

.btn-header { 
    background: rgba(255,255,255,0.15); 
    color: white; 
    border: 1px solid rgba(255,255,255,0.25); 
    padding: 10px 16px; 
    border-radius: var(--radius-sm); 
    font-weight: 700; 
    font-size: 0.75rem; 
    transition: all 0.25s; 
    text-decoration: none; 
    display: inline-flex; 
    align-items: center; 
    gap: 6px; 
    backdrop-filter: blur(10px); 
    position: relative; 
    z-index: 1; 
    cursor: pointer; 
}
.btn-header:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }
.btn-header.danger { background: rgba(220,38,38,0.3); border-color: rgba(255,255,255,0.3); }
.btn-header.danger:hover { background: rgba(220,38,38,0.6); }
.btn-header.warning { background: rgba(245,158,11,0.3); border-color: rgba(255,255,255,0.3); }
.btn-header.warning:hover { background: rgba(245,158,11,0.6); }
.btn-header.success { background: rgba(16,185,129,0.3); border-color: rgba(255,255,255,0.3); }
.btn-header.success:hover { background: rgba(16,185,129,0.6); }

/* ================================================================ */
/* CARDS */
/* ================================================================ */
.card { 
    background: var(--bg-card); 
    border-radius: var(--radius-lg); 
    border: 1px solid var(--border-color); 
    overflow: hidden; 
    box-shadow: var(--shadow-sm); 
    margin-bottom: 20px; 
}
.card-header { 
    padding: 14px 20px; 
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8); 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    flex-wrap: wrap; 
    gap: 10px; 
}
.card-header.green { background: linear-gradient(135deg, #059669, #047857); }
.card-header.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
.card-header.cyan { background: linear-gradient(135deg, #0891B2, #0E7490); }
.card-header.orange { background: linear-gradient(135deg, #F59E0B, #D97706); }
.card-header.red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
.card-header.dark { background: linear-gradient(135deg, #1E293B, #334155); }
.card-header.pink { background: linear-gradient(135deg, #DB2777, #BE185D); }
.card-header.indigo { background: linear-gradient(135deg, #6366F1, #4338CA); }
.card-header .title { 
    color: white; 
    font-size: 0.88rem; 
    font-weight: 800; 
    display: flex; 
    align-items: center; 
    gap: 10px; 
}
.card-header .title i { color: #93C5FD; }
.card-header .count { 
    color: rgba(255,255,255,0.95); 
    font-size: 0.7rem; 
    font-weight: 700; 
    background: rgba(255,255,255,0.18); 
    padding: 5px 12px; 
    border-radius: var(--radius-full); 
    backdrop-filter: blur(10px); 
}
.card-body { padding: 18px 20px; }

/* ================================================================ */
/* ITEM HERO */
/* ================================================================ */
.item-hero {
    background: linear-gradient(135deg, #0B5ED7, #7C3AED);
    padding: 24px 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    position: relative;
    overflow: hidden;
}
.item-hero::before { 
    content: ''; 
    position: absolute; 
    top: -50%; right: -5%; 
    width: 300px; height: 300px; 
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); 
    border-radius: 50%; 
    pointer-events: none; 
}
.item-hero-left {
    display: flex;
    align-items: center;
    gap: 18px;
    position: relative;
    z-index: 1;
}
.item-hero-icon {
    width: 70px;
    height: 70px;
    border-radius: var(--radius-md);
    background: rgba(255,255,255,0.2);
    border: 2px solid rgba(255,255,255,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
    flex-shrink: 0;
    backdrop-filter: blur(10px);
}
.item-hero-info {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.item-hero-name {
    font-size: 1.4rem;
    font-weight: 900;
    color: white;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.item-hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 0.72rem;
}
.item-hero-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(255,255,255,0.15);
    color: rgba(255,255,255,0.95);
    padding: 4px 10px;
    border-radius: var(--radius-full);
    font-weight: 600;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.15);
}
.item-hero-right {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}
.item-hero-stat {
    background: rgba(255,255,255,0.18);
    padding: 12px 18px;
    border-radius: var(--radius-md);
    text-align: center;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
    min-width: 110px;
}
.item-hero-stat-label {
    font-size: 0.58rem;
    color: rgba(255,255,255,0.8);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 800;
    margin-bottom: 4px;
}
.item-hero-stat-value {
    font-size: 1.1rem;
    font-weight: 900;
    color: white;
    font-family: var(--font-mono);
    letter-spacing: -0.03em;
}

/* ================================================================ */
/* INFO GRID */
/* ================================================================ */
.info-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
    gap: 0; 
    background: var(--slate-bg); 
}
.info-item { 
    padding: 16px 20px; 
    border-right: 1px solid var(--border-color); 
    border-bottom: 1px solid var(--border-color); 
}
.info-item:last-child { border-right: none; }
.info-label { 
    font-size: 0.6rem; 
    color: var(--text-secondary); 
    text-transform: uppercase; 
    letter-spacing: 0.08em; 
    font-weight: 800; 
    margin-bottom: 6px; 
    display: flex; 
    align-items: center; 
    gap: 5px; 
}
.info-label i { color: var(--primary); font-size: 0.7rem; }
.info-value { 
    font-size: 0.88rem; 
    font-weight: 700; 
    color: var(--text-primary); 
    word-break: break-word; 
}
.info-value.large { font-size: 1rem; }
.info-value.doctor { color: var(--purple); }
.info-value.receptionist { color: var(--primary); }
.info-value.diagnosis { color: var(--danger); font-weight: 800; }
.info-value.treatment { color: var(--success); font-weight: 700; }
.info-value.muted { color: var(--text-muted); font-weight: 500; font-style: italic; }

/* ================================================================ */
/* PRICE BREAKDOWN */
/* ================================================================ */
.price-breakdown {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0;
    background: var(--bg-card);
}
.price-item {
    padding: 18px 20px;
    text-align: center;
    border-right: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
}
.price-item:last-child { border-right: none; }
.price-label {
    font-size: 0.62rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 800;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}
.price-label i { font-size: 0.75rem; }
.price-value {
    font-family: var(--font-mono);
    font-size: 1.3rem;
    font-weight: 900;
    letter-spacing: -0.03em;
}
.price-value.unit { color: var(--text-secondary); }
.price-value.total { color: var(--success); }
.price-value.discount { color: var(--warning); }
.price-value.final { color: var(--primary); }
.price-value.tax { color: var(--purple); }

/* ================================================================ */
/* STATUS BADGE */
/* ================================================================ */
.status-badge { 
    display: inline-flex; 
    align-items: center; 
    gap: 5px; 
    padding: 6px 14px; 
    border-radius: var(--radius-full); 
    font-size: 0.68rem; 
    font-weight: 800; 
    text-transform: uppercase; 
    white-space: nowrap; 
}
.status-badge.paid { background: var(--success-bg); color: var(--success); border: 1.5px solid var(--success); }
.status-badge.pending { background: var(--warning-bg); color: var(--warning); border: 1.5px solid var(--warning); }
.status-badge.partial { background: var(--cyan-bg); color: var(--cyan); border: 1.5px solid var(--cyan); }
.status-badge.cancelled { background: var(--danger-bg); color: var(--danger); border: 1.5px solid var(--danger); }
.status-badge.refunded { background: var(--pink-bg); color: var(--pink); border: 1.5px solid var(--pink); }

/* ================================================================ */
/* ITEM TYPE BADGE */
/* ================================================================ */
.item-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: var(--radius-full);
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.item-type-badge.consultation { background: var(--success-bg); color: var(--success); border: 1.5px solid var(--success); }
.item-type-badge.registration { background: var(--slate-bg); color: var(--text-secondary); border: 1.5px solid var(--border-strong); }
.item-type-badge.lab_test { background: var(--primary-bg); color: var(--primary); border: 1.5px solid var(--primary); }
.item-type-badge.medication { background: var(--warning-bg); color: var(--warning); border: 1.5px solid var(--warning); }
.item-type-badge.procedure { background: var(--teal-bg); color: var(--teal); border: 1.5px solid var(--teal); }
.item-type-badge.equipment { background: var(--purple-bg); color: var(--purple); border: 1.5px solid var(--purple); }
.item-type-badge.tool { background: var(--indigo-bg); color: var(--indigo); border: 1.5px solid var(--indigo); }
.item-type-badge.other { background: var(--pink-bg); color: var(--pink); border: 1.5px solid var(--pink); }

/* ================================================================ */
/* PATIENT MINI CARD */
/* ================================================================ */
.patient-mini {
    display: flex;
    align-items: center;
    gap: 14px;
}
.patient-mini-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0B5ED7, #7C3AED);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    font-size: 1.1rem;
    flex-shrink: 0;
    text-transform: uppercase;
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}
.patient-mini-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.patient-mini-name {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--text-primary);
}
.patient-mini-meta {
    font-size: 0.68rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

/* ================================================================ */
/* RESPONSIVE */
/* ================================================================ */
@media (max-width: 1024px) {
    .info-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .page-header { padding: 18px 20px; }
    .page-header .page-title { font-size: 1.2rem; }
    .info-grid { grid-template-columns: 1fr; }
    .item-hero { flex-direction: column; align-items: stretch; }
    .item-hero-right { justify-content: space-between; }
    .item-hero-stat { flex: 1; }
    .price-breakdown { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
    .price-breakdown { grid-template-columns: 1fr; }
}

@media print {
    .btn-header, .action-buttons, .modal-overlay { display: none !important; }
    .page-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .card { break-inside: avoid; }
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

    <?php 
        $item_type = strtolower($item['item_type'] ?? 'other');
        $item_status = strtolower($item['status'] ?? 'pending');
        
        $type_icons = [
            'consultation' => 'fa-stethoscope',
            'registration' => 'fa-user-plus',
            'lab_test' => 'fa-flask',
            'medication' => 'fa-pills',
            'procedure' => 'fa-procedures',
            'equipment' => 'fa-toolbox',
            'tool' => 'fa-tools',
            'other' => 'fa-box'
        ];
        $item_icon = $type_icons[$item_type] ?? 'fa-box';
        
        $status_icon = 'fa-clock';
        if ($item_status === 'paid') $status_icon = 'fa-check-circle';
        elseif ($item_status === 'cancelled') $status_icon = 'fa-times-circle';
        elseif ($item_status === 'refunded') $status_icon = 'fa-undo';
        
        $item_total = (float)($item['total_price'] ?? 0);
        $item_discount = (float)($item['discount_amount'] ?? 0);
        $item_tax = (float)($item['tax_amount'] ?? 0);
        $item_final = $item_total - $item_discount + $item_tax;
    ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-receipt"></i>
                Bill Item Details
                <?php if ($is_admin): ?>
                    <span class="header-badge" style="background:linear-gradient(135deg,#FCD34D,#F59E0B);color:#78350F;"><i class="fas fa-crown"></i> ADMIN</span>
                <?php else: ?>
                    <span class="header-badge audit-tag"><i class="fas fa-shield-alt"></i> AUDIT</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <span class="header-badge cyan"><i class="fas fa-hashtag"></i> Item #<?= $item_id ?></span>
                <span class="header-badge <?= $item_status === 'paid' ? 'green' : 'orange' ?>">
                    <i class="fas <?= $status_icon ?>"></i> <?= strtoupper($item_status) ?>
                </span>
                <span class="header-badge purple"><i class="fas fa-money-bill-wave"></i> <?= $currency ?> <?= number_format($item_final, 0) ?></span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <?php if ($is_admin): ?>
                <!-- ✅ Admin Only - Edit/Delete buttons -->
                <a href="<?= $base_path ?>/edit_bill_item.php?id=<?= $item_id ?>&branch=<?= $selected_branch_id ?>" class="btn-header warning">
                    <i class="fas fa-edit"></i> Edit Item
                </a>
                <button onclick="confirmDeleteItem(<?= $item_id ?>, '<?= htmlspecialchars(addslashes($item['item_name'] ?? 'N/A')) ?>')" class="btn-header danger">
                    <i class="fas fa-trash"></i> Delete Item
                </button>
            <?php endif; ?>
            <!-- View-only buttons -->
            <button onclick="window.print()" class="btn-header success">
                <i class="fas fa-print"></i> Print
            </button>
            <?php if (!empty($item['visit_id'])): ?>
            <a href="<?= $base_path ?>/view_visit.php?id=<?= $item['visit_id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back to Visit
            </a>
            <?php else: ?>
            <a href="<?= $base_path ?>/revenue.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ITEM HERO -->
    <div class="card">
        <div class="item-hero">
            <div class="item-hero-left">
                <div class="item-hero-icon">
                    <i class="fas <?= $item_icon ?>"></i>
                </div>
                <div class="item-hero-info">
                    <div class="item-hero-name">
                        <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?>
                    </div>
                    <div class="item-hero-meta">
                        <span class="item-hero-meta-item">
                            <i class="fas fa-tag"></i> <?= htmlspecialchars(str_replace('_', ' ', strtoupper($item_type))) ?>
                        </span>
                        <?php if (!empty($item['item_code'])): ?>
                        <span class="item-hero-meta-item">
                            <i class="fas fa-barcode"></i> <?= htmlspecialchars($item['item_code']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($item['bill_number'])): ?>
                        <span class="item-hero-meta-item">
                            <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($item['bill_number']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($item['reference_type'])): ?>
                        <span class="item-hero-meta-item">
                            <i class="fas fa-link"></i> <?= htmlspecialchars($item['reference_type']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="item-hero-right">
                <div class="item-hero-stat">
                    <div class="item-hero-stat-label">Quantity</div>
                    <div class="item-hero-stat-value"><?= number_format((int)($item['quantity'] ?? 1)) ?></div>
                </div>
                <div class="item-hero-stat">
                    <div class="item-hero-stat-label">Final Price</div>
                    <div class="item-hero-stat-value"><?= number_format($item_final, 0) ?></div>
                </div>
                <div class="item-hero-stat">
                    <div class="item-hero-stat-label">Status</div>
                    <div class="item-hero-stat-value" style="font-size:0.85rem;padding-top:3px;">
                        <i class="fas <?= $status_icon ?>"></i> <?= strtoupper($item_status) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PRICE BREAKDOWN -->
    <div class="card">
        <div class="card-header">
            <span class="title"><i class="fas fa-calculator"></i> Price Breakdown</span>
            <span class="count"><?= $currency ?> <?= number_format($item_final, 0) ?></span>
        </div>
        <div class="price-breakdown">
            <div class="price-item">
                <div class="price-label"><i class="fas fa-tag" style="color:var(--text-secondary);"></i> Unit Price</div>
                <div class="price-value unit"><?= $currency ?> <?= number_format((float)($item['unit_price'] ?? 0), 0) ?></div>
            </div>
            <div class="price-item">
                <div class="price-label"><i class="fas fa-times" style="color:var(--text-secondary);"></i> Quantity</div>
                <div class="price-value unit">× <?= number_format((int)($item['quantity'] ?? 1)) ?></div>
            </div>
            <div class="price-item">
                <div class="price-label"><i class="fas fa-money-bill-wave" style="color:var(--success);"></i> Total Price</div>
                <div class="price-value total"><?= $currency ?> <?= number_format($item_total, 0) ?></div>
            </div>
            <?php if ($item_discount > 0): ?>
            <div class="price-item">
                <div class="price-label"><i class="fas fa-tag" style="color:var(--warning);"></i> Discount</div>
                <div class="price-value discount">- <?= $currency ?> <?= number_format($item_discount, 0) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($item_tax > 0): ?>
            <div class="price-item">
                <div class="price-label"><i class="fas fa-percent" style="color:var(--purple);"></i> Tax</div>
                <div class="price-value tax">+ <?= $currency ?> <?= number_format($item_tax, 0) ?></div>
            </div>
            <?php endif; ?>
            <div class="price-item" style="background:linear-gradient(135deg, var(--primary-bg), transparent);">
                <div class="price-label"><i class="fas fa-check-circle" style="color:var(--primary);"></i> Final Price</div>
                <div class="price-value final"><?= $currency ?> <?= number_format($item_final, 0) ?></div>
            </div>
        </div>
    </div>

    <!-- ITEM INFORMATION -->
    <div class="card">
        <div class="card-header cyan">
            <span class="title"><i class="fas fa-info-circle"></i> Item Information</span>
        </div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label"><i class="fas fa-hashtag"></i> Item ID</div>
                <div class="info-value">#<?= $item_id ?></div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-tag"></i> Item Type</div>
                <div class="info-value">
                    <span class="item-type-badge <?= $item_type ?>">
                        <i class="fas <?= $item_icon ?>"></i> <?= htmlspecialchars(str_replace('_', ' ', strtoupper($item_type))) ?>
                    </span>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-info-circle"></i> Status</div>
                <div class="info-value">
                    <span class="status-badge <?= $item_status ?>">
                        <i class="fas <?= $status_icon ?>"></i> <?= strtoupper($item_status) ?>
                    </span>
                </div>
            </div>
            <?php if (!empty($item['item_code'])): ?>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-barcode"></i> Item Code</div>
                <div class="info-value"><?= htmlspecialchars($item['item_code']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($item['reference_type'])): ?>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-link"></i> Reference Type</div>
                <div class="info-value"><?= htmlspecialchars(ucfirst($item['reference_type'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($item['reference_id'])): ?>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-hashtag"></i> Reference ID</div>
                <div class="info-value">#<?= (int)$item['reference_id'] ?></div>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-calendar-plus"></i> Created At</div>
                <div class="info-value"><?= !empty($item['created_at']) ? date('d M Y, H:i', strtotime($item['created_at'])) : '—' ?></div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-calendar-check"></i> Updated At</div>
                <div class="info-value"><?= !empty($item['updated_at']) ? date('d M Y, H:i', strtotime($item['updated_at'])) : '—' ?></div>
            </div>
            <?php if (!empty($item['description'])): ?>
            <div class="info-item" style="grid-column: span 2;">
                <div class="info-label"><i class="fas fa-align-left"></i> Description</div>
                <div class="info-value"><?= nl2br(htmlspecialchars($item['description'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- BILL INFORMATION -->
    <div class="card">
        <div class="card-header green">
            <span class="title"><i class="fas fa-file-invoice"></i> Bill Information</span>
            <?php if (!empty($item['bill_id'])): ?>
            <a href="<?= $base_path ?>/view_bill.php?id=<?= $item['bill_id'] ?>&branch=<?= $selected_branch_id ?>" style="color:white;font-size:0.7rem;font-weight:700;text-decoration:none;background:rgba(255,255,255,0.2);padding:5px 12px;border-radius:var(--radius-full);backdrop-filter:blur(10px);display:inline-flex;align-items:center;gap:5px;">
                <i class="fas fa-external-link-alt"></i> View Bill
            </a>
            <?php endif; ?>
        </div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label"><i class="fas fa-hashtag"></i> Bill Number</div>
                <div class="info-value"><?= htmlspecialchars($item['bill_number'] ?? 'N/A') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-info-circle"></i> Bill Status</div>
                <div class="info-value">
                    <?php 
                        $b_status = strtolower($item['bill_status'] ?? 'pending');
                        $b_icon = 'fa-clock';
                        if ($b_status === 'paid') $b_icon = 'fa-check-circle';
                        elseif ($b_status === 'partial') $b_icon = 'fa-hourglass-half';
                        elseif ($b_status === 'cancelled') $b_icon = 'fa-times-circle';
                    ?>
                    <span class="status-badge <?= $b_status ?>"><i class="fas <?= $b_icon ?>"></i> <?= strtoupper($b_status) ?></span>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-money-bill-wave"></i> Bill Total</div>
                <div class="info-value large"><?= $currency ?> <?= number_format((float)($item['bill_total'] ?? 0), 0) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-check-circle"></i> Bill Paid</div>
                <div class="info-value large" style="color:var(--success);"><?= $currency ?> <?= number_format((float)($item['bill_paid'] ?? 0), 0) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-exclamation-circle"></i> Bill Balance</div>
                <div class="info-value large" style="color:<?= (float)($item['bill_balance'] ?? 0) > 0 ? 'var(--danger)' : 'var(--text-secondary)' ?>;"><?= $currency ?> <?= number_format((float)($item['bill_balance'] ?? 0), 0) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-credit-card"></i> Payment Method</div>
                <div class="info-value"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $item['bill_payment_method'] ?? 'Cash'))) ?></div>
            </div>
            <?php if ((float)($item['bill_discount'] ?? 0) > 0): ?>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-tag"></i> Bill Discount</div>
                <div class="info-value" style="color:var(--warning);">- <?= $currency ?> <?= number_format((float)$item['bill_discount'], 0) ?></div>
            </div>
            <?php endif; ?>
            <?php if ((float)($item['bill_premium'] ?? 0) > 0): ?>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-star"></i> Bill Premium</div>
                <div class="info-value" style="color:var(--purple);">+ <?= $currency ?> <?= number_format((float)$item['bill_premium'], 0) ?></div>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-user"></i> Created By</div>
                <div class="info-value">
                    <?= htmlspecialchars($item['bill_created_by_name'] ?? 'N/A') ?>
                    <?php if (!empty($item['bill_created_by_role'])): ?>
                        <span style="font-size:0.6rem;background:var(--primary-bg);color:var(--primary);padding:2px 8px;border-radius:4px;font-weight:800;margin-left:5px;"><?= htmlspecialchars(strtoupper($item['bill_created_by_role'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-calendar-plus"></i> Bill Created</div>
                <div class="info-value"><?= !empty($item['bill_created_at']) ? date('d M Y, H:i', strtotime($item['bill_created_at'])) : '—' ?></div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-store-alt"></i> Branch</div>
                <div class="info-value"><?= htmlspecialchars($item['branch_name'] ?? 'N/A') ?></div>
            </div>
        </div>
    </div>

    <!-- PATIENT INFORMATION -->
    <?php if (!empty($item['patient_name'])): ?>
    <div class="card">
        <div class="card-header purple">
            <span class="title"><i class="fas fa-user"></i> Patient Information</span>
            <?php if (!empty($item['patient_code'])): ?>
            <span class="count"><i class="fas fa-id-card"></i> <?= htmlspecialchars($item['patient_code']) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="patient-mini" style="padding:8px 0;">
                <?php 
                    $pname_parts = explode(' ', trim($item['patient_name'] ?? 'N/A'));
                    $pinitials = count($pname_parts) >= 2 ? strtoupper(substr($pname_parts[0], 0, 1) . substr($pname_parts[1], 0, 1)) : strtoupper(substr($item['patient_name'] ?? 'NA', 0, 2));
                ?>
                <div class="patient-mini-avatar"><?= htmlspecialchars($pinitials) ?></div>
                <div class="patient-mini-info">
                    <div class="patient-mini-name"><?= htmlspecialchars($item['patient_name'] ?? 'N/A') ?></div>
                    <div class="patient-mini-meta">
                        <?php if (!empty($item['patient_code'])): ?>
                            <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($item['patient_code']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['patient_phone'])): ?>
                            <span><i class="fas fa-phone"></i> <?= htmlspecialchars($item['patient_phone']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['patient_gender'])): ?>
                            <span><i class="fas fa-venus-mars"></i> <?= htmlspecialchars(ucfirst($item['patient_gender'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['patient_dob'])): ?>
                            <span><i class="fas fa-birthday-cake"></i> <?= date('d M Y', strtotime($item['patient_dob'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['patient_blood_group'])): ?>
                            <span><i class="fas fa-tint"></i> <?= htmlspecialchars($item['patient_blood_group']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['patient_address'])): ?>
                            <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($item['patient_address']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- VISIT INFORMATION -->
    <?php if (!empty($item['visit_number'])): ?>
    <div class="card">
        <div class="card-header indigo">
            <span class="title"><i class="fas fa-stethoscope"></i> Visit Information</span>
            <a href="<?= $base_path ?>/view_visit.php?id=<?= $item['visit_id'] ?>&branch=<?= $selected_branch_id ?>" style="color:white;font-size:0.7rem;font-weight:700;text-decoration:none;background:rgba(255,255,255,0.2);padding:5px 12px;border-radius:var(--radius-full);backdrop-filter:blur(10px);display:inline-flex;align-items:center;gap:5px;">
                <i class="fas fa-external-link-alt"></i> View Visit
            </a>
        </div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label"><i class="fas fa-hashtag"></i> Visit Number</div>
                <div class="info-value"><?= htmlspecialchars($item['visit_number'] ?? 'N/A') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-calendar"></i> Visit Date</div>
                <div class="info-value"><?= !empty($item['visit_date']) ? date('d M Y, H:i', strtotime($item['visit_date'])) : '—' ?></div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-tag"></i> Visit Type</div>
                <div class="info-value"><?= htmlspecialchars($item['visit_type'] ?? 'N/A') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-info-circle"></i> Visit Status</div>
                <div class="info-value">
                    <?php 
                        $v_status = strtolower($item['visit_status'] ?? 'pending');
                        $v_icon = 'fa-clock';
                        if ($v_status === 'completed') $v_icon = 'fa-check-circle';
                        elseif ($v_status === 'cancelled') $v_icon = 'fa-times-circle';
                    ?>
                    <span class="status-badge <?= $v_status === 'completed' ? 'paid' : ($v_status === 'cancelled' ? 'cancelled' : 'pending') ?>"><i class="fas <?= $v_icon ?>"></i> <?= strtoupper($v_status) ?></span>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-user-md"></i> Doctor</div>
                <div class="info-value doctor"><?= htmlspecialchars($item['doctor_name'] ?? 'Not Assigned') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label"><i class="fas fa-user-tie"></i> Receptionist</div>
                <div class="info-value receptionist"><?= htmlspecialchars($item['receptionist_name'] ?? 'Not Assigned') ?></div>
            </div>
            <?php if (!empty($item['diagnosis'])): ?>
            <div class="info-item" style="grid-column: span 2;">
                <div class="info-label"><i class="fas fa-diagnoses"></i> Diagnosis</div>
                <div class="info-value diagnosis">
                    <?= htmlspecialchars($item['diagnosis']) ?>
                    <?php if (!empty($item['disease_code'])): ?>
                        <span style="font-size:0.65rem;color:var(--text-secondary);font-family:var(--font-mono);font-weight:600;margin-left:6px;background:var(--slate-bg);padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($item['disease_code']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($item['treatment'])): ?>
            <div class="info-item" style="grid-column: span 2;">
                <div class="info-label"><i class="fas fa-prescription-bottle-medical"></i> Treatment</div>
                <div class="info-value treatment"><?= nl2br(htmlspecialchars($item['treatment'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</main>

<?php if ($is_admin): ?>
<!-- DELETE MODAL (Admin Only) -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <h3 class="modal-title">Delete Bill Item?</h3>
        <p class="modal-text">
            Are you sure you want to delete<br>
            <strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong><br>
            <span class="modal-warning"><i class="fas fa-exclamation-circle"></i> This action cannot be undone!<br>The bill will be recalculated automatically.</span>
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="delete_item">
            <div class="modal-actions">
                <button type="button" class="modal-btn cancel" onclick="closeDeleteModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="modal-btn danger"><i class="fas fa-trash"></i> Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function confirmDeleteItem(id, name) {
    document.getElementById('deleteModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('deleteModal').addEventListener('click', function(e) { if (e.target === this) closeDeleteModal(); });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeDeleteModal(); });
</script>
<?php endif; ?>

<script>
console.log('%c📦 Bill Item Details - #<?= $item_id ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
console.log('%c👤 Role: <?= strtoupper($user_role) ?>', 'font-size:12px; color:#<?= $is_admin ? 'FCD34D' : '3B82F6' ?>; font-weight:bold;');
console.log('%c📝 Item: <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?>', 'font-size:12px; color:#059669; font-weight:bold;');
console.log('%c💰 Final Price: <?= $currency ?> <?= number_format($item_final, 0) ?>', 'font-size:12px; color:#7C3AED; font-weight:bold;');
console.log('%c📊 Type: <?= strtoupper($item_type) ?> | Status: <?= strtoupper($item_status) ?>', 'font-size:12px; color:#0891B2; font-weight:bold;');
<?php if ($is_audit): ?>
console.log('%c🔒 VIEW ONLY MODE - Hakuna Edit/Delete buttons', 'font-size:12px; color:#F59E0B; font-weight:bold;');
<?php endif; ?>
</script>

</body>
</html>