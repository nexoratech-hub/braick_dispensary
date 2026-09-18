<?php
// ================================================================
// FILE: frontend/pages/admin/audit/edit_prescription.php
// ADMIN AUDIT - EDIT PRESCRIPTION (V3 - Show 10 Rows on Open)
// ✅ Search filter kwenye kila dropdown
// ✅ Dropdown inafunguka na rows 10 za kwanza zinaonekana
// ✅ Branch ya mgonjwa kutoka visits table
// ✅ Load ALL prescriptions za visit_id husika
// ✅ Blue theme + dark mode
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $presc_id = (int)($_GET['id'] ?? 0);
    $v_id = (int)($_GET['visit_id'] ?? 0);
    $p_id = (int)($_GET['patient_id'] ?? 0);
    $b_id = $_GET['branch'] ?? 'all';
    header("Location: view_prescription.php?id=$presc_id&visit_id=$v_id&patient_id=$p_id&branch=$b_id");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$prescription_id = (int)($_GET['id'] ?? 0);
$visit_id = (int)($_GET['visit_id'] ?? 0);
$patient_id = (int)($_GET['patient_id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($prescription_id <= 0 && $visit_id <= 0) {
    header('Location: inventory.php?branch=' . urlencode($selected_branch_id));
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

function parseMoney($value) {
    if (is_numeric($value)) return (float)$value;
    $clean = preg_replace('/[^0-9.]/', '', (string)$value);
    return (float)($clean ?: 0);
}

// HANDLE UPDATE
$alert_message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_prescription') {
    try {
        $db->beginTransaction();
        
        if ($prescription_id > 0) {
            $doctor_id = !empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null;
            $status = $_POST['status'] ?? 'pending';
            $notes = trim($_POST['notes'] ?? '');
            
            $sql = "UPDATE prescriptions SET 
                doctor_id = ?, status = ?, notes = ?, updated_at = NOW()
                WHERE id = ?";
            $db->prepare($sql)->execute([$doctor_id, $status, $notes, $prescription_id]);
        }
        
        $item_ids = $_POST['item_id'] ?? [];
        $item_medication_ids = $_POST['item_medication_id'] ?? [];
        $item_names = $_POST['item_name'] ?? [];
        $item_quantities = $_POST['item_quantity'] ?? [];
        $item_prices = $_POST['item_price'] ?? [];
        $item_discounts = $_POST['item_discount'] ?? [];
        $item_dosages = $_POST['item_dosage'] ?? [];
        $item_frequencies = $_POST['item_frequency'] ?? [];
        $item_durations = $_POST['item_duration'] ?? [];
        $item_routes = $_POST['item_route'] ?? [];
        $item_instructions = $_POST['item_instructions'] ?? [];
        $item_prescription_ids = $_POST['item_prescription_id'] ?? [];
        
        foreach ($item_names as $index => $name) {
            if (empty(trim($name))) continue;
            
            $item_id = !empty($item_ids[$index]) ? (int)$item_ids[$index] : null;
            $medication_id = !empty($item_medication_ids[$index]) ? (int)$item_medication_ids[$index] : null;
            $qty = (int)($item_quantities[$index] ?? 1);
            $price = parseMoney($item_prices[$index] ?? '0');
            $disc = parseMoney($item_discounts[$index] ?? '0');
            $dosage = trim($item_dosages[$index] ?? '');
            $frequency = trim($item_frequencies[$index] ?? '');
            $duration = trim($item_durations[$index] ?? '');
            $route = trim($item_routes[$index] ?? '');
            $instructions = trim($item_instructions[$index] ?? '');
            $item_prescription_id = !empty($item_prescription_ids[$index]) ? (int)$item_prescription_ids[$index] : $prescription_id;
            
            $total_price = $qty * $price;
            
            if ($item_id) {
                $sql = "UPDATE prescription_items SET 
                    inventory_id = ?, medication_name = ?, dosage = ?, 
                    frequency = ?, quantity = ?, duration = ?, route = ?, 
                    instructions = ?, unit_price = ?, total_price = ?
                    WHERE id = ?";
                $db->prepare($sql)->execute([
                    $medication_id, $name, $dosage, $frequency, $qty, 
                    $duration, $route, $instructions, $price, $total_price, $item_id
                ]);
            } else {
                $sql = "INSERT INTO prescription_items 
                    (prescription_id, patient_id, inventory_id, medication_name, 
                     dosage, frequency, quantity, duration, route, instructions,
                     unit_price, total_price, branch_id, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $db->prepare($sql)->execute([
                    $item_prescription_id, $patient_id, $medication_id, $name,
                    $dosage, $frequency, $qty, $duration, $route, $instructions,
                    $price, $total_price, $selected_branch_id !== 'all' ? (int)$selected_branch_id : 1
                ]);
            }
        }
        
        $deleted_items = $_POST['deleted_items'] ?? '';
        if (!empty($deleted_items)) {
            $del_ids = array_filter(array_map('intval', explode(',', $deleted_items)));
            if (!empty($del_ids)) {
                $placeholders = implode(',', array_fill(0, count($del_ids), '?'));
                $db->prepare("DELETE FROM prescription_items WHERE id IN ($placeholders)")->execute($del_ids);
            }
        }
        
        try {
            $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) 
                          VALUES (?, ?, 'edit_prescription', ?, ?, NOW())")
               ->execute([
                   $user_id,
                   $selected_branch_id !== 'all' ? (int)$selected_branch_id : 1,
                   "Edited prescriptions for visit #$visit_id - " . count($item_names) . " items",
                   $_SERVER['REMOTE_ADDR'] ?? 'unknown'
               ]);
        } catch (Exception $e) {}
        
        $db->commit();
        
        $alert_message = "Prescription updated successfully!";
        $alert_type = 'success';
        
        header("refresh:2;url=view_prescription.php?id=$prescription_id&visit_id=$visit_id&patient_id=$patient_id&branch=" . urlencode($selected_branch_id));
        
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $alert_message = "Error updating prescription: " . $e->getMessage();
        $alert_type = 'error';
    }
}

// GET VISIT + PATIENT INFO
$prescription = null;
try {
    $sql = "SELECT 
        v.id as id,
        v.visit_number,
        v.visit_date,
        v.diagnosis,
        v.patient_id,
        v.doctor_id,
        v.branch_id,
        pat.full_name as patient_name,
        pat.patient_id as patient_number,
        pat.phone as patient_phone,
        pat.date_of_birth as patient_dob,
        pat.gender as patient_gender,
        pat.blood_group as patient_blood,
        pat.allergies as patient_allergies,
        u_doctor.full_name as doctor_name,
        b.name as branch_name,
        v.created_at
    FROM visits v
    LEFT JOIN patients pat ON v.patient_id = pat.id
    LEFT JOIN users u_doctor ON v.doctor_id = u_doctor.id
    LEFT JOIN branches b ON v.branch_id = b.id
    WHERE v.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$visit_id]);
    $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if (!$prescription) {
    header('Location: inventory.php?branch=' . urlencode($selected_branch_id));
    exit;
}

