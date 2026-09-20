<?php
// ================================================================
// FILE: frontend/pages/admin/audit/all_procedures.php
// ADMIN - ALL PROCEDURES & EQUIPMENTS FOR A PATIENT
// ✅ Inaonyesha Procedures NA Equipments (kutoka bill_items)
// ✅ Group by Visit
// ✅ View / Edit / Delete buttons
// ✅ Bill info sahihi (paid/partial/pending)
// ✅ BLUE THEME + Table nav < >
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

$patient_id = (int)($_GET['patient_id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($patient_id <= 0) {
    header('Location: other_services.php?tab=procedures&branch=' . urlencode($selected_branch_id));
    exit;
}

require_once __DIR__ . '/../../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// CURRENCY
// ================================================================
$currency = 'TSh';
try {
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['setting_value'])) $currency = $row['setting_value'];
} catch (Exception $e) {}

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
        'pending'    => ['class' => 'warning', 'icon' => '⏳', 'label' => 'Pending'],
        'confirmed'  => ['class' => 'info',    'icon' => '🔵', 'label' => 'Confirmed'],
        'paid'       => ['class' => 'success', 'icon' => '✅', 'label' => 'Paid'],
        'dispensed'  => ['class' => 'cyan',    'icon' => '📦', 'label' => 'Dispensed'],
        'completed'  => ['class' => 'success', 'icon' => '✅', 'label' => 'Completed'],
        'cancelled'  => ['class' => 'danger',  'icon' => '❌', 'label' => 'Cancelled'],
        'partial'    => ['class' => 'purple',  'icon' => '⚡', 'label' => 'Partial'],
        'in_progress'=> ['class' => 'info',    'icon' => '🔄', 'label' => 'In Progress'],
    ];
    return $map[$status] ?? ['class' => 'warning', 'icon' => '❓', 'label' => ucfirst($status ?: 'Unknown')];
}

function formatTsh($amount) {
    return 'TSh ' . number_format((float)$amount, 0);
}

function getInitials($name) {
    if (empty($name)) return 'NA';
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

// ================================================================
// GET PATIENT
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
    header('Location: other_services.php?tab=procedures&branch=' . urlencode($selected_branch_id));
    exit;
}

// ================================================================
// ✅ FETCH DATA - Procedures + Equipments kutoka bill_items
// ================================================================
$visits_data = [];
$all_items = [];

