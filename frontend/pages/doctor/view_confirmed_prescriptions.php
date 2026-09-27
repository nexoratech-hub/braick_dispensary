<?php
// ================================================================
// FILE: frontend/pages/doctor/view_confirmed_prescriptions.php
// DOCTOR - VIEW CONFIRMED PRESCRIPTIONS (READ-ONLY)
// ================================================================
// ✅ Shows CONFIRMED prescriptions only (status = 'confirmed')
// ✅ Single table for entire visit
// ✅ Medication-only bill summary
// ✅ Doctor = VIEW ONLY (no confirm/dispense/delete)
// ✅ Fixed: prescription_items HAINA 'status' - use timestamps
// ✅ BLUE THEME (confirmed = info)
// ================================================================

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../login.php');
    exit;
}

$user_id          = $_SESSION['user_id'];
$user_full_name   = $_SESSION['full_name'] ?? 'Doctor';
$user_branch_id   = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$profile_pic      = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $db->exec("SET time_zone = '+03:00'");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
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
function formatDate($datetime, $showTime = true) {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') return 'N/A';
    $ts = strtotime($datetime);
    if ($ts === false) return 'N/A';
    return $showTime ? date('d M Y, H:i', $ts) : date('d M Y', $ts);
}

function timeAgo($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') return 'N/A';
    $ts = strtotime($datetime);
    if ($ts === false) return 'N/A';
    $diff = time() - $ts;
    if ($diff < 60) return 'Just now';
    elseif ($diff < 3600) { $m = floor($diff / 60); return $m . ' min' . ($m > 1 ? 's' : '') . ' ago'; }
    elseif ($diff < 86400) { $h = floor($diff / 3600); return $h . ' hour' . ($h > 1 ? 's' : '') . ' ago'; }
    elseif ($diff < 604800) { $d = floor($diff / 86400); return $d . ' day' . ($d > 1 ? 's' : '') . ' ago'; }
    else return date('d M Y', $ts);
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    try { return (new DateTime($dob))->diff(new DateTime('today'))->y; }
    catch (Exception $e) { return 'N/A'; }
}