// ✅ GET PATIENT BRANCH FROM VISITS TABLE
$patient_branch_id = (int)($prescription['branch_id'] ?? 0);

if ($patient_branch_id <= 0 && $patient_id > 0) {
    try {
        $stmt = $db->prepare("SELECT branch_id FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $pat_branch = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($pat_branch && (int)$pat_branch['branch_id'] > 0) {
            $patient_branch_id = (int)$pat_branch['branch_id'];
        }
    } catch (Exception $e) {}
}

if ($patient_branch_id <= 0) {
    $patient_branch_id = (int)($_SESSION['branch_id'] ?? 1);
}

$patient_branch_name = $prescription['branch_name'] ?? $user_branch_name;

if ($prescription_id <= 0) {
    try {
        $stmt = $db->prepare("SELECT id FROM prescriptions WHERE visit_id = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$visit_id]);
        $first_rx = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($first_rx) $prescription_id = (int)$first_rx['id'];
    } catch (Exception $e) {}
}

// GET ALL PRESCRIPTIONS
$visit_prescriptions = [];
try {
    $sql = "SELECT p.id, p.prescription_number, p.doctor_id, p.status, p.notes, p.created_at,
        u.full_name as doctor_name
    FROM prescriptions p
    LEFT JOIN users u ON p.doctor_id = u.id
    WHERE p.visit_id = ?
    ORDER BY p.id ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$visit_id]);
    $visit_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// GET ALL PRESCRIPTION ITEMS
$prescription_items = [];
try {
    $sql = "SELECT 
        pi.id, pi.prescription_id, pi.inventory_id as medication_id,
        pi.medication_name, pi.dosage, pi.frequency, pi.quantity,
        pi.duration, pi.route, pi.instructions, pi.unit_price, pi.total_price,
        pi.branch_id, pi.created_at, p.prescription_number,
        p.status as rx_status, p.visit_id
    FROM prescription_items pi
    INNER JOIN prescriptions p ON pi.prescription_id = p.id
    WHERE p.visit_id = ?
    ORDER BY pi.id ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$visit_id]);
    $prescription_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// GET DOCTORS