try {
    $sql = "
        SELECT bi.*,
               bi.id as bill_item_id,
               bi.item_name as procedure_name,
               bi.unit_price as procedure_price,
               bi.total_price as procedure_total,
               bi.discount_amount as item_discount,
               bi.status as item_status,
               bi.created_at as item_created_at,
               bi.reference_id,
               bi.item_type,
               b.id as bill_id, b.bill_number, b.status as bill_status,
               b.total_amount as bill_total, b.paid_amount as bill_paid,
               b.balance as bill_balance, b.total_discount as bill_discount,
               b.premium_amount as bill_premium, b.payment_method,
               v.id as visit_id, v.visit_number, v.visit_date, 
               v.status as visit_status, v.diagnosis,
               doc.full_name as doctor_name,
               rec.full_name as receptionist_name,
               br.name as branch_name,
               pc.procedure_code, pc.category as procedure_category,
               pc.description as procedure_description
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users doc ON v.doctor_id = doc.id
        LEFT JOIN users rec ON v.receptionist_id = rec.id
        LEFT JOIN branches br ON bi.branch_id = br.id
        LEFT JOIN procedures_catalog pc ON (bi.item_type = 'procedure' AND bi.reference_id = pc.id)
        WHERE bi.patient_id = ?
          AND bi.item_type IN ('procedure', 'equipment')
          AND bi.status != 'cancelled'
    ";
    
    $params = [$patient_id];
    
    if ($selected_branch_id !== 'all') {
        $sql .= " AND bi.branch_id = ?";
        $params[] = (int)$selected_branch_id;
    }
    
    $sql .= " ORDER BY v.visit_date DESC, bi.item_type ASC, bi.id ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rows as $row) {
        $vid = $row['visit_id'] ?? 0;
        
        // Fetch payments kwa bill
        $received_by_name = null;
        $received_by_role = null;
        $payments_count = 0;
        
        if (($row['bill_paid'] ?? 0) > 0 && !empty($row['bill_id'])) {
            $stmt_pay = $db->prepare("
                SELECT u.full_name as received_by_name, u.role as received_by_role
                FROM payments p
                LEFT JOIN users u ON p.received_by = u.id
                WHERE p.bill_id = ?
                ORDER BY p.received_at DESC
                LIMIT 1
            ");
            $stmt_pay->execute([$row['bill_id']]);
            $pay = $stmt_pay->fetch(PDO::FETCH_ASSOC);
            
            if ($pay) {
                $received_by_name = $pay['received_by_name'];
                $received_by_role = $pay['received_by_role'];
            }
            
            $stmt_count = $db->prepare("SELECT COUNT(*) as c FROM payments WHERE bill_id = ?");
            $stmt_count->execute([$row['bill_id']]);
            $payments_count = $stmt_count->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
        }
        
        $row['received_by_name'] = $received_by_name;
        $row['received_by_role'] = $received_by_role;
        $row['payments_count'] = $payments_count;
        
        if (!isset($visits_data[$vid])) {
            $visits_data[$vid] = [
                'visit_id' => $vid,
                'visit_number' => $row['visit_number'] ?? 'N/A',
                'visit_date' => $row['visit_date'] ?? $row['item_created_at'],
                'doctor_name' => $row['doctor_name'] ?? 'N/A',
                'receptionist_name' => $row['receptionist_name'] ?? 'N/A',
                'branch_name' => $row['branch_name'] ?? 'N/A',
                'visit_status' => $row['visit_status'] ?? 'N/A',
                'diagnosis' => $row['diagnosis'] ?? '',
                'bill_id' => $row['bill_id'] ?? null,
                'bill_number' => $row['bill_number'] ?? null,
                'bill_status' => $row['bill_status'] ?? 'pending',
                'bill_total' => $row['bill_total'] ?? 0,
                'bill_paid' => $row['bill_paid'] ?? 0,
                'bill_balance' => $row['bill_balance'] ?? 0,
                'bill_discount' => $row['bill_discount'] ?? 0,
                'bill_premium' => $row['bill_premium'] ?? 0,
                'items' => [],
                'total_amount' => 0,
                'procedure_count' => 0,
                'equipment_count' => 0
            ];
        }
        
        $visits_data[$vid]['items'][] = $row;
        $visits_data[$vid]['total_amount'] += $row['procedure_total'] ?? 0;
        
        if ($row['item_type'] === 'equipment') {
            $visits_data[$vid]['equipment_count']++;
        } else {
            $visits_data[$vid]['procedure_count']++;
        }
        
        $all_items[] = $row;
    }
} catch (Exception $e) {
    error_log("Fetch error: " . $e->getMessage());
}

$visits_array = array_values($visits_data);

// ================================================================
// STATS
// ================================================================
$total_items = count($all_items);
$total_procedures = 0;
$total_equipments = 0;
$total_amount = 0;
$total_paid = 0;
$total_pending = 0;
$total_paid_count = 0;
$total_pending_count = 0;

foreach ($all_items as $item) {
    if ($item['item_type'] === 'equipment') {
        $total_equipments++;
    } else {
        $total_procedures++;
    }
    
    $price = (float)($item['procedure_price'] ?? 0);
    $status = $item['item_status'] ?? 'pending';
    
    $total_amount += $price;
    if (in_array($status, ['paid', 'completed'])) {
        $total_paid += $price;
        $total_paid_count++;
    } else {
        $total_pending += $price;
        $total_pending_count++;
    }
}

$total_visits = count($visits_array);
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
    <title>All Procedures & Equipments - <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></title>
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
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3A2A1A;
    --purple-bg: #2D1B4E;
    --cyan-bg: #0E3A47;
}
* { font-family: var(--font-primary); -webkit-font-smoothing: antialiased; box-sizing: border-box; }
html, body { font-family: var(--font-primary); background: var(--bg-body); color: var(--text-primary); margin: 0; }
.money-number, .money-cell, .font-mono { font-family: var(--font-mono) !important; font-variant-numeric: tabular-nums; letter-spacing: -0.02em; }