function getStatusBadgeClass($status) {
    $status = strtolower($status ?? 'pending');
    $map = [
        'pending'   => 'badge-warning',
        'confirmed' => 'badge-info',
        'dispensed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-warning';
}

function getStatusLabel($status) {
    $status = strtolower($status ?? 'pending');
    $map = [
        'pending'   => '⏳ PENDING',
        'confirmed' => '✅ CONFIRMED',
        'dispensed' => '💊 DISPENSED',
        'cancelled' => '❌ CANCELLED'
    ];
    return $map[$status] ?? strtoupper($status);
}

/**
 * ✅ prescription_items HAINA 'status' - use timestamps
 */
function getItemStatus($item, $prescription_status = 'pending') {
    if (!empty($item['cancelled_at'])) return 'cancelled';
    if (!empty($item['dispensed_at'])) return 'dispensed';
    if (!empty($item['confirmed_at'])) return 'confirmed';
    if (!empty($prescription_status)) return strtolower($prescription_status);
    return 'pending';
}

/**
 * ✅ MEDICATION-ONLY BILL CALCULATION
 */
function calculateMedicationBill(PDO $db, int $bill_id): array {
    $result = [
        'med_subtotal'       => 0,
        'med_discount'       => 0,
        'med_total'          => 0,
        'pharmacy_discount'  => 0,
        'pharmacy_premium'   => 0,
        'net_total'          => 0,
        'item_count'         => 0,
    ];

    if ($bill_id <= 0) return $result;

    $stmt = $db->prepare("
        SELECT
            COALESCE(SUM(total_price), 0) AS subtotal,
            COALESCE(SUM(discount_amount), 0) AS discount,
            COUNT(*) AS item_count
        FROM bill_items
        WHERE bill_id = ?
          AND item_type = 'medication'
          AND status != 'cancelled'
    ");
    $stmt->execute([$bill_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $result['med_subtotal'] = (float)($row['subtotal'] ?? 0);
    $result['med_discount'] = (float)($row['discount'] ?? 0);
    $result['med_total']    = $result['med_subtotal'] - $result['med_discount'];
    $result['item_count']   = (int)($row['item_count'] ?? 0);

    $stmt = $db->prepare("
        SELECT pharmacy_discount, pharmacy_premium
        FROM bills
        WHERE id = ?
    ");
    $stmt->execute([$bill_id]);
    $bill_row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($bill_row) {
        $result['pharmacy_discount'] = (float)($bill_row['pharmacy_discount'] ?? 0);
        $result['pharmacy_premium']  = (float)($bill_row['pharmacy_premium'] ?? 0);
    }

    $result['net_total'] = $result['med_total']
                         - $result['pharmacy_discount']
                         + $result['pharmacy_premium'];

    if ($result['net_total'] < 0) $result['net_total'] = 0;

    return $result;
}

// ================================================================
// PARAMETERS
// ================================================================
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$visit_id   = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$back_url   = 'view_prescriptions.php';

if ($patient_id <= 0) {
    header("Location: $back_url");
    exit;
}

// ================================================================
// FETCH PATIENT
// ================================================================
$patient = null;
try {
    $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if (!$patient) die("Patient not found.");

// ================================================================
// FETCH VISIT
// ================================================================
$visit = null;
if ($visit_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT v.*,
                   u.full_name AS doctor_name,
                   r.full_name AS receptionist_name,
                   br.name AS branch_name
            FROM visits v
            LEFT JOIN users u ON v.doctor_id = u.id
            LEFT JOIN users r ON v.receptionist_id = r.id
            LEFT JOIN branches br ON v.branch_id = br.id
            WHERE v.id = ? AND v.patient_id = ?
        ");
        $stmt->execute([$visit_id, $patient_id]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// ================================================================
// ✅ FETCH CONFIRMED PRESCRIPTIONS ONLY
// ================================================================
$prescriptions = [];
try {
    $presc_where = "p.patient_id = ? AND p.branch_id = ? AND p.status = 'confirmed'";
    $presc_params = [$patient_id, $user_branch_id];
    if ($visit_id > 0) {
        $presc_where .= " AND p.visit_id = ?";
        $presc_params[] = $visit_id;
    }

    $stmt = $db->prepare("
        SELECT p.*,
               u.full_name AS doctor_name,
               v.visit_number, v.visit_date
        FROM prescriptions p
        LEFT JOIN users u ON p.doctor_id = u.id
        LEFT JOIN visits v ON p.visit_id = v.id
        WHERE $presc_where
          AND EXISTS (SELECT 1 FROM prescription_items pi WHERE pi.prescription_id = p.id AND pi.quantity > 0)
        ORDER BY p.updated_at DESC, p.created_at DESC
    ");
    $stmt->execute($presc_params);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// FETCH ALL ITEMS
// ================================================================
$items_by_prescription = [];
$all_items = [];

if (!empty($prescriptions)) {
    $presc_ids = array_column($prescriptions, 'id');
    $ph = implode(',', array_fill(0, count($presc_ids), '?'));

    $presc_status_map = [];
    foreach ($prescriptions as $p) {
        $presc_status_map[$p['id']] = strtolower($p['status'] ?? 'confirmed');
    }

    try {
        $stmt = $db->prepare("
            SELECT
                pi.*,
                mi.quantity AS current_stock,
                mi.unit AS stock_unit,
                u1.full_name AS confirmed_by_name,
                u2.full_name AS dispensed_by_name,
                u3.full_name AS cancelled_by_name
            FROM prescription_items pi
            LEFT JOIN medications_inventory mi ON pi.inventory_id = mi.id
            LEFT JOIN users u1 ON pi.confirmed_by = u1.id
            LEFT JOIN users u2 ON pi.dispensed_by = u2.id
            LEFT JOIN users u3 ON pi.cancelled_by = u3.id
            WHERE pi.prescription_id IN ($ph)
              AND pi.quantity > 0
            ORDER BY pi.id ASC
        ");
        $stmt->execute($presc_ids);
        $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($all_items as &$item) {
            $item['prescription_status'] = $presc_status_map[$item['prescription_id']] ?? 'confirmed';
        }
        unset($item);

        foreach ($all_items as $item) {
            $items_by_prescription[$item['prescription_id']][] = $item;
        }
    } catch (Exception $e) {
        try {
            $stmt = $db->prepare("
                SELECT
                    pi.*,
                    mi.quantity AS current_stock,
                    mi.unit AS stock_unit
                FROM prescription_items pi
                LEFT JOIN medications_inventory mi ON pi.inventory_id = mi.id
                WHERE pi.prescription_id IN ($ph)
                  AND pi.quantity > 0
                ORDER BY pi.id ASC
            ");
            $stmt->execute($presc_ids);
            $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($all_items as &$item) {
                $item['prescription_status'] = $presc_status_map[$item['prescription_id']] ?? 'confirmed';
            }
            unset($item);

            foreach ($all_items as $item) {
                $items_by_prescription[$item['prescription_id']][] = $item;
            }
        } catch (Exception $e2) {
            $stmt = $db->prepare("
                SELECT pi.*
                FROM prescription_items pi
                WHERE pi.prescription_id IN ($ph)
                  AND pi.quantity > 0
                ORDER BY pi.id ASC
            ");
            $stmt->execute($presc_ids);
            $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($all_items as &$item) {
                $item['prescription_status'] = $presc_status_map[$item['prescription_id']] ?? 'confirmed';
            }
            unset($item);

            foreach ($all_items as $item) {
                $items_by_prescription[$item['prescription_id']][] = $item;
            }
        }
    }
}

// ================================================================
// BUILD UNIFIED ITEMS (single table for entire visit)
// ================================================================
$unified_items = [];
$grand_qty = 0;
$grand_total = 0;

foreach ($prescriptions as $presc) {
    $presc_id = $presc['id'];
    $presc_status = strtolower($presc['status'] ?? 'confirmed');
    $presc_items = $items_by_prescription[$presc_id] ?? [];

    foreach ($presc_items as $item) {
        $item['_prescription_number'] = $presc['prescription_number'];
        $item['_prescription_status'] = $presc_status;
        $item['_prescription_created'] = $presc['created_at'];
        $item['_prescription_updated'] = $presc['updated_at'];
        $item['_prescription_doctor'] = $presc['doctor_name'] ?? 'N/A';
        $unified_items[] = $item;

        $grand_qty += (int)($item['quantity'] ?? 0);
        $grand_total += (float)($item['unit_price'] ?? 0) * (int)($item['quantity'] ?? 0);
    }
}

$visit_doctors = [];
foreach ($prescriptions as $p) {
    if (!empty($p['doctor_name']) && !in_array($p['doctor_name'], $visit_doctors)) {
        $visit_doctors[] = $p['doctor_name'];
    }
}
$visit_doctors_str = !empty($visit_doctors) ? implode(', ', $visit_doctors) : 'N/A';

// Visit header info
$visit_header_number = $visit['visit_number'] ?? 'N/A';
$visit_header_date = $visit['visit_date'] ?? $visit['created_at'] ?? null;
$visit_header_date_str = !empty($visit_header_date) ? date('d M Y, H:i', strtotime($visit_header_date)) : 'N/A';

// Latest confirmed time
$latest_confirmed = null;
foreach ($prescriptions as $p) {
    if (!empty($p['updated_at'])) {
        if ($latest_confirmed === null || strtotime($p['updated_at']) > strtotime($latest_confirmed)) {
            $latest_confirmed = $p['updated_at'];
        }
    }
}

$unique_prescriptions = count($prescriptions);

// ================================================================
// MEDICATION-ONLY BILL
// ================================================================
$bill = null;
$med_bill = [
    'med_subtotal'      => 0,
    'med_discount'      => 0,
    'med_total'         => 0,
    'pharmacy_discount' => 0,
    'pharmacy_premium'  => 0,
    'net_total'         => 0,
    'item_count'        => 0,
];

if ($visit_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT b.*
            FROM bills b
            WHERE b.patient_id = ?
              AND b.visit_id = ?
              AND b.branch_id = ?
              AND b.status != 'cancelled'
            ORDER BY b.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$patient_id, $visit_id, $user_branch_id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($bill && $bill['id']) {
            $med_bill = calculateMedicationBill($db, (int)$bill['id']);
        }
    } catch (Exception $e) {}
}

// ================================================================
// STATISTICS
// ================================================================
$total_items = count($unified_items);
$total_qty = $grand_qty;
$confirmed_count = 0;

foreach ($unified_items as $item) {
    $st = getItemStatus($item, $item['_prescription_status'] ?? 'confirmed');
    if ($st === 'confirmed') $confirmed_count++;
}

$profile_pic_url = !empty($profile_pic) ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/doctor_header.php';
include_once '../../components/doctor_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Confirmed Prescriptions - <?= htmlspecialchars($patient['full_name']) ?></title>
<link rel="icon" href="<?= $logo_path ?>" type="image/png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --font-primary: 'Inter', -apple-system, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #6EA8FE;
    --primary-bg: #E8F0FE;
    --info: #3B82F6;
    --info-bg: #DBEAFE;
    --success: #059669;
    --success-dark: #047857;
    --success-bg: #D1FAE5;
    --danger: #DC2626;
    --danger-bg: #FEE2E2;
    --warning: #D97706;
    --warning-bg: #FEF3C7;
    --purple: #7C3AED;
    --purple-bg: #EDE9FE;
    --gray-50: #F8FAFC;
    --gray-200: #E2E8F0;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --radius: 10px;
    --radius-lg: 14px;
    --shadow: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
    --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
}
[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --purple-bg: #2D1B4E;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: var(--font-primary);
    background: var(--bg-body);
    color: var(--text-primary);
    -webkit-font-smoothing: antialiased;
}
.mono, .patient-id, .qty-number, .money, .date-mono { font-family: var(--font-mono) !important; font-variant-numeric: tabular-nums; }
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: var(--bg-body); }
::-webkit-scrollbar-thumb { background: var(--info); border-radius: 10px; }

.main-content { margin-left: 270px; margin-top: 68px; padding: 24px 28px; min-height: calc(100vh - 68px); }

/* ============ PAGE HEADER (BLUE - CONFIRMED THEME) ============ */
.page-header {
    background: linear-gradient(135deg, #3B82F6, #2563EB, #1D4ED8);
    border-radius: 16px;
    padding: 20px 28px;
    margin-bottom: 16px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    box-shadow: 0 4px 20px rgba(59, 130, 246, 0.35);
    position: relative;
    overflow: hidden;
}
.page-header::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 300px; height: 300px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
}
.page-header .page-title {
    color: white;
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}
.page-header .page-title i { font-size: 1.6rem; opacity: 0.9; }
.page-header .page-subtitle {
    color: rgba(255,255,255,0.85);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin-top: 6px;
}
.page-header .role-badge-display {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.6rem;
    font-weight: 600;
    text-transform: uppercase;
    backdrop-filter: blur(4px);
}
.page-header .branch-tag {
    background: rgba(255,255,255,0.15);
    color: white;
    padding: 2px 12px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    backdrop-filter: blur(4px);
}
.page-header .btn-outline-light {
    background: rgba(255,255,255,0.12);
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 8px 16px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.78rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    backdrop-filter: blur(4px);
    position: relative;
    z-index: 1;
    cursor: pointer;
    transition: all 0.25s;
}
.page-header .btn-outline-light:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
}

/* ============ READ-ONLY NOTICE (BLUE) ============ */
.readonly-notice {
    background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
    border-left: 4px solid var(--info);
    border-radius: var(--radius);
    padding: 12px 18px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.8rem;
    color: #1E40AF;
    font-weight: 600;
}
[data-theme="dark"] .readonly-notice {
    background: linear-gradient(135deg, #1E3A5F, #16294A);
    color: #93C5FD;
}
.readonly-notice i { font-size: 1.2rem; color: var(--info); }

/* ============ PATIENT INFO CARD (BLUE) ============ */
.patient-info-card {
    background: linear-gradient(135deg, #3B82F6, #2563EB);
    border-radius: var(--radius-lg);
    padding: 18px 22px;
    color: white;
    margin-bottom: 16px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
    position: relative;
    overflow: hidden;
}
.patient-info-card::before {
    content: '';
    position: absolute;
    top: -60%; right: -10%;
    width: 250px; height: 250px;
    background: rgba(255,255,255,0.08);
    border-radius: 50%;
}
.patient-info-card .patient-left {
    display: flex;
    align-items: center;
    gap: 14px;
    position: relative;
    z-index: 1;
}
.patient-info-card .avatar-circle {
    width: 60px; height: 60px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    border: 3px solid rgba(255,255,255,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    font-weight: 900;
    flex-shrink: 0;
    font-family: var(--font-mono);
}
.patient-info-card .p-name {
    font-size: 1.25rem;
    font-weight: 800;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.patient-info-card .p-meta {
    display: flex;
    gap: 10px;
    font-size: 0.72rem;
    flex-wrap: wrap;
}
.patient-info-card .p-meta-item {
    background: rgba(255,255,255,0.15);
    padding: 3px 10px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    backdrop-filter: blur(4px);
}
.patient-info-card .p-stats {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}
.patient-info-card .p-stat {
    background: rgba(255,255,255,0.18);
    padding: 8px 14px;
    border-radius: 10px;
    text-align: center;
    border: 1px solid rgba(255,255,255,0.2);
    min-width: 90px;
}
.patient-info-card .p-stat-label {
    font-size: 0.58rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
    opacity: 0.85;
}
.patient-info-card .p-stat-value {
    font-size: 1rem;
    font-weight: 900;
    font-family: var(--font-mono);
    margin-top: 2px;
}

/* ============ VISIT INFO BAR ============ */
.visit-info-bar {
    background: var(--bg-card);
    border: 2px solid var(--info);
    border-radius: var(--radius-lg);
    padding: 12px 18px;
    margin-bottom: 16px;
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    align-items: center;
    box-shadow: var(--shadow);
}
.visit-info-bar .vi-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.visit-info-bar .vi-label {
    font-size: 0.58rem;
    text-transform: uppercase;
    font-weight: 700;
    color: var(--text-secondary);
    letter-spacing: 0.05em;
}
.visit-info-bar .vi-value {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-primary);
    font-family: var(--font-mono);
}
.visit-info-bar .vi-value.blue { color: var(--info); }

/* ============ PRESCRIPTION CARD (BLUE) ============ */
.prescription-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 2px solid var(--info);
    margin-bottom: 16px;
    overflow: hidden;
    box-shadow: var(--shadow-md);
    transition: all 0.3s;
}
.prescription-card:hover { box-shadow: var(--shadow-lg); }

.prescription-header {
    background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
    padding: 12px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    border-bottom: 2px solid var(--info);
}
[data-theme="dark"] .prescription-header {
    background: linear-gradient(135deg, #1E3A5F, #16294A);
}
.prescription-header .ph-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.prescription-header .rx-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #60A5FA, #3B82F6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);
    flex-shrink: 0;
}
.prescription-header .rx-number {
    font-family: var(--font-mono);
    font-weight: 800;
    font-size: 0.9rem;
    color: #1E40AF;
    background: rgba(255,255,255,0.7);
    padding: 4px 12px;
    border-radius: 8px;
    border: 1px solid var(--info);
}
[data-theme="dark"] .prescription-header .rx-number {
    background: rgba(255,255,255,0.1);
    color: #93C5FD;
}
.prescription-header .rx-meta {
    font-size: 0.72rem;
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-weight: 600;
}
.prescription-header .rx-doctor {
    font-size: 0.72rem;
    color: #1E40AF;
    font-weight: 700;
    background: rgba(255,255,255,0.7);
    padding: 3px 10px;
    border-radius: 20px;
    border: 1px solid var(--info);
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
[data-theme="dark"] .prescription-header .rx-doctor {
    background: rgba(255,255,255,0.1);
    color: #93C5FD;
}

/* ============ BADGES ============ */
.badge-status {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 700;
    white-space: nowrap;
}
.badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
.badge-info { background: var(--info-bg); color: var(--info); border: 1px solid var(--info); }
.badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
[data-theme="dark"] .badge-warning { background: #3A2A1A; color: #F59E0B; border-color: #D97706; }
[data-theme="dark"] .badge-info { background: #1E3A5F; color: #6EA8FE; border-color: #3B82F6; }
[data-theme="dark"] .badge-success { background: #1A3A2A; color: #34D399; border-color: #059669; }
[data-theme="dark"] .badge-danger { background: #3A1A1A; color: #F87171; border-color: #DC2626; }

/* ============ TABLE ============ */
.table-scroll { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.data-table thead th {
    text-align: left;
    padding: 10px 12px;
    font-weight: 700;
    font-size: 0.62rem;
    text-transform: uppercase;
    color: #ffffff;
    background: var(--info);
    border-bottom: 3px solid #1E40AF;
    white-space: nowrap;
}
.data-table thead th i { margin-right: 5px; opacity: 0.75; }
.data-table tbody td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
    font-size: 0.75rem;
}
.data-table tbody tr:hover td { background: var(--info-bg); }
[data-theme="dark"] .data-table tbody tr:hover td { background: #1E3A5F; }
.data-table tbody tr:last-child td { border-bottom: none; }

.qty-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--info-bg);
    color: var(--info);
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 800;
    border: 1px solid var(--info);
    font-family: var(--font-mono);
}
[data-theme="dark"] .qty-badge { background: #1E3A5F; color: #93C5FD; border-color: #3B82F6; }

.rx-pill {
    font-family: var(--font-mono);
    font-size: 0.62rem;
    font-weight: 700;
    background: var(--info-bg);
    color: var(--info);
    padding: 2px 8px;
    border-radius: 5px;
    border: 1px solid var(--info);
    white-space: nowrap;
}
[data-theme="dark"] .rx-pill {
    background: #1E3A5F;
    color: #93C5FD;
    border-color: #3B82F6;
}

/* ============ MEDICATION BILL SUMMARY ============ */
.med-bill-summary {
    background: linear-gradient(135deg, #F0FDF4, #DCFCE7);
    border-radius: var(--radius-lg);
    padding: 18px 20px;
    border: 2px solid var(--success);
    margin-top: 16px;
    box-shadow: var(--shadow-md);
}
[data-theme="dark"] .med-bill-summary {
    background: linear-gradient(135deg, #1A3A2A, #0F2A1A);
}
.med-bill-summary .mbs-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 2px dashed rgba(5, 150, 105, 0.3);
    flex-wrap: wrap;
    gap: 8px;
}
.med-bill-summary .mbs-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 1rem;
    font-weight: 800;
    color: var(--success);
}
.med-bill-summary .mbs-title i { font-size: 1.2rem; }
.med-bill-summary .mbs-note {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--text-secondary);
    background: rgba(255,255,255,0.7);
    padding: 4px 12px;
    border-radius: 20px;
    border: 1px solid rgba(5, 150, 105, 0.2);
}
[data-theme="dark"] .med-bill-summary .mbs-note { background: rgba(255,255,255,0.08); }
.med-bill-summary .mbs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
}
.med-bill-summary .mbs-item {
    text-align: center;
    padding: 12px 14px;
    background: rgba(255,255,255,0.6);
    border-radius: 10px;
    border: 1px solid rgba(5, 150, 105, 0.2);
    transition: all 0.25s;
}
.med-bill-summary .mbs-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.15);
}
[data-theme="dark"] .med-bill-summary .mbs-item { background: rgba(255,255,255,0.05); }
.med-bill-summary .mbs-item.highlight {
    background: linear-gradient(135deg, #10B981, #059669);
    border-color: #047857;
    color: white;
}
.med-bill-summary .mbs-item.highlight .mbs-label,
.med-bill-summary .mbs-item.highlight .mbs-value { color: white; }
.med-bill-summary .mbs-label {
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 800;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    margin-bottom: 6px;
}
.med-bill-summary .mbs-label i { font-size: 0.75rem; }
.med-bill-summary .mbs-value {
    font-size: 1.05rem;
    font-weight: 900;
    font-family: var(--font-mono);
    color: var(--success);
    letter-spacing: -0.02em;
}
.med-bill-summary .mbs-value.discount { color: var(--warning); }
.med-bill-summary .mbs-value.premium { color: var(--purple); }
.med-bill-summary .mbs-value.primary { color: var(--primary); }

/* ============ EMPTY STATE ============ */
.empty-state {
    text-align: center;
    padding: 50px 20px;
    color: var(--text-secondary);
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 2px dashed var(--info);
}
.empty-state i {
    font-size: 3rem;
    color: var(--info);
    display: block;
    margin-bottom: 12px;
    opacity: 0.6;
}
.empty-state p { font-size: 0.95rem; font-weight: 600; color: var(--text-primary); }
.empty-state .sub { font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px; font-weight: 400; }

@media (max-width: 1024px) { .main-content { margin-left: 0; padding: 16px; } }
@media (max-width: 768px) {
    .patient-info-card { flex-direction: column; align-items: stretch; }
    .prescription-header { flex-direction: column; align-items: stretch; }
    .med-bill-summary .mbs-grid { grid-template-columns: repeat(2, 1fr); }
}
@media print {
    .page-header .btn-outline-light,
    .readonly-notice { display: none !important; }
    .med-bill-summary { break-inside: avoid; }
}
</style>
</head>
<body>

<main class="main-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-check-circle"></i>
                Confirmed Prescriptions
                <span class="role-badge-display">DOCTOR</span>
            </h1>
            <p class="page-subtitle">
                <span class="branch-tag">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($patient['full_name']) ?>
                </span>
                <?php if ($visit): ?>
                    <span class="branch-tag">
                        <i class="fas fa-calendar-check"></i> <?= htmlspecialchars($visit['visit_number']) ?>
                    </span>
                <?php endif; ?>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="<?= $back_url ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <div class="readonly-notice">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>Confirmed & Ready for Dispensing:</strong> These medications have been confirmed by pharmacy staff and are waiting to be dispensed to the patient. This is a read-only view.
        </div>
    </div>

    <div class="patient-info-card">
        <div class="patient-left">
            <div class="avatar-circle">
                <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
            </div>
            <div>
                <div class="p-name">
                    <i class="fas fa-user-circle"></i>
                    <?= htmlspecialchars($patient['full_name']) ?>
                </div>
                <div class="p-meta">
                    <span class="p-meta-item">
                        <i class="fas fa-id-card"></i>
                        <?= htmlspecialchars($patient['patient_id']) ?>
                    </span>
                    <?php if (!empty($patient['phone'])): ?>
                        <span class="p-meta-item">
                            <i class="fas fa-phone"></i>
                            <?= htmlspecialchars($patient['phone']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($patient['gender'])): ?>
                        <span class="p-meta-item">
                            <i class="fas fa-<?= $patient['gender'] === 'Female' ? 'venus' : 'mars' ?>"></i>
                            <?= htmlspecialchars($patient['gender']) ?>
                            <?php if (!empty($patient['date_of_birth'])): ?>
                                • <?= calculateAge($patient['date_of_birth']) ?> yrs
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($patient['blood_group'])): ?>
                        <span class="p-meta-item">
                            <i class="fas fa-tint"></i>
                            <?= htmlspecialchars($patient['blood_group']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="p-stats">
            <div class="p-stat">
                <div class="p-stat-label">Total Items</div>
                <div class="p-stat-value"><?= $total_items ?></div>
            </div>
            <div class="p-stat">
                <div class="p-stat-label">Total Qty</div>
                <div class="p-stat-value"><?= $total_qty ?></div>
            </div>
            <div class="p-stat">
                <div class="p-stat-label">Prescriptions</div>
                <div class="p-stat-value"><?= $unique_prescriptions ?></div>
            </div>
            <div class="p-stat" style="background:rgba(255,255,255,0.35);">
                <div class="p-stat-label">Status</div>
                <div class="p-stat-value" style="font-size:0.85rem;">✅ CONFIRMED</div>
            </div>
        </div>
    </div>

    <?php if ($visit): ?>
    <div class="visit-info-bar">
        <div class="vi-item">
            <div class="vi-label">Visit #</div>
            <div class="vi-value"><?= htmlspecialchars($visit['visit_number']) ?></div>
        </div>
        <div class="vi-item">
            <div class="vi-label">Visit Date</div>
            <div class="vi-value"><?= formatDate($visit['visit_date'] ?? $visit['created_at']) ?></div>
        </div>
        <div class="vi-item">
            <div class="vi-label">Doctor</div>
            <div class="vi-value"><?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></div>
        </div>
        <div class="vi-item">
            <div class="vi-label">Diagnosis</div>
            <div class="vi-value" style="color:var(--danger);"><?= htmlspecialchars($visit['diagnosis'] ?? 'N/A') ?></div>
        </div>
        <?php if (!empty($latest_confirmed)): ?>
        <div class="vi-item">
            <div class="vi-label">Confirmed At</div>
            <div class="vi-value blue"><?= formatDate($latest_confirmed) ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($unified_items)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <p>No confirmed prescriptions found</p>
            <p class="sub">No medications have been confirmed for this patient<?= $visit_id ? ' in this visit' : '' ?> yet.</p>
        </div>
    <?php else: ?>

        <!-- ============================================================
             SINGLE TABLE - ALL CONFIRMED MEDICATIONS FROM THIS VISIT
             ============================================================ -->
        <div class="prescription-card">
            <div class="prescription-header">
                <div class="ph-left">
                    <div class="rx-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <span class="rx-number">
                        <?= htmlspecialchars($visit_header_number) ?>
                    </span>
                    <span class="rx-doctor">
                        <i class="fas fa-user-md"></i>
                        Dr. <?= htmlspecialchars($visit_doctors_str) ?>
                    </span>
                    <span class="rx-meta">
                        <i class="fas fa-calendar-alt"></i>
                        <?= $visit_header_date_str ?>
                    </span>
                    <?php if ($unique_prescriptions > 1): ?>
                        <span class="badge-status badge-info" style="font-size:0.6rem;padding:3px 10px;">
                            <i class="fas fa-layer-group"></i> <?= $unique_prescriptions ?> Prescriptions
                        </span>
                    <?php endif; ?>
                    <span class="badge-status badge-info" style="font-size:0.6rem;padding:3px 10px;">
                        <i class="fas fa-check-circle"></i> CONFIRMED
                    </span>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <span class="rx-meta" style="font-size:0.75rem;font-weight:700;color:var(--info);">
                        <i class="fas fa-pills"></i>
                        <?= count($unified_items) ?> items • <?= $grand_qty ?> qty •
                        <?= $currency ?> <?= number_format($grand_total, 0) ?>
                    </span>
                </div>
            </div>

            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:40px;text-align:center;">#</th>
                            <th><i class="fas fa-prescription"></i> Rx #</th>
                            <th><i class="fas fa-pills"></i> Medication</th>
                            <th style="text-align:center;"><i class="fas fa-sort-numeric-up"></i> Qty</th>
                            <th><i class="fas fa-prescription"></i> Dosage</th>
                            <th><i class="fas fa-redo"></i> Frequency</th>
                            <th style="text-align:right;"><i class="fas fa-coins"></i> Unit Price</th>
                            <th style="text-align:right;"><i class="fas fa-money-bill"></i> Total</th>
                            <th style="text-align:center;"><i class="fas fa-info-circle"></i> Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $row_num = 1;
                        foreach ($unified_items as $item):
                            $item_status = getItemStatus($item, $item['_prescription_status'] ?? 'confirmed');
                            $unit_price = (float)($item['unit_price'] ?? 0);
                            $qty = (int)($item['quantity'] ?? 0);
                            $line_total = $unit_price * $qty;
                            $item_badge = getStatusBadgeClass($item_status);
                        ?>
                        <tr>
                            <td style="text-align:center;font-family:var(--font-mono);font-weight:700;color:var(--text-secondary);">
                                <?= $row_num++ ?>
                            </td>
                            <td>
                                <span class="rx-pill">
                                    <?= htmlspecialchars($item['_prescription_number']) ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight:700;color:var(--text-primary);">
                                    <?= htmlspecialchars($item['medication_name']) ?>
                                </div>
                                <?php if (isset($item['current_stock'])): ?>
                                    <div style="font-size:0.65rem;color:var(--text-secondary);margin-top:2px;">
                                        <i class="fas fa-warehouse"></i>
                                        Stock: <strong><?= (int)$item['current_stock'] ?></strong>
                                        <?= htmlspecialchars($item['stock_unit'] ?? '') ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="qty-badge"><?= $qty ?></span>
                            </td>
                            <td><?= htmlspecialchars($item['dosage'] ?? '—') ?></td>
                            <td>
                                <?= htmlspecialchars($item['frequency'] ?? '—') ?>
                                <?php if (!empty($item['duration'])): ?>
                                    <div style="font-size:0.65rem;color:var(--text-secondary);">
                                        <?= htmlspecialchars($item['duration']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;font-family:var(--font-mono);color:var(--text-secondary);">
                                <?= $currency ?> <?= number_format($unit_price, 0) ?>
                            </td>
                            <td style="text-align:right;font-family:var(--font-mono);font-weight:800;color:var(--info);">
                                <?= $currency ?> <?= number_format($line_total, 0) ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge-status badge-info">
                                    <i class="fas fa-check"></i> CONFIRMED
                                </span>
                                <?php if (!empty($item['confirmed_by_name'])): ?>
                                    <div style="font-size:0.6rem;color:var(--info);margin-top:2px;font-weight:600;">
                                        <i class="fas fa-user-check"></i> by <?= htmlspecialchars($item['confirmed_by_name']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($item['confirmed_at'])): ?>
                                    <div style="font-size:0.55rem;color:var(--text-secondary);margin-top:1px;font-family:var(--font-mono);">
                                        <?= formatDate($item['confirmed_at']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- GRAND TOTAL ROW -->
                        <tr style="background:linear-gradient(135deg, #DBEAFE, #BFDBFE);font-weight:700;border-top:2px solid var(--info);">
                            <td colspan="7" style="text-align:right;padding:12px 14px;font-size:0.8rem;color:#1E40AF;">
                                <i class="fas fa-calculator"></i> TOTAL (<?= count($unified_items) ?> items • <?= $grand_qty ?> qty):
                            </td>
                            <td style="text-align:right;padding:12px 14px;font-size:1rem;color:#1E40AF;font-family:var(--font-mono);font-weight:900;">
                                <?= $currency ?> <?= number_format($grand_total, 0) ?>
                            </td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============================================================
             MEDICATION-ONLY BILL SUMMARY
             ============================================================ -->
        <?php if ($bill && $med_bill['item_count'] > 0): ?>
        <div class="med-bill-summary">
            <div class="mbs-header">
                <div class="mbs-title">
                    <i class="fas fa-pills"></i>
                    Medication Bill Summary
                </div>
                <div class="mbs-note">
                    <i class="fas fa-info-circle"></i>
                    Medications only — excludes consultation, lab, procedures & equipment
                </div>
            </div>

            <div class="mbs-grid">
                <div class="mbs-item">
                    <div class="mbs-label">
                        <i class="fas fa-coins"></i> Meds Subtotal
                    </div>
                    <div class="mbs-value">
                        <?= $currency ?> <?= number_format($med_bill['med_subtotal'], 0) ?>
                    </div>
                </div>

                <div class="mbs-item">
                    <div class="mbs-label">
                        <i class="fas fa-percent"></i> Pharm Discount
                    </div>
                    <div class="mbs-value discount">
                        <?= $med_bill['pharmacy_discount'] > 0
                            ? '- ' . $currency . ' ' . number_format($med_bill['pharmacy_discount'], 0)
                            : '—' ?>
                    </div>
                </div>

                <div class="mbs-item">
                    <div class="mbs-label">
                        <i class="fas fa-star"></i> Pharm Premium
                    </div>
                    <div class="mbs-value premium">
                        <?= $med_bill['pharmacy_premium'] > 0
                            ? '+ ' . $currency . ' ' . number_format($med_bill['pharmacy_premium'], 0)
                            : '—' ?>
                    </div>
                </div>

                <div class="mbs-item highlight">
                    <div class="mbs-label">
                        <i class="fas fa-calculator"></i> Net Total
                    </div>
                    <div class="mbs-value">
                        <?= $currency ?> <?= number_format($med_bill['net_total'], 0) ?>
                    </div>
                </div>

                <div class="mbs-item">
                    <div class="mbs-label">
                        <i class="fas fa-list"></i> Items
                    </div>
                    <div class="mbs-value primary">
                        <?= (int)$med_bill['item_count'] ?>
                    </div>
                </div>

                <div class="mbs-item">
                    <div class="mbs-label">
                        <i class="fas fa-file-invoice"></i> Bill #
                    </div>
                    <div class="mbs-value" style="font-size:0.72rem;color:var(--text-secondary);">
                        <?= htmlspecialchars($bill['bill_number']) ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>

</main>

<script>
    var htmlElement = document.documentElement;
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') htmlElement.setAttribute('data-theme', 'dark');
    else if (savedDarkMode === 'false') htmlElement.removeAttribute('data-theme');
    else {
        var cookieDark = document.cookie.match(/dark_mode=([^;]+)/);
        if (cookieDark && cookieDark[1] === 'true') htmlElement.setAttribute('data-theme', 'dark');
    }

    console.log('%c💊 Doctor - Confirmed Prescriptions (Read-Only)', 'font-size:18px; font-weight:bold; color:#3B82F6;');
    console.log('%c✅ Single table for entire visit', 'font-size:13px; color:#3B82F6; font-weight:bold;');
    console.log('%c✅ Medication-only bill summary', 'font-size:13px; color:#3B82F6;');
</script>

</body>
</html>