$doctors_list = [];
try {
    $stmt = $db->query("SELECT id, full_name, specialty FROM users WHERE role = 'doctor' AND status = 'active' ORDER BY full_name");
    $doctors_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ✅ GET MEDICATIONS - KUTOKA BRANCH YA MGONJWA
$medications_list = [];
try {
    $sql = "SELECT 
        MIN(id) as id,
        medication_name,
        category,
        unit,
        MIN(selling_price) as selling_price,
        SUM(CASE WHEN status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') THEN quantity ELSE 0 END) as total_quantity
    FROM medications_inventory 
    WHERE status = 'active'
    AND branch_id = ?
    GROUP BY medication_name, category, unit
    HAVING total_quantity > 0
    ORDER BY medication_name ASC
    LIMIT 1000";
    $stmt = $db->prepare($sql);
    $stmt->execute([$patient_branch_id]);
    $medications_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fallback if empty
    if (empty($medications_list)) {
        $sql = "SELECT 
            MIN(id) as id,
            medication_name,
            category,
            unit,
            MIN(selling_price) as selling_price,
            SUM(CASE WHEN status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE() OR expiry_date = '0000-00-00') THEN quantity ELSE 0 END) as total_quantity
        FROM medications_inventory 
        WHERE status = 'active'
        GROUP BY medication_name, category, unit
        HAVING total_quantity > 0
        ORDER BY medication_name ASC
        LIMIT 1000";
        $stmt = $db->query($sql);
        $medications_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Medications fetch error: " . $e->getMessage());
}

function calculateAge($dob) {
    if (!$dob) return 'N/A';
    try { return (new DateTime($dob))->diff(new DateTime())->y . ' yrs'; } 
    catch (Exception $e) { return 'N/A'; }
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
    <title>Edit Prescription - <?= htmlspecialchars($prescription['visit_number'] ?? 'N/A') ?></title>
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
}
* { font-family: var(--font-primary); -webkit-font-smoothing: antialiased; }
html, body { font-family: var(--font-primary); background: var(--bg-body); color: var(--text-primary); }
.money-number, .money-cell, .font-mono, .money-input { font-family: var(--font-mono) !important; font-feature-settings: 'tnum'; letter-spacing: -0.02em; }

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
.branch-tag.count-tag { background: linear-gradient(135deg, #10B981, #059669); font-weight: 700; }

.btn-header { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.25); padding: 8px 14px; border-radius: 9px; font-weight: 600; font-size: 0.75rem; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; backdrop-filter: blur(4px); position: relative; z-index: 1; cursor: pointer; }
.btn-header:hover { background: rgba(255,255,255,0.28); transform: translateY(-2px); }

.form-card { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 18px; }
.form-card .card-header { padding: 12px 18px; background: var(--primary-bg); border-bottom: 2px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.form-card .card-header .title { font-size: 0.85rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
.form-card .card-header .title i { color: var(--primary); font-size: 0.95rem; }
.form-card .card-body { padding: 18px 20px; }
.form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group.full-width { grid-column: 1 / -1; }
.form-group label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); display: flex; align-items: center; gap: 5px; }
.form-group label i { color: var(--primary); font-size: 0.65rem; }
.form-group input, .form-group select, .form-group textarea { padding: 9px 12px; border-radius: 8px; border: 2px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-size: 0.82rem; font-weight: 600; outline: none; transition: all 0.3s ease; font-family: var(--font-primary); }
.form-group textarea { resize: vertical; min-height: 60px; font-weight: 500; }

.items-table-card { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 18px; }
.items-table-card .card-header { padding: 14px 20px; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
.items-table-card .card-header .title { color: white; font-size: 0.9rem; font-weight: 800; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.items-table-card .card-header .title i { color: #93C5FD; font-size: 1rem; }
.items-table-card .card-header .header-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.scroll-buttons { display: inline-flex; gap: 4px; background: rgba(255,255,255,0.15); border-radius: 10px; padding: 4px; border: 1px solid rgba(255,255,255,0.25); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.btn-scroll { width: 36px; height: 36px; border-radius: 8px; background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.15); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 800; transition: all 0.25s ease; }
.btn-scroll:hover { background: rgba(255,255,255,0.35); transform: scale(1.1); }

.btn-add-item { background: linear-gradient(135deg, #10B981, #059669); color: white; border: 2px solid rgba(255,255,255,0.3); padding: 8px 16px; border-radius: 10px; font-weight: 800; font-size: 0.75rem; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4); text-transform: uppercase; letter-spacing: 0.03em; }
.btn-add-item:hover { transform: translateY(-2px) scale(1.02); box-shadow: 0 6px 20px rgba(16, 185, 129, 0.6); }

.items-table-wrapper { overflow-x: auto; scroll-behavior: smooth; position: relative; }
.items-table-wrapper::-webkit-scrollbar { height: 10px; }
.items-table-wrapper::-webkit-scrollbar-track { background: var(--border-color); border-radius: 10px; margin: 0 8px; }
.items-table-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(90deg, #0B5ED7, #3B82F6); border-radius: 10px; border: 2px solid var(--border-color); }

.items-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; min-width: 1750px; }
.items-table thead th { text-align: left; padding: 10px 12px; font-weight: 800; font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.06em; color: white; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); white-space: nowrap; position: sticky; top: 0; z-index: 2; }
.items-table tbody td { padding: 8px 12px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); vertical-align: middle; background: var(--bg-card); overflow: visible; position: relative; }
.items-table tbody tr:hover td { background: var(--primary-bg); }
.items-table tbody tr.item-row { transition: all 0.3s ease; }
.items-table tbody tr.item-row.removing { opacity: 0.3; text-decoration: line-through; }

.items-table input[type="text"], .items-table input[type="number"] { width: 100%; padding: 6px 9px; border-radius: 6px; border: 2px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-size: 0.78rem; font-weight: 600; outline: none; transition: all 0.2s ease; font-family: var(--font-primary); }
.items-table input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15); }

.items-table .item-qty-input { width: 70px; text-align: center; }
.items-table .item-price-input, .items-table .item-disc-input { width: 120px; text-align: right; font-family: var(--font-mono) !important; font-weight: 700; letter-spacing: 0.02em; }
.items-table .item-total-cell { font-family: var(--font-mono); font-weight: 800; color: var(--primary); text-align: right; font-size: 0.82rem; white-space: nowrap; }

.rx-badge { display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 6px; background: var(--primary-bg); color: var(--primary); font-family: var(--font-mono); font-size: 0.62rem; font-weight: 800; white-space: nowrap; }
[data-theme="dark"] .rx-badge { background: #1E3A5F; color: #93C5FD; }

.btn-remove-item { width: 30px; height: 30px; border-radius: 8px; background: rgba(220, 38, 38, 0.12); color: #DC2626; border: 2px solid transparent; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 0.78rem; transition: all 0.2s ease; }
.btn-remove-item:hover { background: #DC2626; color: white; transform: scale(1.1); box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4); }

/* ================================================================
   ✅ SEARCHABLE MEDICATION DROPDOWN (SHOW 10 ROWS ON OPEN)
   ================================================================ */
.med-search-dropdown {
    position: relative;
    width: 100%;
    min-width: 240px;
}

.med-selected-display {
    width: 100%;
    padding: 6px 32px 6px 9px;
    border-radius: 6px;
    border: 2px solid var(--primary-light);
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    text-overflow: ellipsis;
    white-space: nowrap;
    overflow: hidden;
    min-height: 32px;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
    position: relative;
}

.med-selected-display:hover {
    border-color: var(--primary);
    background: var(--primary-bg);
}

.med-selected-display .med-display-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.med-selected-display .med-arrow {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--primary);
    font-size: 0.7rem;
    pointer-events: none;
    transition: transform 0.2s ease;
}

.med-search-dropdown.open .med-arrow {
    transform: translateY(-50%) rotate(180deg);
}

.med-selected-display.placeholder {
    color: var(--text-secondary);
    font-weight: 500;
    font-style: italic;
    background: var(--primary-bg);
    border-color: var(--primary-light);
}

/* ✅ PANEL - max-height inaonyesha rows ~10 */
.med-dropdown-panel {
    position: fixed;
    z-index: 99999;
    background: var(--bg-card);
    border: 2px solid var(--primary);
    border-radius: 12px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.25);
    display: none;
    flex-direction: column;
    overflow: hidden;
    animation: medDropdownFadeIn 0.15s ease;
}

@keyframes medDropdownFadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}

.med-dropdown-panel.show { display: flex; }

.med-dropdown-search {
    position: relative;
    padding: 10px;
    background: var(--primary-bg);
    border-bottom: 2px solid var(--primary-light);
    flex-shrink: 0;
}

.med-dropdown-search i {
    position: absolute;
    left: 20px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--primary);
    font-size: 0.8rem;
    pointer-events: none;
    z-index: 2;
}

.med-dropdown-search input {
    width: 100%;
    padding: 9px 12px 9px 34px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 0.82rem;
    font-weight: 600;
    outline: none;
    font-family: var(--font-primary);
    transition: all 0.2s ease;
}

.med-dropdown-search input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
}

/* ✅ LIST - inaonyesha rows 10 (kila row = 42px) */
.med-dropdown-list {
    overflow-y: auto;
    max-height: 420px; /* ✅ 10 rows × 42px = 420px */
    flex: 1;
}

.med-dropdown-list::-webkit-scrollbar { width: 10px; }
.med-dropdown-list::-webkit-scrollbar-track { 
    background: var(--bg-body); 
    border-radius: 10px; 
}
.med-dropdown-list::-webkit-scrollbar-thumb { 
    background: linear-gradient(180deg, var(--primary), var(--primary-dark)); 
    border-radius: 10px; 
    border: 2px solid var(--bg-body);
}
.med-dropdown-list::-webkit-scrollbar-thumb:hover { 
    background: var(--primary-dark); 
}

/* ✅ OPTION - kila row ina fixed height */
.med-option {
    padding: 8px 14px;
    cursor: pointer;
    transition: all 0.15s ease;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    font-size: 0.8rem;
    min-height: 42px;
    height: 42px;
    box-sizing: border-box;
    overflow: hidden;
}

.med-option:last-child { border-bottom: none; }

.med-option:hover,
.med-option.selected {
    background: var(--primary-bg);
    padding-left: 18px;
    border-left: 4px solid var(--primary);
}

.med-option.current {
    background: var(--success-bg);
    font-weight: 700;
    border-left: 4px solid var(--success);
}

.med-option .med-opt-name {
    font-weight: 700;
    color: var(--text-primary);
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.82rem;
}

.med-option .med-opt-name i {
    color: var(--primary);
    font-size: 0.75rem;
    flex-shrink: 0;
}

.med-option .med-opt-info {
    font-size: 0.7rem;
    color: var(--text-secondary);
    display: flex;
    gap: 10px;
    flex-shrink: 0;
    align-items: center;
}

.med-option .med-opt-stock {
    font-weight: 800;
    font-family: var(--font-mono);
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 0.72rem;
}

.med-option .med-opt-stock.ok { color: var(--success); }
.med-option .med-opt-stock.low { color: var(--warning); }
.med-option .med-opt-stock.out { color: var(--danger); }

.med-option .med-opt-price {
    font-weight: 800;
    font-family: var(--font-mono);
    color: var(--primary);
    white-space: nowrap;
    font-size: 0.78rem;
}

.med-option.empty {
    padding: 30px 20px;
    text-align: center;
    color: var(--text-secondary);
    cursor: default;
    height: auto;
    min-height: auto;
}

.med-option.empty:hover {
    background: transparent;
    border-left: none;
    padding-left: 14px;
}

.med-option.hidden {
    display: none;
}

.med-dropdown-footer {
    padding: 8px 14px;
    background: var(--primary-bg);
    border-top: 1px solid var(--primary-light);
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--primary);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.med-dropdown-footer .count {
    background: var(--primary);
    color: white;
    padding: 2px 10px;
    border-radius: 10px;
    font-family: var(--font-mono);
    font-size: 0.65rem;
}

.med-dropdown-footer .scroll-hint-mini {
    font-size: 0.6rem;
    color: var(--text-secondary);
    font-weight: 500;
}

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
.total-row .value { font-family: var(--font-mono); font-weight: 800; font-size: 0.9rem; color: var(--text-primary); letter-spacing: -0.02em; }
.total-row .value.blue { color: var(--primary); }
.total-row .value.red { color: var(--danger); }
.total-row.grand { background: linear-gradient(135deg, var(--primary-bg), var(--primary-bg)); border-top: 3px solid var(--primary); border-bottom: 3px solid var(--primary); padding: 16px 20px; margin: 6px 0; }
.total-row.grand .label { color: var(--primary); font-size: 1rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; }
.total-row.grand .value { color: var(--primary); font-size: 1.35rem; font-weight: 900; }

.action-bar { background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-color); padding: 16px 20px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; box-shadow: var(--shadow-sm); position: sticky; bottom: 20px; z-index: 10; }
.action-bar .action-info { display: flex; align-items: center; gap: 12px; }
.action-bar .action-info .info-icon { width: 42px; height: 42px; border-radius: 12px; background: var(--primary-bg); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
.action-bar .action-info .info-text .info-title { font-size: 0.85rem; font-weight: 800; color: var(--text-primary); }
.action-bar .action-info .info-text .info-sub { font-size: 0.7rem; color: var(--text-secondary); font-weight: 500; }
.action-bar .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }

.btn { padding: 10px 18px; border-radius: 10px; font-weight: 700; font-size: 0.8rem; border: none; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 7px; text-decoration: none; white-space: nowrap; }
.btn:hover { transform: translateY(-2px); }
.btn-save { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
.btn-save:hover { box-shadow: 0 6px 20px rgba(11, 94, 215, 0.5); }
.btn-back { background: var(--bg-card); color: var(--text-primary); border: 2px solid var(--border-color); }
.btn-back:hover { border-color: var(--primary); color: var(--primary); }
.btn-view { background: var(--bg-card); color: var(--primary); border: 2px solid var(--primary); }
.btn-view:hover { background: var(--primary); color: white; }

.branch-banner { background: linear-gradient(135deg, #DBEAFE, #BFDBFE); border: 2px solid #3B82F6; border-radius: 12px; padding: 12px 18px; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; color: #1E40AF; font-size: 0.82rem; font-weight: 600; }
[data-theme="dark"] .branch-banner { background: linear-gradient(135deg, #1E3A5F, #1E40AF); color: #BFDBFE; border-color: #3B82F6; }
.branch-banner i { font-size: 1.2rem; background: #3B82F6; color: white; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .form-grid { grid-template-columns: 1fr; }
    .items-table { font-size: 0.72rem; }
    .items-table thead th, .items-table tbody td { padding: 6px 8px; }
    .action-bar { padding: 12px 14px; flex-direction: column; align-items: stretch; }
    .btn { padding: 8px 14px; font-size: 0.75rem; }
    .btn-scroll { width: 32px; height: 32px; font-size: 0.8rem; }
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
        <?php if ($alert_type === 'success'): ?>
        <script>
            setTimeout(function() {
                window.location.href = 'view_prescription.php?id=<?= $prescription_id ?>&visit_id=<?= $visit_id ?>&patient_id=<?= $patient_id ?>&branch=<?= urlencode($selected_branch_id) ?>';
            }, 2000);
        </script>
        <?php endif; ?>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-edit"></i>
                Edit Prescription
                <span class="branch-tag admin-tag"><i class="fas fa-crown"></i> ADMIN</span>
                <span class="branch-tag count-tag">
                    <i class="fas fa-pills"></i> <?= count($prescription_items) ?> Items
                </span>
                <span class="branch-tag" style="background:linear-gradient(135deg,#7C3AED,#6D28D9);">
                    <i class="fas fa-prescription"></i> <?= count($visit_prescriptions) ?> Rx
                </span>
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
            <a href="view_prescription.php?id=<?= $prescription_id ?>&visit_id=<?= $visit_id ?>&patient_id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" 
               class="btn-header">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ✅ BRANCH INFO BANNER -->
    <div class="branch-banner">
        <i class="fas fa-store-alt"></i>
        <div>
            <strong>Branch ya Mgonjwa: <?= htmlspecialchars($patient_branch_name) ?> (ID: <?= $patient_branch_id ?>)</strong>
            <span style="font-weight:500;opacity:0.85;margin-left:8px;">
                • Dawa zote zinazoonyeshwa hapa ni za branch hii TU (<?= count($medications_list) ?> available)
            </span>
        </div>
    </div>

    <form method="POST" id="editPrescriptionForm">
        <input type="hidden" name="action" value="update_prescription">
        <input type="hidden" name="deleted_items" id="deletedItems" value="">

        <div class="form-card">
            <div class="card-header">
                <span class="title"><i class="fas fa-info-circle"></i> Prescription Information</span>
                <span style="font-size:0.68rem;color:var(--text-secondary);font-weight:700;">
                    <i class="fas fa-clock"></i> Created: <?= date('d M Y, H:i', strtotime($prescription['created_at'] ?? 'now')) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    
                    <div class="form-group">
                        <label><i class="fas fa-user-md"></i> Doctor</label>
                        <select name="doctor_id">
                            <option value="">Select Doctor</option>
                            <?php foreach ($doctors_list as $doc): ?>
                                <option value="<?= $doc['id'] ?>" <?= ($prescription['doctor_id'] ?? 0) == $doc['id'] ? 'selected' : '' ?>>
                                    Dr. <?= htmlspecialchars($doc['full_name']) ?>
                                    <?= !empty($doc['specialty']) ? '(' . htmlspecialchars($doc['specialty']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-flag"></i> Status</label>
                        <select name="status">
                            <option value="pending">Pending</option>
                            <option value="confirmed" selected>Confirmed</option>
                            <option value="dispensed">Dispensed</option>
                            <option value="paid">Paid</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label><i class="fas fa-sticky-note"></i> Notes</label>
                        <textarea name="notes" rows="2" placeholder="Additional notes..."><?= htmlspecialchars($prescription['notes'] ?? '') ?></textarea>
                    </div>
                    
                </div>
            </div>
        </div>

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
                    <button type="button" class="btn-add-item" onclick="addNewItem()">
                        <i class="fas fa-plus"></i> Add Medication
                    </button>
                </div>
            </div>
            
            <div class="items-table-wrapper" id="itemsWrapper">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th style="width:150px;">Rx #</th>
                            <th style="width:280px;">💊 Medication (Search & Change)</th>
                            <th style="width:80px;text-align:center;">Qty</th>
                            <th style="width:120px;text-align:right;">Unit Price</th>
                            <th style="width:120px;text-align:right;">Discount</th>
                            <th style="width:110px;text-align:right;">Total</th>
                            <th style="width:130px;">📋 Dosage</th>
                            <th style="width:130px;">⏰ Frequency</th>
                            <th style="width:110px;">📅 Duration</th>
                            <th style="width:110px;">🛣️ Route</th>
                            <th style="width:160px;">📝 Instructions</th>
                            <th style="width:50px;text-align:center;">Remove</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        <?php if (count($prescription_items) > 0): ?>
                            <?php $item_num = 1; foreach ($prescription_items as $item): 
                                $current_med_id = (int)($item['medication_id'] ?? 0);
                                $current_med_name = $item['medication_name'] ?? '';
                                $item_rx_number = $item['prescription_number'] ?? 'N/A';
                                $item_rx_id = (int)($item['prescription_id'] ?? 0);
                            ?>
                                <tr class="item-row" data-item-id="<?= $item['id'] ?>">
                                    <td style="text-align:center;font-weight:700;color:var(--text-secondary);" class="row-num"><?= $item_num++ ?></td>
                                    <td>
                                        <span class="rx-badge">
                                            <i class="fas fa-prescription"></i>
                                            <?= htmlspecialchars($item_rx_number) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <input type="hidden" name="item_id[]" value="<?= $item['id'] ?>">
                                        <input type="hidden" name="item_prescription_id[]" value="<?= $item_rx_id ?>">
                                        <input type="hidden" name="item_name[]" class="item-name-hidden" value="<?= htmlspecialchars($current_med_name) ?>">
                                        <input type="hidden" name="item_medication_id[]" class="item-medication-id-hidden" value="<?= $current_med_id ?>">
                                        
                                        <div class="med-search-dropdown" data-current-id="<?= $current_med_id ?>" data-current-name="<?= htmlspecialchars($current_med_name) ?>">
                                            <div class="med-selected-display <?= $current_med_name ? '' : 'placeholder' ?>">
                                                <span class="med-display-name"><?= $current_med_name ?: '-- Select Medication --' ?></span>
                                                <i class="fas fa-chevron-down med-arrow"></i>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" name="item_quantity[]" class="item-qty-input" 
                                               value="<?= (int)($item['quantity'] ?? 1) ?>" min="1"
                                               oninput="recalculate()">
                                    </td>
                                    <td>
                                        <input type="text" name="item_price[]" class="item-price-input money-input" 
                                               value="<?= number_format((float)($item['unit_price'] ?? 0), 0) ?>" 
                                               inputmode="numeric" autocomplete="off">
                                    </td>
                                    <td>
                                        <input type="text" name="item_discount[]" class="item-disc-input money-input" 
                                               value="0" inputmode="numeric" autocomplete="off">
                                    </td>
                                    <td class="item-total-cell">
                                        <?= $currency ?> <span class="item-total-value"><?= number_format((float)($item['total_price'] ?? 0), 0) ?></span>
                                    </td>
                                    <td>
                                        <input type="text" name="item_dosage[]" value="<?= htmlspecialchars($item['dosage'] ?? '') ?>" placeholder="e.g. 500mg">
                                    </td>
                                    <td>
                                        <input type="text" name="item_frequency[]" value="<?= htmlspecialchars($item['frequency'] ?? '') ?>" placeholder="e.g. 2x daily">
                                    </td>
                                    <td>
                                        <input type="text" name="item_duration[]" value="<?= htmlspecialchars($item['duration'] ?? '') ?>" placeholder="e.g. 7 days">
                                    </td>
                                    <td>
                                        <input type="text" name="item_route[]" value="<?= htmlspecialchars($item['route'] ?? '') ?>" placeholder="e.g. Oral">
                                    </td>
                                    <td>
                                        <input type="text" name="item_instructions[]" value="<?= htmlspecialchars($item['instructions'] ?? '') ?>" placeholder="Instructions...">
                                    </td>
                                    <td style="text-align:center;">
                                        <button type="button" class="btn-remove-item" onclick="removeItem(this)" title="Remove from edit">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" style="text-align:center;padding:40px 20px;color:var(--text-secondary);">
                                    <i class="fas fa-inbox" style="font-size:2rem;opacity:0.3;display:block;margin-bottom:8px;"></i>
                                    <p style="font-weight:600;">No medications found for this visit</p>
                                    <p style="font-size:0.75rem;opacity:0.8;">Click "Add Medication" to start</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="scroll-hint">
                <i class="fas fa-arrows-alt-h"></i>
                Tumia <kbd>‹</kbd> <kbd>›</kbd> buttons kuslide kushoto/kulia 
                <span style="opacity:0.5;">•</span>
                Keyboard: <kbd>Alt</kbd> + <kbd>←</kbd> / <kbd>→</kbd>
                <span style="opacity:0.5;">•</span>
                <strong>🔍 Search inaonekana unapofungua dropdown</strong>
            </div>
        </div>

        <div class="totals-card">
            <div class="card-header">
                <i class="fas fa-calculator"></i> Prescription Summary
            </div>
            <div class="totals-body">
                <div class="total-row">
                    <span class="label"><i class="fas fa-cubes"></i> Total Items</span>
                    <span class="value blue" id="totalItemsValue"><?= count($prescription_items) ?></span>
                </div>
                
                <div class="total-row">
                    <span class="label"><i class="fas fa-sort-numeric-up"></i> Total Quantity</span>
                    <span class="value blue" id="totalQtyValue">0</span>
                </div>
                
                <div class="total-row">
                    <span class="label"><i class="fas fa-receipt"></i> Subtotal</span>
                    <span class="value"><span style="font-size:0.72rem;color:var(--text-secondary);"><?= $currency ?></span> <span id="subtotalValue">0</span></span>
                </div>
                
                <div class="total-row">
                    <span class="label"><i class="fas fa-tags"></i> Total Discount</span>
                    <span class="value red">- <span style="font-size:0.72rem;color:var(--text-secondary);"><?= $currency ?></span> <span id="discountValue">0</span></span>
                </div>
                
                <div class="total-row grand">
                    <span class="label"><i class="fas fa-money-bill-wave"></i> Total Amount</span>
                    <span class="value"><span style="font-size:0.85rem;"><?= $currency ?></span> <span id="grandTotalValue">0</span></span>
                </div>
            </div>
        </div>

        <div class="action-bar">
            <div class="action-info">
                <div class="info-icon"><i class="fas fa-edit"></i></div>
                <div class="info-text">
                    <div class="info-title">Save Changes</div>
                    <div class="info-sub">Total changes will be logged in audit trail</div>
                </div>
            </div>
            <div class="action-buttons">
                <a href="view_prescription.php?id=<?= $prescription_id ?>&visit_id=<?= $visit_id ?>&patient_id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" 
                   class="btn btn-view">
                    <i class="fas fa-eye"></i> View
                </a>
                <a href="inventory.php?branch=<?= $selected_branch_id ?>" class="btn btn-back">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn btn-save">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>

    </form>

</main>

<!-- TEMPLATE FOR NEW ITEM -->
<template id="newItemTemplate">
    <tr class="item-row">
        <td style="text-align:center;font-weight:700;color:var(--text-secondary);" class="row-num">-</td>
        <td>
            <span class="rx-badge" style="background:linear-gradient(135deg,#F59E0B,#D97706);color:white;">
                <i class="fas fa-plus"></i> NEW
            </span>
        </td>
        <td>
            <input type="hidden" name="item_id[]" value="">
            <input type="hidden" name="item_prescription_id[]" value="<?= $prescription_id ?>">
            <input type="hidden" name="item_name[]" class="item-name-hidden" value="">
            <input type="hidden" name="item_medication_id[]" class="item-medication-id-hidden" value="">
            
            <div class="med-search-dropdown" data-current-id="0" data-current-name="">
                <div class="med-selected-display placeholder">
                    <span class="med-display-name">-- Select Medication --</span>
                    <i class="fas fa-chevron-down med-arrow"></i>
                </div>
            </div>
        </td>
        <td><input type="number" name="item_quantity[]" class="item-qty-input" value="1" min="1" oninput="recalculate()"></td>
        <td><input type="text" name="item_price[]" class="item-price-input money-input" value="0" inputmode="numeric" autocomplete="off"></td>
        <td><input type="text" name="item_discount[]" class="item-disc-input money-input" value="0" inputmode="numeric" autocomplete="off"></td>
        <td class="item-total-cell"><?= $currency ?> <span class="item-total-value">0</span></td>
        <td><input type="text" name="item_dosage[]" value="" placeholder="e.g. 500mg"></td>
        <td><input type="text" name="item_frequency[]" value="" placeholder="e.g. 2x daily"></td>
        <td><input type="text" name="item_duration[]" value="" placeholder="e.g. 7 days"></td>
        <td><input type="text" name="item_route[]" value="" placeholder="e.g. Oral"></td>
        <td><input type="text" name="item_instructions[]" value="" placeholder="Instructions..."></td>
        <td style="text-align:center;">
            <button type="button" class="btn-remove-item" onclick="removeItem(this)" title="Remove">
                <i class="fas fa-times"></i>
            </button>
        </td>
    </tr>
</template>

<!-- ✅ MEDICATIONS DATA -->
<script id="medicationsDataScript" type="application/json">
<?= json_encode(array_map(function($med) {
    return [
        'id' => (int)$med['id'],
        'name' => $med['medication_name'],
        'price' => (float)$med['selling_price'],
        'qty' => (int)$med['total_quantity'],
        'unit' => $med['unit'] ?? '',
        'category' => $med['category'] ?? ''
    ];
}, $medications_list), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>

<script>
// ================================================================
// VARIABLES
// ================================================================
var currency = '<?= $currency ?>';
var deletedItems = [];
var MEDICATIONS = [];
var PATIENT_BRANCH_ID = <?= (int)$patient_branch_id ?>;

try {
    var script = document.getElementById('medicationsDataScript');
    if (script) MEDICATIONS = JSON.parse(script.textContent);
} catch (e) {
    console.error('Failed to parse medications:', e);
    MEDICATIONS = [];
}

console.log('%c💊 Medications loaded: ' + MEDICATIONS.length + ' (Branch ID: ' + PATIENT_BRANCH_ID + ')', 'color:#0B5ED7;font-weight:bold;');

// ================================================================
// ✅ OPEN MEDICATION DROPDOWN - Shows 10 rows
// ================================================================
function openMedDropdown(dropdown) {
    closeAllMedDropdowns();
    
    var display = dropdown.querySelector('.med-selected-display');
    var rect = display.getBoundingClientRect();
    
    var panel = document.createElement('div');
    panel.className = 'med-dropdown-panel';
    panel.dataset.owner = 'med-dropdown-' + Math.random().toString(36).substring(2, 10);
    dropdown.dataset.panelId = panel.dataset.owner;
    
    // Position panel
    panel.style.left = rect.left + 'px';
    panel.style.top = (rect.bottom + 4) + 'px';
    panel.style.width = Math.max(rect.width, 360) + 'px';
    
    // Search
    var searchHtml = `
        <div class="med-dropdown-search">
            <i class="fas fa-search"></i>
            <input type="text" class="med-dropdown-search-input" placeholder="Search medication by name, category..." autocomplete="off">
        </div>
    `;
    
    // List
    var listHtml = '<div class="med-dropdown-list" data-list="' + panel.dataset.owner + '">';
    
    if (MEDICATIONS.length === 0) {
        listHtml += '<div class="med-option empty"><i class="fas fa-pills" style="font-size:1.5rem;opacity:0.3;display:block;margin-bottom:8px;"></i><p style="font-weight:600;">No medications available</p></div>';
    } else {
        var currentId = parseInt(dropdown.dataset.currentId) || 0;
        
        MEDICATIONS.forEach(function(med) {
            var stockClass = med.qty > 10 ? 'ok' : (med.qty > 0 ? 'low' : 'out');
            var isCurrent = (currentId > 0 && currentId === med.id);
            
            listHtml += `
                <div class="med-option ${isCurrent ? 'current' : ''}" 
                     data-med-id="${med.id}"
                     data-med-name="${escapeHtml(med.name)}"
                     data-med-price="${med.price}"
                     data-med-qty="${med.qty}"
                     data-search="${escapeHtml((med.name + ' ' + med.category).toLowerCase())}">
                    <div class="med-opt-name">
                        <i class="fas fa-pills"></i>
                        ${escapeHtml(med.name)}
                    </div>
                    <div class="med-opt-info">
                        <span class="med-opt-stock ${stockClass}">
                            <i class="fas fa-cubes"></i> ${med.qty}
                        </span>
                        <span class="med-opt-price">${currency} ${numberFormat(med.price)}</span>
                    </div>
                </div>
            `;
        });
    }
    
    listHtml += '</div>';
    
    // Footer
    var footerHtml = `
        <div class="med-dropdown-footer">
            <span><i class="fas fa-list"></i> <span class="med-visible-count">${MEDICATIONS.length}</span> / ${MEDICATIONS.length} meds</span>
            <span class="scroll-hint-mini">
                <i class="fas fa-mouse"></i> Scroll for more
            </span>
        </div>
    `;
    
    panel.innerHTML = searchHtml + listHtml + footerHtml;
    document.body.appendChild(panel);
    panel.classList.add('show');
    
    dropdown.classList.add('open');
    
    // Auto focus on search
    setTimeout(function() {
        var searchInput = panel.querySelector('.med-dropdown-search-input');
        if (searchInput) searchInput.focus();
    }, 50);
    
    // Search events
    var searchInput = panel.querySelector('.med-dropdown-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterMedOptions(panel, this.value);
        });
        
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeMedDropdown(panel);
            else if (e.key === 'Enter') {
                var firstVisible = panel.querySelector('.med-option:not(.hidden):not(.empty)');
                if (firstVisible) firstVisible.click();
            }
        });
    }
    
    // Option click
    panel.querySelectorAll('.med-option:not(.empty)').forEach(function(opt) {
        opt.addEventListener('click', function() {
            selectMedication(dropdown, this);
            closeMedDropdown(panel);
        });
    });
    
    // Adjust position if off-screen
    var panelRect = panel.getBoundingClientRect();
    if (panelRect.bottom > window.innerHeight - 20) {
        panel.style.maxHeight = (window.innerHeight - rect.bottom - 30) + 'px';
    }
    
    window.currentMedPanel = panel;
}

// ================================================================
// CLOSE DROPDOWN
// ================================================================
function closeMedDropdown(panel) {
    if (panel) panel.remove();
    document.querySelectorAll('.med-search-dropdown.open').forEach(function(d) {
        d.classList.remove('open');
    });
    window.currentMedPanel = null;
}

function closeAllMedDropdowns() {
    document.querySelectorAll('.med-dropdown-panel').forEach(function(p) { p.remove(); });
    document.querySelectorAll('.med-search-dropdown.open').forEach(function(d) { d.classList.remove('open'); });
    window.currentMedPanel = null;
}

// ================================================================
// FILTER MEDS
// ================================================================
function filterMedOptions(panel, query) {
    var term = (query || '').toLowerCase().trim();
    var options = panel.querySelectorAll('.med-option:not(.empty)');
    var visibleCount = 0;
    
    options.forEach(function(opt) {
        var searchData = opt.getAttribute('data-search') || '';
        if (term === '' || searchData.indexOf(term) !== -1) {
            opt.classList.remove('hidden');
            opt.style.display = 'flex';
            visibleCount++;
        } else {
            opt.classList.add('hidden');
            opt.style.display = 'none';
        }
    });
    
    var countEl = panel.querySelector('.med-visible-count');
    if (countEl) countEl.textContent = visibleCount;
}

// ================================================================
// SELECT MEDICATION
// ================================================================
function selectMedication(dropdown, option) {
    var medId = option.getAttribute('data-med-id') || '';
    var medName = option.getAttribute('data-med-name') || '';
    var medPrice = parseFloat(option.getAttribute('data-med-price')) || 0;
    var medQty = parseInt(option.getAttribute('data-med-qty')) || 0;
    
    dropdown.dataset.currentId = medId;
    dropdown.dataset.currentName = medName;
    
    var display = dropdown.querySelector('.med-selected-display');
    var nameEl = display.querySelector('.med-display-name');
    if (nameEl) nameEl.textContent = medName;
    display.classList.remove('placeholder');
    
    var row = dropdown.closest('tr');
    var nameHidden = row.querySelector('.item-name-hidden');
    var idHidden = row.querySelector('.item-medication-id-hidden');
    
    if (nameHidden) nameHidden.value = medName;
    if (idHidden) idHidden.value = medId;
    
    var priceInput = row.querySelector('.item-price-input');
    if (priceInput) priceInput.value = formatMoney(medPrice);
    
    if (medQty <= 0) {
        display.style.borderColor = '#DC2626';
        display.style.background = '#FEE2E2';
        display.style.color = '#DC2626';
    } else {
        display.style.borderColor = '';
        display.style.background = '';
        display.style.color = '';
    }
    
    recalculate();
}

// ================================================================
// UTILITIES
// ================================================================
function formatMoney(value) {
    var cleaned = String(value).replace(/[^0-9.]/g, '');
    var parts = cleaned.split('.');
    var integerPart = parts[0];
    var decimalPart = parts.length > 1 ? parts[1] : '';
    integerPart = integerPart.replace(/^0+/, '') || '0';
    integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    if (decimalPart) return integerPart + '.' + decimalPart;
    return integerPart;
}

function parseMoney(value) {
    if (typeof value === 'number') return value;
    var cleaned = String(value).replace(/[^0-9.]/g, '');
    return parseFloat(cleaned) || 0;
}

function numberFormat(num) {
    num = Math.round(num || 0);
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function attachMoneyFormat(input) {
    if (!input || input.dataset.moneyAttached === '1') return;
    input.dataset.moneyAttached = '1';
    
    if (input.value && input.value !== '0') input.value = formatMoney(input.value);
    
    input.addEventListener('input', function() {
        var cursorPos = this.selectionStart;
        var oldLength = this.value.length;
        var formatted = formatMoney(this.value);
        this.value = formatted;
        var diff = formatted.length - oldLength;
        if (this.setSelectionRange) this.setSelectionRange(cursorPos + diff, cursorPos + diff);
        recalculate();
    });
    
    input.addEventListener('blur', function() {
        if (this.value === '' || this.value === '.') this.value = '0';
        else this.value = formatMoney(this.value);
        recalculate();
    });
    
    input.addEventListener('focus', function() {
        var self = this;
        setTimeout(function() { self.select(); }, 10);
    });
    
    input.addEventListener('keypress', function(e) {
        var char = String.fromCharCode(e.which);
        if (!/[0-9.]/.test(char)) e.preventDefault();
    });
}

function initMoneyInputs() {
    document.querySelectorAll('.money-input').forEach(function(input) {
        attachMoneyFormat(input);
    });
}

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
    
    document.querySelectorAll('.btn-scroll').forEach(function(btn) {
        btn.style.transform = 'scale(0.9)';
        setTimeout(function() { btn.style.transform = ''; }, 150);
    });
}

function recalculate() {
    var rows = document.querySelectorAll('#itemsTableBody tr.item-row');
    var subtotal = 0;
    var totalDiscount = 0;
    var totalQty = 0;
    var itemCount = 0;
    
    rows.forEach(function(row) {
        if (row.classList.contains('removing') || row.style.display === 'none') return;
        
        var qtyInput = row.querySelector('.item-qty-input');
        var priceInput = row.querySelector('.item-price-input');
        var discInput = row.querySelector('.item-disc-input');
        
        if (!qtyInput || !priceInput) return;
        
        var qty = parseFloat(qtyInput.value) || 0;
        var price = parseMoney(priceInput.value);
        var disc = discInput ? parseMoney(discInput.value) : 0;
        
        var rowTotal = qty * price;
        var rowFinal = rowTotal - disc;
        
        subtotal += rowTotal;
        totalDiscount += disc;
        totalQty += qty;
        itemCount++;
        
        var totalEl = row.querySelector('.item-total-value');
        if (totalEl) totalEl.textContent = numberFormat(rowFinal);
    });
    
    var grandTotal = subtotal - totalDiscount;
    if (grandTotal < 0) grandTotal = 0;
    
    var subtotalEl = document.getElementById('subtotalValue');
    var discountEl = document.getElementById('discountValue');
    var grandTotalEl = document.getElementById('grandTotalValue');
    var totalQtyEl = document.getElementById('totalQtyValue');
    var totalItemsEl = document.getElementById('totalItemsValue');
    
    if (subtotalEl) subtotalEl.textContent = numberFormat(subtotal);
    if (discountEl) discountEl.textContent = numberFormat(totalDiscount);
    if (grandTotalEl) grandTotalEl.textContent = numberFormat(grandTotal);
    if (totalQtyEl) totalQtyEl.textContent = numberFormat(totalQty);
    if (totalItemsEl) totalItemsEl.textContent = numberFormat(itemCount);
    
    var num = 1;
    rows.forEach(function(row) {
        if (!row.classList.contains('removing') && row.style.display !== 'none') {
            var numEl = row.querySelector('.row-num');
            if (numEl) numEl.textContent = num++;
        }
    });
}

function addNewItem() {
    var tbody = document.getElementById('itemsTableBody');
    var template = document.getElementById('newItemTemplate');
    
    if (!template) return;
    
    var emptyRow = tbody.querySelector('tr td[colspan]');
    if (emptyRow) emptyRow.closest('tr').remove();
    
    var newRow = template.content.cloneNode(true).querySelector('tr');
    tbody.appendChild(newRow);
    
    newRow.querySelectorAll('.money-input').forEach(function(input) {
        attachMoneyFormat(input);
    });
    
    var dropdown = newRow.querySelector('.med-search-dropdown');
    if (dropdown) {
        setTimeout(function() {
            openMedDropdown(dropdown);
        }, 100);
    }
    
    setTimeout(function() {
        newRow.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
    }, 100);
    
    recalculate();
}

function removeItem(btn) {
    var row = btn.closest('tr.item-row');
    var itemId = row.querySelector('input[name="item_id[]"]').value;
    var nameHidden = row.querySelector('.item-name-hidden');
    var itemName = nameHidden ? (nameHidden.value || 'this item') : 'this item';
    
    if (itemId) {
        if (!confirm('Are you sure you want to remove "' + itemName + '" from this prescription?')) return;
        
        deletedItems.push(itemId);
        document.getElementById('deletedItems').value = deletedItems.join(',');
        row.classList.add('removing');
        
        row.querySelectorAll('input, select').forEach(function(el) {
            el.disabled = true;
        });
        
        setTimeout(function() {
            row.style.transition = 'all 0.3s ease';
            row.style.opacity = '0';
            row.style.height = '0';
            setTimeout(function() {
                row.style.display = 'none';
                recalculate();
            }, 300);
        }, 100);
    } else {
        row.remove();
        recalculate();
    }
}

// ================================================================
// EVENT LISTENERS
// ================================================================
document.addEventListener('click', function(e) {
    if (!e.target.closest('.med-search-dropdown') && !e.target.closest('.med-dropdown-panel')) {
        closeAllMedDropdowns();
    }
});

document.addEventListener('click', function(e) {
    var display = e.target.closest('.med-selected-display');
    if (display) {
        e.preventDefault();
        e.stopPropagation();
        var dropdown = display.closest('.med-search-dropdown');
        if (dropdown) {
            if (dropdown.classList.contains('open')) {
                closeAllMedDropdowns();
            } else {
                openMedDropdown(dropdown);
            }
        }
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeAllMedDropdowns();
    if (e.altKey && e.key === 'ArrowLeft') { e.preventDefault(); scrollItems('left'); }
    if (e.altKey && e.key === 'ArrowRight') { e.preventDefault(); scrollItems('right'); }
});

window.addEventListener('scroll', function() {
    if (window.currentMedPanel) {
        var dropdown = document.querySelector('.med-search-dropdown.open');
        if (dropdown) {
            var display = dropdown.querySelector('.med-selected-display');
            var rect = display.getBoundingClientRect();
            window.currentMedPanel.style.left = rect.left + 'px';
            window.currentMedPanel.style.top = (rect.bottom + 4) + 'px';
        }
    }
}, true);

document.getElementById('editPrescriptionForm').addEventListener('submit', function(e) {
    var visibleItems = document.querySelectorAll('#itemsTableBody tr.item-row:not(.removing)');
    var hasValidItem = false;
    
    visibleItems.forEach(function(row) {
        if (row.style.display === 'none') return;
        var idHidden = row.querySelector('.item-medication-id-hidden');
        if (idHidden && idHidden.value) hasValidItem = true;
    });
    
    if (!hasValidItem) {
        e.preventDefault();
        alert('Prescription must have at least one medication selected!');
        return false;
    }
    
    if (!confirm('Are you sure you want to save these changes?')) {
        e.preventDefault();
        return false;
    }
});

document.addEventListener('DOMContentLoaded', function() {
    initMoneyInputs();
    recalculate();
});

console.log('%c✏️ Edit Prescription V3 - Show 10 Rows on Open', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Dropdown inafunguka na rows 10 za kwanza', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ Search filter kwenye kila dropdown', 'font-size:13px; color:#34D399;');
console.log('%c✅ Branch ya Mgonjwa: <?= htmlspecialchars($patient_branch_name) ?> (ID: <?= $patient_branch_id ?>)', 'font-size:13px; color:#F59E0B; font-weight:bold;');
console.log('%c💊 Meds: ' + MEDICATIONS.length, 'font-size:13px; color:#60A5FA;');
</script>

</body>
</html>