.page-header { background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%); border-radius: 16px; padding: 20px 24px; margin-bottom: 18px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px; box-shadow: 0 6px 20px rgba(11, 94, 215, 0.25); position: relative; overflow: hidden; }
.page-header::before { content: ''; position: absolute; top: -50%; right: -20%; width: 350px; height: 350px; background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%); border-radius: 50%; pointer-events: none; }
.page-header .page-title { color: white; font-size: 1.4rem; font-weight: 800; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; position: relative; z-index: 1; letter-spacing: -0.02em; margin: 0; }
.page-header .page-title i { font-size: 1.5rem; color: #93C5FD; }
.page-header .page-subtitle { color: rgba(255,255,255,0.85); font-size: 0.78rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px; position: relative; z-index: 1; }
.branch-tag { background: rgba(255,255,255,0.15); color: white; padding: 3px 10px; border-radius: 16px; font-size: 0.65rem; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.1); }
.branch-tag.admin-tag { background: linear-gradient(135deg, #FCD34D, #F59E0B); color: #78350F; font-weight: 800; }
.branch-tag.proc-tag { background: linear-gradient(135deg, #7C3AED, #6D28D9); font-weight: 700; }
.branch-tag.eqp-tag { background: linear-gradient(135deg, #06B6D4, #0891B2); font-weight: 700; }
.btn-header { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 8px 14px; border-radius: 9px; font-weight: 600; font-size: 0.75rem; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; backdrop-filter: blur(4px); position: relative; z-index: 1; cursor: pointer; }
.btn-header:hover { background: rgba(255,255,255,0.28); transform: translateY(-2px); color: white; }

.patient-card { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); padding: 18px 22px; margin-bottom: 18px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; box-shadow: var(--shadow-sm); position: relative; overflow: hidden; }
.patient-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #0B5ED7, #3B82F6); }
.patient-avatar-lg { width: 72px; height: 72px; border-radius: 16px; background: linear-gradient(135deg, #0B5ED7, #3B82F6); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 900; text-transform: uppercase; flex-shrink: 0; box-shadow: 0 8px 20px rgba(11, 94, 215, 0.3); border: 3px solid var(--bg-card); }
.patient-info-main { flex: 1; min-width: 250px; }
.patient-name-lg { font-size: 1.35rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.02em; line-height: 1.2; margin-bottom: 6px; }
.patient-meta-lg { display: flex; flex-wrap: wrap; gap: 8px; }
.patient-meta-lg .meta-pill { font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 4px; background: var(--bg-body); padding: 4px 10px; border-radius: 12px; border: 1px solid var(--border-color); }
.patient-meta-lg .meta-pill i { color: var(--primary); font-size: 0.68rem; }

.stats-grid-5 { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 18px; }
.stat-mini { background: var(--bg-card); border-radius: 12px; padding: 14px 16px; border: 2px solid var(--border-color); display: flex; align-items: center; gap: 12px; box-shadow: var(--shadow-sm); transition: all 0.3s ease; }
.stat-mini:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.stat-mini .icon-box { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: white; flex-shrink: 0; }
.stat-mini.blue .icon-box { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.stat-mini.purple .icon-box { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
.stat-mini.cyan .icon-box { background: linear-gradient(135deg, #06B6D4, #22D3EE); }
.stat-mini.green .icon-box { background: linear-gradient(135deg, #059669, #34D399); }
.stat-mini.orange .icon-box { background: linear-gradient(135deg, #D97706, #FBBF24); }
.stat-mini .stat-info .stat-lbl { font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-secondary); font-weight: 800; margin-bottom: 2px; }
.stat-mini .stat-info .stat-val { font-size: 1.15rem; font-weight: 900; color: var(--text-primary); font-family: var(--font-mono); letter-spacing: -0.03em; line-height: 1.1; }
.stat-mini.blue .stat-val { color: var(--primary); }
.stat-mini.purple .stat-val { color: var(--purple); }
.stat-mini.cyan .stat-val { color: var(--cyan); }
.stat-mini.green .stat-val { color: var(--success); }
.stat-mini.orange .stat-val { color: var(--warning); }

.visit-card { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); margin-bottom: 18px; overflow: hidden; box-shadow: var(--shadow-sm); transition: all 0.3s ease; }
.visit-card:hover { border-color: var(--primary); box-shadow: var(--shadow-md); }
.visit-header { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
.visit-header-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; flex: 1; }
.visit-icon-badge { width: 42px; height: 42px; border-radius: 11px; background: linear-gradient(135deg, #FCD34D, #F59E0B); color: #78350F; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35); flex-shrink: 0; }
.visit-number { font-family: var(--font-mono); font-size: 0.95rem; font-weight: 800; color: white; background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.25); }
.visit-meta-item { color: rgba(255,255,255,0.9); font-size: 0.72rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
.visit-header-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.visit-stat-pill { background: rgba(255,255,255,0.2); color: white; padding: 4px 12px; border-radius: 16px; font-size: 0.68rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; border: 1px solid rgba(255,255,255,0.15); }
.visit-stat-pill .num { font-family: var(--font-mono); font-weight: 900; font-size: 0.8rem; }
.visit-stat-pill.proc { background: rgba(124,58,237,0.4); }
.visit-stat-pill.eqp { background: rgba(6,182,212,0.4); }
.visit-stat-pill.paid { background: rgba(52,211,153,0.35); }
.visit-stat-pill.balance { background: rgba(220,38,38,0.35); }

.table-nav-group { display: inline-flex; align-items: center; gap: 2px; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); border-radius: 8px; padding: 2px; }
.table-nav-btn { background: rgba(255,255,255,0.15); border: none; color: white; width: 28px; height: 28px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 0.72rem; transition: all 0.2s ease; }
.table-nav-btn:hover:not(:disabled) { background: rgba(255,255,255,0.35); transform: scale(1.1); }
.table-nav-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.table-nav-indicator { font-size: 0.6rem; font-weight: 800; font-family: var(--font-mono); color: white; padding: 0 6px; min-width: 38px; text-align: center; background: rgba(255,255,255,0.15); border-radius: 5px; height: 24px; line-height: 24px; }

.table-wrapper { overflow-x: auto; scroll-behavior: smooth; }
.table-wrapper::-webkit-scrollbar { height: 8px; }
.table-wrapper::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 10px; }
.table-wrapper::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }

.data-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; min-width: 1200px; }
.data-table thead th { text-align: left; padding: 10px 12px; font-weight: 800; font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.06em; color: white; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); white-space: nowrap; }
.data-table tbody td { padding: 10px 12px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); vertical-align: middle; }
.data-table tbody tr:hover td { background: var(--primary-bg); }
.data-table tbody tr:last-child td { border-bottom: none; }

.status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 18px; font-size: 0.62rem; font-weight: 700; text-transform: uppercase; white-space: nowrap; }
.status-badge.warning { background: var(--warning-bg); color: var(--warning); }
.status-badge.success { background: var(--success-bg); color: var(--success); }
.status-badge.danger { background: var(--danger-bg); color: var(--danger); }
.status-badge.info { background: var(--primary-bg); color: var(--primary); }
.status-badge.purple { background: var(--purple-bg); color: var(--purple); }
.status-badge.cyan { background: var(--cyan-bg); color: var(--cyan); }

.action-group { display: flex; gap: 4px; align-items: center; justify-content: center; }
.btn-act { width: 30px; height: 30px; border-radius: 7px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; border: none; cursor: pointer; transition: all 0.25s ease; text-decoration: none; color: white; }
.btn-act:hover { transform: translateY(-2px); color: white; }
.btn-act.view { background: #0B5ED7; }
.btn-act.view:hover { background: #0A4CA8; box-shadow: 0 4px 10px rgba(11, 94, 215, 0.4); }
.btn-act.edit { background: #F59E0B; }
.btn-act.edit:hover { background: #D97706; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.4); }
.btn-act.delete { background: #DC2626; }
.btn-act.delete:hover { background: #B91C1C; box-shadow: 0 4px 10px rgba(220, 38, 38, 0.4); }

.money-cell { font-family: var(--font-mono); font-weight: 800; color: var(--primary); font-size: 0.82rem; text-align: right; white-space: nowrap; }
.money-cell.green { color: var(--success); }
.money-cell.red { color: var(--danger); }

.item-type-badge { display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 12px; font-size: 0.55rem; font-weight: 800; text-transform: uppercase; }
.item-type-badge.proc { background: var(--purple-bg); color: var(--purple); }
.item-type-badge.eqp { background: var(--cyan-bg); color: var(--cyan); }

.received-by-cell { display: flex; align-items: center; gap: 6px; }
.received-by-avatar { width: 24px; height: 24px; border-radius: 50%; background: linear-gradient(135deg, #059669, #34D399); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.55rem; flex-shrink: 0; font-family: var(--font-mono); }
.received-by-name { font-size: 0.68rem; font-weight: 700; color: var(--text-primary); }
.received-by-meta { font-size: 0.55rem; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); }

.empty-state { text-align: center; padding: 60px 20px; color: var(--text-secondary); background: var(--bg-card); border-radius: 14px; border: 2px dashed var(--border-color); }
.empty-state i { font-size: 3.5rem; color: var(--primary); display: block; margin-bottom: 12px; opacity: 0.4; }
.empty-state p { font-size: 1rem; font-weight: 700; color: var(--text-primary); }
.empty-state .sub { font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px; font-weight: 400; }

@media (max-width: 1200px) { .stats-grid-5 { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .stats-grid-5 { grid-template-columns: 1fr 1fr; gap: 10px; }
    .visit-header { padding: 12px 14px; }
    .visit-header-right { flex-direction: column; align-items: stretch; }
}
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-syringe"></i>
                Procedures & Equipments
                <span class="branch-tag admin-tag"><i class="fas fa-crown"></i> ADMIN</span>
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
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="other_services.php?tab=procedures&branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <!-- PATIENT CARD -->
    <div class="patient-card">
        <div class="patient-avatar-lg"><?= $initials ?></div>
        <div class="patient-info-main">
            <div class="patient-name-lg"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></div>
            <div class="patient-meta-lg">
                <span class="meta-pill"><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span>
                <?php if (!empty($patient['gender'])): ?>
                <span class="meta-pill"><i class="fas fa-<?= strtolower($patient['gender']) === 'female' ? 'venus' : 'mars' ?>"></i> <?= htmlspecialchars($patient['gender']) ?></span>
                <?php endif; ?>
                <?php if ($patient_age !== 'N/A'): ?>
                <span class="meta-pill"><i class="fas fa-birthday-cake"></i> <?= $patient_age ?></span>
                <?php endif; ?>
                <?php if (!empty($patient['phone'])): ?>
                <span class="meta-pill"><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone']) ?></span>
                <?php endif; ?>
                <?php if (!empty($patient['branch_name'])): ?>
                <span class="meta-pill"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($patient['branch_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats-grid-5">
        <div class="stat-mini blue">
            <div class="icon-box"><i class="fas fa-list"></i></div>
            <div class="stat-info">
                <div class="stat-lbl">Total Items</div>
                <div class="stat-val"><?= number_format($total_items) ?></div>
            </div>
        </div>
        <div class="stat-mini purple">
            <div class="icon-box"><i class="fas fa-syringe"></i></div>
            <div class="stat-info">
                <div class="stat-lbl">Procedures</div>
                <div class="stat-val"><?= number_format($total_procedures) ?></div>
            </div>
        </div>
        <div class="stat-mini cyan">
            <div class="icon-box"><i class="fas fa-tools"></i></div>
            <div class="stat-info">
                <div class="stat-lbl">Equipments</div>
                <div class="stat-val"><?= number_format($total_equipments) ?></div>
            </div>
        </div>
        <div class="stat-mini green">
            <div class="icon-box"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-lbl">Paid (<?= $total_paid_count ?>)</div>
                <div class="stat-val"><?= $currency ?> <?= number_format($total_paid, 0) ?></div>
            </div>
        </div>
        <div class="stat-mini orange">
            <div class="icon-box"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <div class="stat-lbl">Pending (<?= $total_pending_count ?>)</div>
                <div class="stat-val"><?= $currency ?> <?= number_format($total_pending, 0) ?></div>
            </div>
        </div>
    </div>

    <!-- VISITS -->
    <?php if (count($visits_array) > 0): ?>
        <?php foreach ($visits_array as $visit): 
            $vid = $visit['visit_id'];
            $visit_items = $visit['items'];
            $item_count = count($visit_items);
            $proc_count = $visit['procedure_count'];
            $eqp_count = $visit['equipment_count'];
            $visit_total = $visit['total_amount'] ?? 0;
            $uid = $patient_id . '-' . $vid;
            $visit_bill_s = getStatusBadge($visit['bill_status']);
        ?>
            <div class="visit-card">
                <div class="visit-header">
                    <div class="visit-header-left">
                        <div class="visit-icon-badge"><i class="fas fa-syringe"></i></div>
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
                        <?php if (!empty($visit['bill_number'])): ?>
                        <span class="visit-meta-item" style="background:rgba(255,255,255,0.15);padding:3px 10px;border-radius:12px;">
                            <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($visit['bill_number']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="visit-header-right">
                        <?php if ($proc_count > 0): ?>
                        <span class="visit-stat-pill proc">
                            <i class="fas fa-syringe"></i>
                            <span class="num"><?= $proc_count ?></span> Proc
                        </span>
                        <?php endif; ?>
                        <?php if ($eqp_count > 0): ?>
                        <span class="visit-stat-pill eqp">
                            <i class="fas fa-tools"></i>
                            <span class="num"><?= $eqp_count ?></span> Eqp
                        </span>
                        <?php endif; ?>
                        <span class="visit-stat-pill">
                            <i class="fas fa-money-bill-wave"></i>
                            <span class="num"><?= $currency ?> <?= number_format($visit_total, 0) ?></span>
                        </span>
                        <?php if (($visit['bill_paid'] ?? 0) > 0): ?>
                        <span class="visit-stat-pill paid">
                            <i class="fas fa-check"></i>
                            Paid: <span class="num"><?= $currency ?> <?= number_format($visit['bill_paid'], 0) ?></span>
                        </span>
                        <?php endif; ?>
                        <?php if (($visit['bill_balance'] ?? 0) > 0): ?>
                        <span class="visit-stat-pill balance">
                            <i class="fas fa-exclamation-triangle"></i>
                            Bal: <span class="num"><?= $currency ?> <?= number_format($visit['bill_balance'], 0) ?></span>
                        </span>
                        <?php endif; ?>
                        <span class="status-badge <?= $visit_bill_s['class'] ?>" style="background:rgba(255,255,255,0.25);color:white;font-size:0.6rem;">
                            <?= $visit_bill_s['icon'] ?> <?= $visit_bill_s['label'] ?>
                        </span>
                        <div class="table-nav-group">
                            <button type="button" class="table-nav-btn" onclick="scrollTable('table-<?= $uid ?>', -1)">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <span class="table-nav-indicator" id="indicator-<?= $uid ?>">0%</span>
                            <button type="button" class="table-nav-btn" onclick="scrollTable('table-<?= $uid ?>', 1)">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="table-wrapper" id="table-<?= $uid ?>">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:45px;">#</th>
                                <th>Item Name</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Doctor</th>
                                <th style="text-align:right;">Price</th>
                                <th style="text-align:right;">Discount</th>
                                <th style="text-align:right;">Total</th>
                                <th style="text-align:center;">Item Status</th>
                                <th style="text-align:center;">Bill Status</th>
                                <th>Received By</th>
                                <th>Date</th>
                                <th style="text-align:center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($visit_items as $item): 
                                $s = getStatusBadge($item['item_status'] ?? 'pending');
                                $is_eq = ($item['item_type'] === 'equipment');
                                $bill_item_id = $item['bill_item_id'] ?? 0;
                                $reference_id = $item['reference_id'] ?? 0;
                                
                                $rb_name = $item['received_by_name'] ?? null;
                                $rb_role = $item['received_by_role'] ?? null;
                                $payments_count = $item['payments_count'] ?? 0;
                                $rb_initials = getInitials($rb_name);
                            ?>
                                <tr>
                                    <td style="text-align:center;font-weight:700;"><?= $i++ ?></td>
                                    <td>
                                        <span style="font-weight:700;color:var(--primary);">
                                            <?= htmlspecialchars($item['procedure_name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="item-type-badge <?= $is_eq ? 'eqp' : 'proc' ?>">
                                            <i class="fas <?= $is_eq ? 'fa-tools' : 'fa-syringe' ?>"></i>
                                            <?= $is_eq ? 'Equipment' : 'Procedure' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge purple" style="font-size:0.55rem;">
                                            <?= htmlspecialchars($item['procedure_category'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-size:0.72rem;">
                                            <i class="fas fa-user-md" style="color:var(--cyan);"></i>
                                            <?= htmlspecialchars($item['doctor_name'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td class="money-cell"><?= formatTsh($item['procedure_price']) ?></td>
                                    <td class="money-cell <?= ($item['item_discount'] ?? 0) > 0 ? 'red' : '' ?>">
                                        <?= ($item['item_discount'] ?? 0) > 0 ? '-' . formatTsh($item['item_discount']) : '—' ?>
                                    </td>
                                    <td class="money-cell"><?= formatTsh($item['procedure_total']) ?></td>
                                    <td style="text-align:center;">
                                        <span class="status-badge <?= $s['class'] ?>">
                                            <?= $s['icon'] ?> <?= $s['label'] ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ($item['bill_id']): ?>
                                            <span class="status-badge <?= getStatusBadge($item['bill_status'])['class'] ?>" style="font-size:0.55rem;">
                                                <?= getStatusBadge($item['bill_status'])['icon'] ?> <?= getStatusBadge($item['bill_status'])['label'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="font-size:0.6rem;color:var(--text-secondary);font-style:italic;">No Bill</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($item['bill_paid'] ?? 0) > 0 && !empty($rb_name)): ?>
                                            <div class="received-by-cell">
                                                <div class="received-by-avatar"><?= htmlspecialchars($rb_initials) ?></div>
                                                <div>
                                                    <div class="received-by-name"><?= htmlspecialchars($rb_name) ?></div>
                                                    <div class="received-by-meta">
                                                        <?= htmlspecialchars(strtoupper($rb_role ?? 'user')) ?>
                                                        <?php if ($payments_count > 1): ?> • <?= $payments_count ?> pay<?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php elseif ($item['bill_status'] === 'partial'): ?>
                                            <span style="font-size:0.68rem;color:var(--purple);font-weight:700;">
                                                <i class="fas fa-hourglass-half"></i> Partial
                                            </span>
                                        <?php else: ?>
                                            <span style="font-size:0.68rem;color:var(--warning);font-weight:700;">
                                                <i class="fas fa-hourglass-half"></i> Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:0.7rem;"><?= date('d M Y', strtotime($item['item_created_at'] ?? $item['created_at'])) ?></td>
                                    <td style="text-align:center;">
                                        <div class="action-group">
                                            <a href="view_procedure.php?id=<?= $reference_id ?>&bill_item_id=<?= $bill_item_id ?>&type=<?= $item['item_type'] ?>&branch=<?= $selected_branch_id ?>" 
                                               class="btn-act view" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($is_admin): ?>
                                            <a href="edit_procedure.php?id=<?= $reference_id ?>&bill_item_id=<?= $bill_item_id ?>&type=<?= $item['item_type'] ?>&branch=<?= $selected_branch_id ?>" 
                                               class="btn-act edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php endif; ?>
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
            <i class="fas fa-syringe"></i>
            <p>No procedures or equipments found</p>
            <p class="sub">This patient has no procedures or equipments recorded yet</p>
        </div>
    <?php endif; ?>

</main>

<script>
function scrollTable(tableId, direction) {
    var table = document.getElementById(tableId);
    if (!table) return;
    
    var scrollAmount = 300;
    var currentScroll = table.scrollLeft;
    var maxScroll = table.scrollWidth - table.clientWidth;
    
    var newScroll = currentScroll + (direction * scrollAmount);
    if (newScroll < 0) newScroll = 0;
    if (newScroll > maxScroll) newScroll = maxScroll;
    
    table.scrollTo({ left: newScroll, behavior: 'smooth' });
    setTimeout(function() { updateTableNav(tableId); }, 350);
}

function updateTableNav(tableId) {
    var table = document.getElementById(tableId);
    if (!table) return;
    
    var uniqueId = tableId.replace('table-', '');
    var indicator = document.getElementById('indicator-' + uniqueId);
    
    var currentScroll = table.scrollLeft;
    var maxScroll = table.scrollWidth - table.clientWidth;
    
    var percent = 0;
    if (maxScroll > 0) percent = Math.round((currentScroll / maxScroll) * 100);
    if (indicator) indicator.textContent = percent + '%';
    
    var section = table.closest('.visit-card');
    if (section) {
        var btns = section.querySelectorAll('.table-nav-btn');
        if (btns.length >= 2) {
            btns[0].disabled = (currentScroll <= 1);
            btns[1].disabled = (currentScroll >= maxScroll - 1);
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.table-wrapper').forEach(function(tbl) {
        if (!tbl.id) return;
        tbl.addEventListener('scroll', function() { updateTableNav(tbl.id); });
        updateTableNav(tbl.id);
    });
});

console.log('%c💉 All Procedures & Equipments', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>', 'font-size:12px;color:#34D399;');
console.log('%c📊 Total: <?= $total_procedures ?> Procedures + <?= $total_equipments ?> Equipments', 'font-size:12px;color:#34D399;font-weight:bold;');
</script>

</body>
</html>