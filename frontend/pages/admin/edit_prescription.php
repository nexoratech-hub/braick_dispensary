<?php
// ================================================================
// FILE: frontend/pages/admin/edit_prescription.php
// ADMIN - EDIT PENDING PRESCRIPTION (with Dosage/Frequency/Route)
// ✅ ENGLISH ONLY
// ✅ Shows ALL bill items (medications + others)
// ✅ MEDICATIONS have: Dosage, Frequency, Route, Duration, Instructions
// ✅ NON-MEDICATIONS have: Item Name, Qty, Unit Price
// ✅ Add/Remove items dynamically
// ✅ BLUE THEME
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET PARAMETERS
// ================================================================
$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

if ($prescription_id <= 0 || $patient_id <= 0) {
    header('Location: prescriptions.php?error=invalid_params');
    exit;
}

// ================================================================
// FETCH PRESCRIPTION
// ================================================================
$prescription = null;
try {
    $stmt = $db->prepare("
        SELECT p.*, 
               pat.full_name as patient_name, pat.patient_id as patient_number,
               pat.phone as patient_phone, pat.gender as patient_gender, pat.date_of_birth,
               doc.full_name as doctor_name,
               v.visit_number, v.visit_date,
               b.name as branch_name
        FROM prescriptions p
        LEFT JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN users doc ON p.doctor_id = doc.id
        LEFT JOIN visits v ON p.visit_id = v.id
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE p.id = ? AND p.patient_id = ?
    ");
    $stmt->execute([$prescription_id, $patient_id]);
    $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if (!$prescription) {
    header('Location: prescriptions.php?error=prescription_not_found');
    exit;
}

if ($prescription['status'] !== 'pending') {
    header('Location: patient_prescriptions.php?patient_id=' . $patient_id . '&branch=' . urlencode($selected_branch_id) . '&error=cannot_edit');
    exit;
}

// ================================================================
// FETCH BILL & ALL BILL ITEMS
// ================================================================
$bill = null;
$bill_items = [];

try {
    $stmt = $db->prepare("
        SELECT DISTINCT b.id as bill_id, b.bill_number, b.status, b.total_amount, 
                        b.paid_amount, b.discount_amount, b.subtotal
        FROM bills b
        INNER JOIN bill_items bi ON b.id = bi.bill_id
        WHERE bi.reference_id = ? 
          AND bi.reference_type = 'prescription'
        LIMIT 1
    ");
    $stmt->execute([$prescription_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($bill) {
        // Get ALL bill items with medication details joined
        $stmt = $db->prepare("
            SELECT 
                bi.id, bi.item_type, bi.item_name, bi.quantity, bi.unit_price, 
                bi.total_price, bi.discount_amount, bi.final_price, bi.status,
                bi.reference_id, bi.reference_type, bi.notes,
                pi.dosage as med_dosage,
                pi.frequency as med_frequency,
                pi.route as med_route,
                pi.duration as med_duration,
                pi.instructions as med_instructions
            FROM bill_items bi
            LEFT JOIN prescription_items pi ON bi.reference_id = pi.id AND bi.reference_type = 'prescription'
            WHERE bi.bill_id = ?
              AND bi.status != 'cancelled'
            ORDER BY bi.item_type ASC, bi.id ASC
        ");
        $stmt->execute([$bill['bill_id']]);
        $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

if (empty($bill_items)) {
    try {
        $stmt = $db->prepare("
            SELECT 
                id, 'medication' as item_type, medication_name as item_name, quantity, 
                unit_price, total_price, 0 as discount_amount, total_price as final_price,
                'pending' as status, id as reference_id, 'prescription' as reference_type, NULL as notes,
                dosage as med_dosage, frequency as med_frequency, route as med_route,
                duration as med_duration, instructions as med_instructions
            FROM prescription_items
            WHERE prescription_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$prescription_id]);
        $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// ================================================================
// HANDLE SAVE
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_prescription') {
    try {
        $db->beginTransaction();
        
        $bill_items_input = $_POST['bill_items'] ?? [];
        
        if (empty($bill_items_input)) {
            throw new Exception("At least one item is required");
        }
        
        // 1. DELETE OLD
        $db->prepare("DELETE FROM prescription_items WHERE prescription_id = ?")->execute([$prescription_id]);
        if ($bill) {
            $db->prepare("DELETE FROM bill_items WHERE bill_id = ?")->execute([$bill['bill_id']]);
        }
        
        // 2. INSERT NEW
        $grand_total = 0;
        $item_count = 0;
        $medication_count = 0;
        
        foreach ($bill_items_input as $idx => $item) {
            $item_type = trim($item['item_type'] ?? 'medication');
            $item_name = trim($item['item_name'] ?? '');
            $quantity = (int)($item['quantity'] ?? 0);
            $unit_price = (float)($item['unit_price'] ?? 0);
            
            // Medication extra fields
            $dosage = trim($item['dosage'] ?? '');
            $frequency = trim($item['frequency'] ?? '');
            $route = trim($item['route'] ?? '');
            $duration = trim($item['duration'] ?? '');
            $item_instructions = trim($item['item_instructions'] ?? '');
            
            if (empty($item_name) || $quantity <= 0) continue;
            
            $item_total = $unit_price * $quantity;
            $grand_total += $item_total;
            $item_count++;
            
            $reference_id = null;
            $reference_type = null;
            
            if ($item_type === 'medication') {
                $reference_type = 'prescription';
                $medication_count++;
                
                // Insert into prescription_items (with all fields)
                $db->prepare("
                    INSERT INTO prescription_items (
                        prescription_id, patient_id, branch_id,
                        medication_name, quantity, dosage, frequency, route, duration, instructions,
                        unit_price, total_price,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ")->execute([
                    $prescription_id,
                    $patient_id,
                    $prescription['branch_id'],
                    $item_name,
                    $quantity,
                    $dosage,
                    $frequency,
                    $route,
                    $duration,
                    $item_instructions,
                    $unit_price,
                    $item_total
                ]);
                
                $reference_id = $db->lastInsertId();
            }
            
            // Insert into bill_items
            if ($bill) {
                $db->prepare("
                    INSERT INTO bill_items (
                        bill_id, patient_id, branch_id,
                        item_type, item_name, quantity, unit_price, total_price,
                        discount_amount, tax_amount, final_price,
                        reference_id, reference_type, status,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, 'pending', NOW(), NOW())
                ")->execute([
                    $bill['bill_id'],
                    $patient_id,
                    $prescription['branch_id'],
                    $item_type,
                    $item_name,
                    $quantity,
                    $unit_price,
                    $item_total,
                    $item_total,
                    $reference_id,
                    $reference_type
                ]);
            }
        }
        
        // 3. UPDATE BILL TOTALS
        if ($bill) {
            $db->prepare("
                UPDATE bills 
                SET subtotal = ?, 
                    total_amount = ?, 
                    balance = total_amount - paid_amount,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$grand_total, $grand_total, $bill['bill_id']]);
        }
        
        // 4. LOG ACTIVITY
        try {
            $db->prepare("
                INSERT INTO activity_logs (user_id, action, description, branch_id, ip_address, created_at)
                VALUES (?, 'EDIT_PRESCRIPTION', ?, ?, ?, NOW())
            ")->execute([
                $user_id,
                "Edited prescription: " . ($prescription['prescription_number'] ?? 'N/A') . " ({$item_count} items, {$medication_count} medications, Total: TSh " . number_format($grand_total, 0) . ")",
                $selected_branch_id !== 'all' ? (int)$selected_branch_id : 1,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (Exception $e) {}
        
        $db->commit();
        
        header('Location: patient_prescriptions.php?patient_id=' . $patient_id . '&branch=' . urlencode($selected_branch_id) . '&updated=1');
        exit;
        
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $message = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// ================================================================
// FETCH INVENTORY
// ================================================================
$inventory_medications = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT medication_name, generic_name, selling_price, quantity as stock_qty
        FROM medications_inventory 
        WHERE branch_id = ? AND status = 'active' AND quantity > 0
        ORDER BY medication_name ASC
    ");
    $stmt->execute([$prescription['branch_id']]);
    $inventory_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    return (new DateTime($dob))->diff(new DateTime('today'))->y;
}

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

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
    --gray-50: #F8FAFC;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --shadow: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
}

[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary-bg: #1E3A5F;
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3A2A1A;
    --purple-bg: #2D1B4E;
    --cyan-bg: #0E3A47;
}

* { font-family: var(--font-primary); -webkit-font-smoothing: antialiased; }
.mono, .money-value, .price-value { font-family: var(--font-mono) !important; font-feature-settings: 'tnum'; }

.page-header-custom {
    background: linear-gradient(135deg, #F59E0B, #D97706, #B45309);
    border-radius: 16px;
    padding: 20px 28px;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(217, 119, 6, 0.3);
    position: relative;
    overflow: hidden;
}

.page-header-custom::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 300px; height: 300px;
    background: rgba(255,255,255,0.08);
    border-radius: 50%;
    pointer-events: none;
}

.page-header-custom .page-title {
    color: white;
    font-size: 1.4rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin: 0;
}

.page-header-custom .page-title i { font-size: 1.6rem; }

.page-header-custom .page-subtitle {
    color: rgba(255,255,255,0.9);
    font-size: 0.82rem;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin-top: 4px;
}

.page-header-custom .tag-chip {
    background: rgba(255,255,255,0.18);
    color: white;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.68rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid rgba(255,255,255,0.2);
}

.page-header-custom .btn-outline-light {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1px solid rgba(255,255,255,0.25);
    padding: 8px 16px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.78rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    position: relative;
    z-index: 1;
}

.page-header-custom .btn-outline-light:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}

.info-card {
    background: var(--bg-card);
    border-radius: 14px;
    padding: 16px 20px;
    border: 2px solid var(--border-color);
    box-shadow: var(--shadow);
}

.info-card .info-card-title {
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--primary);
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 2px dashed var(--border-color);
    display: flex;
    align-items: center;
    gap: 6px;
}

.info-card .info-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 0.8rem;
    border-bottom: 1px solid var(--border-color);
}

.info-card .info-row:last-child { border-bottom: none; }
.info-card .info-label { color: var(--text-secondary); font-weight: 600; }
.info-card .info-value { color: var(--text-primary); font-weight: 700; text-align: right; }

.warning-banner {
    background: linear-gradient(135deg, #FEF3C7, #FDE68A);
    border: 2px solid #F59E0B;
    border-radius: 12px;
    padding: 14px 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #78350F;
    font-size: 0.85rem;
    font-weight: 600;
}

[data-theme="dark"] .warning-banner {
    background: linear-gradient(135deg, #3A2A1A, #4A3A2A);
    color: #FCD34D;
    border-color: #B45309;
}

.warning-banner i {
    font-size: 1.4rem;
    background: #F59E0B;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.form-card {
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px solid var(--border-color);
    margin-bottom: 20px;
    box-shadow: var(--shadow);
    overflow: hidden;
}

.form-card-header {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    padding: 14px 22px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.form-card-header .card-title {
    font-size: 0.95rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-card-body { padding: 20px 22px; }

.form-group { margin-bottom: 16px; }

.form-group label {
    display: block;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-secondary);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 500;
    background: var(--bg-body);
    color: var(--text-primary);
    outline: none;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: var(--primary);
    background: var(--bg-card);
    box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
}

.bill-items-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.bill-item {
    background: var(--bg-body);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 18px 20px;
    position: relative;
    transition: all 0.3s ease;
}

.bill-item:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
}

.bill-item .item-number {
    position: absolute;
    top: -10px;
    left: 18px;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.72rem;
    font-family: var(--font-mono);
    box-shadow: 0 3px 8px rgba(11, 94, 215, 0.4);
    border: 2px solid var(--bg-body);
}

.bill-item .item-remove {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(220, 38, 38, 0.1);
    color: var(--danger);
    border: 1.5px solid var(--danger);
    width: 30px;
    height: 30px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    transition: all 0.25s ease;
}

.bill-item .item-remove:hover {
    background: var(--danger);
    color: white;
    transform: scale(1.08);
}

.bill-item .item-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 8px;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    margin-bottom: 12px;
    letter-spacing: 0.04em;
}

.item-type-badge.medication {
    background: var(--primary-bg);
    color: var(--primary);
    border: 1px solid var(--primary-light);
}

.item-type-badge.consultation {
    background: var(--success-bg);
    color: var(--success);
    border: 1px solid var(--success);
}

.item-type-badge.lab_test {
    background: var(--purple-bg);
    color: var(--purple);
    border: 1px solid var(--purple);
}

.item-type-badge.procedure {
    background: var(--warning-bg);
    color: var(--warning);
    border: 1px solid var(--warning);
}

.item-type-badge.registration {
    background: var(--cyan-bg);
    color: var(--cyan);
    border: 1px solid var(--cyan);
}

.item-type-badge.equipment,
.item-type-badge.other {
    background: var(--gray-50);
    color: var(--text-secondary);
    border: 1px solid var(--border-color);
}

/* Basic grid: Name + Qty + Price */
.bill-item .item-grid-basic {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 12px;
}

/* Medication extra fields grid: Dosage + Freq + Route + Duration + Instructions */
.bill-item .item-grid-med {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 1fr;
    gap: 12px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 2px dashed var(--border-color);
}

.bill-item .item-grid-med-instructions {
    grid-column: 1 / -1;
}

.bill-item .line-total-box {
    padding: 10px 14px;
    background: var(--success-bg);
    color: var(--success);
    border-radius: 10px;
    font-family: var(--font-mono);
    font-weight: 800;
    border: 2px solid var(--success);
    text-align: center;
}

.bill-item .med-section-title {
    font-size: 0.62rem;
    font-weight: 800;
    color: var(--primary);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.btn-add-item {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.82rem;
    background: linear-gradient(135deg, #10B981, #059669);
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    width: 100%;
}

.btn-add-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5);
}

.total-summary {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    border-radius: 14px;
    padding: 20px 24px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 20px;
    box-shadow: 0 6px 24px rgba(11, 94, 215, 0.3);
}

.total-summary .summary-label {
    font-size: 0.75rem;
    font-weight: 600;
    opacity: 0.85;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.total-summary .summary-value {
    font-family: var(--font-mono);
    font-size: 1.8rem;
    font-weight: 800;
}

.total-summary .summary-count {
    font-size: 0.8rem;
    opacity: 0.9;
    background: rgba(255,255,255,0.15);
    padding: 4px 12px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-weight: 700;
}

.action-buttons {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    flex-wrap: wrap;
    padding-top: 20px;
    border-top: 2px solid var(--border-color);
    margin-top: 20px;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.85rem;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-action.save {
    background: linear-gradient(135deg, #10B981, #059669);
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-action.save:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5);
}

.btn-action.cancel {
    background: var(--bg-body);
    color: var(--text-secondary);
    border: 2px solid var(--border-color);
}

.btn-action.cancel:hover {
    background: var(--border-color);
    color: var(--text-primary);
}

.alert-custom {
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
    font-size: 0.85rem;
}

.alert-custom.error {
    background: var(--danger-bg);
    color: var(--danger);
    border-left: 4px solid var(--danger);
}

@media (max-width: 1024px) {
    .info-grid { grid-template-columns: 1fr; }
    .bill-item .item-grid-basic { grid-template-columns: 1fr 1fr; }
    .bill-item .item-grid-med { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 768px) {
    .page-header-custom { padding: 16px 18px; }
    .page-header-custom .page-title { font-size: 1.1rem; }
    .bill-item .item-grid-basic { grid-template-columns: 1fr; }
    .bill-item .item-grid-med { grid-template-columns: 1fr; }
    .action-buttons { flex-direction: column; }
    .btn-action { width: 100%; justify-content: center; }
    .total-summary { flex-direction: column; text-align: center; }
}
</style>

<main class="main-content">

    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-edit"></i>
                Edit Prescription
                <span style="background:rgba(255,255,255,0.25);padding:3px 12px;border-radius:20px;font-size:0.62rem;font-weight:800;text-transform:uppercase;">
                    <i class="fas fa-clock"></i> PENDING
                </span>
            </h1>
            <p class="page-subtitle">
                <span class="tag-chip"><i class="fas fa-hashtag"></i> <?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></span>
                <span class="tag-chip"><i class="fas fa-user"></i> <?= htmlspecialchars($prescription['patient_name']) ?></span>
                <span class="tag-chip"><i class="fas fa-calendar-check"></i> <?= htmlspecialchars($prescription['visit_number'] ?? 'N/A') ?></span>
                <?php if ($bill): ?>
                    <span class="tag-chip" style="background:rgba(16,185,129,0.3);">
                        <i class="fas fa-file-invoice-dollar"></i> <?= htmlspecialchars($bill['bill_number']) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="patient_prescriptions.php?patient_id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" 
               class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="warning-banner">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>Editing Pending Prescription</strong><br>
            <span style="font-weight:500;opacity:0.9;">Changes will update the bill and stock records. Make sure all information is correct before saving.</span>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert-custom <?= $message_type ?>">
            <i class="fas fa-exclamation-circle"></i>
            <div><?= htmlspecialchars($message) ?></div>
        </div>
    <?php endif; ?>

    <div class="info-grid">
        <div class="info-card">
            <div class="info-card-title">
                <i class="fas fa-user"></i> Patient Information
            </div>
            <div class="info-row">
                <span class="info-label">Name</span>
                <span class="info-value"><?= htmlspecialchars($prescription['patient_name'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Patient ID</span>
                <span class="info-value mono"><?= htmlspecialchars($prescription['patient_number'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Phone</span>
                <span class="info-value mono"><?= htmlspecialchars($prescription['patient_phone'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Age / Gender</span>
                <span class="info-value"><?= calculateAge($prescription['date_of_birth'] ?? '') ?> yrs • <?= htmlspecialchars($prescription['patient_gender'] ?? 'N/A') ?></span>
            </div>
        </div>

        <div class="info-card">
            <div class="info-card-title">
                <i class="fas fa-file-medical"></i> Prescription Info
            </div>
            <div class="info-row">
                <span class="info-label">Rx Number</span>
                <span class="info-value mono"><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Visit</span>
                <span class="info-value mono"><?= htmlspecialchars($prescription['visit_number'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Doctor</span>
                <span class="info-value"><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Date Created</span>
                <span class="info-value mono"><?= date('d M Y, H:i', strtotime($prescription['created_at'] ?? 'now')) ?></span>
            </div>
        </div>
    </div>

    <form method="POST" id="editForm">
        <input type="hidden" name="action" value="save_prescription">
        
        <div class="form-card">
            <div class="form-card-header">
                <div class="card-title">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Bill Items
                    <?php if ($bill): ?>
                        <span style="background:rgba(255,255,255,0.25);padding:2px 10px;border-radius:12px;font-size:0.65rem;font-weight:800;">
                            <?= htmlspecialchars($bill['bill_number']) ?>
                        </span>
                    <?php endif; ?>
                    <span style="background:rgba(255,255,255,0.25);padding:2px 10px;border-radius:12px;font-size:0.65rem;font-weight:800;" id="itemCountBadge">
                        <?= count($bill_items) ?> items
                    </span>
                </div>
            </div>
            <div class="form-card-body">
                
                <div class="bill-items-container" id="billItemsContainer">
                    <?php if (!empty($bill_items)): ?>
                        <?php $idx = 0; foreach ($bill_items as $item): $idx++; 
                            $item_type = $item['item_type'] ?? 'medication';
                            $item_name = $item['item_name'] ?? '';
                            $item_qty = (int)($item['quantity'] ?? 1);
                            $item_price = (float)($item['unit_price'] ?? 0);
                            $item_total = (float)($item['total_price'] ?? ($item_price * $item_qty));
                            
                            $item_dosage = $item['med_dosage'] ?? '';
                            $item_frequency = $item['med_frequency'] ?? '';
                            $item_route = $item['med_route'] ?? '';
                            $item_duration = $item['med_duration'] ?? '';
                            $item_instructions = $item['med_instructions'] ?? '';
                            
                            $type_labels = [
                                'medication' => ['icon' => 'fa-pills', 'label' => 'Medication'],
                                'consultation' => ['icon' => 'fa-stethoscope', 'label' => 'Consultation'],
                                'lab_test' => ['icon' => 'fa-flask', 'label' => 'Lab Test'],
                                'procedure' => ['icon' => 'fa-syringe', 'label' => 'Procedure'],
                                'registration' => ['icon' => 'fa-user-plus', 'label' => 'Registration'],
                                'equipment' => ['icon' => 'fa-tools', 'label' => 'Equipment'],
                                'other' => ['icon' => 'fa-ellipsis-h', 'label' => 'Other'],
                            ];
                            $type_info = $type_labels[$item_type] ?? ['icon' => 'fa-tag', 'label' => ucfirst($item_type)];
                            $is_medication = ($item_type === 'medication');
                        ?>
                            <div class="bill-item" data-index="<?= $idx ?>" data-type="<?= $item_type ?>">
                                <div class="item-number"><?= $idx ?></div>
                                <button type="button" class="item-remove" onclick="removeItem(this)" title="Remove">
                                    <i class="fas fa-times"></i>
                                </button>
                                
                                <span class="item-type-badge <?= $item_type ?>">
                                    <i class="fas <?= $type_info['icon'] ?>"></i>
                                    <?= $type_info['label'] ?>
                                </span>
                                
                                <input type="hidden" name="bill_items[<?= $idx ?>][item_type]" class="item-type-input" value="<?= htmlspecialchars($item_type) ?>">
                                
                                <!-- BASIC FIELDS -->
                                <div class="item-grid-basic">
                                    <div class="form-group" style="margin: 0;">
                                        <label><i class="fas fa-tag"></i> Item Name</label>
                                        <input type="text" 
                                               name="bill_items[<?= $idx ?>][item_name]" 
                                               class="form-control item-name-input" 
                                               value="<?= htmlspecialchars($item_name) ?>"
                                               <?php if ($is_medication): ?>list="medicationsList"<?php endif; ?>
                                               placeholder="Enter item name..."
                                               required
                                               oninput="onItemNameChange(this)">
                                    </div>
                                    <div class="form-group" style="margin: 0;">
                                        <label><i class="fas fa-sort-numeric-up"></i> Quantity</label>
                                        <input type="number" 
                                               name="bill_items[<?= $idx ?>][quantity]" 
                                               class="form-control item-qty-input" 
                                               value="<?= $item_qty ?>"
                                               min="1"
                                               required
                                               oninput="updateItemPrice(this)">
                                    </div>
                                    <div class="form-group" style="margin: 0;">
                                        <label><i class="fas fa-money-bill-wave"></i> Unit Price (TSh)</label>
                                        <input type="number" 
                                               name="bill_items[<?= $idx ?>][unit_price]" 
                                               class="form-control item-price-input" 
                                               value="<?= $item_price ?>"
                                               min="0"
                                               step="0.01"
                                               required
                                               oninput="updateItemPrice(this)">
                                    </div>
                                    <div class="form-group" style="margin: 0;">
                                        <label><i class="fas fa-calculator"></i> Line Total</label>
                                        <div class="line-total-box">
                                            <span class="line-total-display">TSh <?= number_format($item_total, 0) ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- ✅ MEDICATION EXTRA FIELDS -->
                                <div class="item-grid-med" style="display: <?= $is_medication ? 'grid' : 'none' ?>;">
                                    <div class="med-section-title" style="grid-column: 1 / -1; margin-bottom: 0;">
                                        <i class="fas fa-prescription-bottle-medical"></i> Medication Details
                                    </div>
                                    
                                    <div class="form-group" style="margin: 0;">
                                        <label><i class="fas fa-prescription"></i> Dosage</label>
                                        <input type="text" 
                                               name="bill_items[<?= $idx ?>][dosage]" 
                                               class="form-control med-dosage-input" 
                                               value="<?= htmlspecialchars($item_dosage) ?>"
                                               placeholder="e.g. 500mg">
                                    </div>
                                    <div class="form-group" style="margin: 0;">
                                        <label><i class="fas fa-clock"></i> Frequency</label>
                                        <input type="text" 
                                               name="bill_items[<?= $idx ?>][frequency]" 
                                               class="form-control med-frequency-input" 
                                               value="<?= htmlspecialchars($item_frequency) ?>"
                                               placeholder="e.g. 2x daily">
                                    </div>
                                    <div class="form-group" style="margin: 0;">
                                        <label><i class="fas fa-route"></i> Route</label>
                                        <input type="text" 
                                               name="bill_items[<?= $idx ?>][route]" 
                                               class="form-control med-route-input" 
                                               value="<?= htmlspecialchars($item_route) ?>"
                                               placeholder="e.g. Oral">
                                    </div>
                                    <div class="form-group" style="margin: 0;">
                                        <label><i class="fas fa-calendar-alt"></i> Duration</label>
                                        <input type="text" 
                                               name="bill_items[<?= $idx ?>][duration]" 
                                               class="form-control med-duration-input" 
                                               value="<?= htmlspecialchars($item_duration) ?>"
                                               placeholder="e.g. 7 days">
                                    </div>
                                    
                                    <div class="form-group item-grid-med-instructions" style="margin: 0;">
                                        <label><i class="fas fa-info-circle"></i> Instructions</label>
                                        <input type="text" 
                                               name="bill_items[<?= $idx ?>][item_instructions]" 
                                               class="form-control med-instructions-input" 
                                               value="<?= htmlspecialchars($item_instructions) ?>"
                                               placeholder="e.g. Take after meal with plenty of water">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="bill-item" data-index="1" data-type="medication">
                            <div class="item-number">1</div>
                            <button type="button" class="item-remove" onclick="removeItem(this)">
                                <i class="fas fa-times"></i>
                            </button>
                            
                            <span class="item-type-badge medication">
                                <i class="fas fa-pills"></i> Medication
                            </span>
                            <input type="hidden" name="bill_items[1][item_type]" class="item-type-input" value="medication">
                            
                            <div class="item-grid-basic">
                                <div class="form-group" style="margin: 0;">
                                    <label><i class="fas fa-tag"></i> Item Name</label>
                                    <input type="text" name="bill_items[1][item_name]" class="form-control item-name-input" list="medicationsList" placeholder="Start typing..." required oninput="onItemNameChange(this)">
                                </div>
                                <div class="form-group" style="margin: 0;">
                                    <label><i class="fas fa-sort-numeric-up"></i> Quantity</label>
                                    <input type="number" name="bill_items[1][quantity]" class="form-control item-qty-input" value="1" min="1" required oninput="updateItemPrice(this)">
                                </div>
                                <div class="form-group" style="margin: 0;">
                                    <label><i class="fas fa-money-bill-wave"></i> Unit Price (TSh)</label>
                                    <input type="number" name="bill_items[1][unit_price]" class="form-control item-price-input" value="0" min="0" step="0.01" required oninput="updateItemPrice(this)">
                                </div>
                                <div class="form-group" style="margin: 0;">
                                    <label><i class="fas fa-calculator"></i> Line Total</label>
                                    <div class="line-total-box"><span class="line-total-display">TSh 0</span></div>
                                </div>
                            </div>
                            
                            <div class="item-grid-med" style="display: grid;">
                                <div class="med-section-title" style="grid-column: 1 / -1; margin-bottom: 0;">
                                    <i class="fas fa-prescription-bottle-medical"></i> Medication Details
                                </div>
                                
                                <div class="form-group" style="margin: 0;">
                                    <label><i class="fas fa-prescription"></i> Dosage</label>
                                    <input type="text" name="bill_items[1][dosage]" class="form-control med-dosage-input" placeholder="e.g. 500mg">
                                </div>
                                <div class="form-group" style="margin: 0;">
                                    <label><i class="fas fa-clock"></i> Frequency</label>
                                    <input type="text" name="bill_items[1][frequency]" class="form-control med-frequency-input" placeholder="e.g. 2x daily">
                                </div>
                                <div class="form-group" style="margin: 0;">
                                    <label><i class="fas fa-route"></i> Route</label>
                                    <input type="text" name="bill_items[1][route]" class="form-control med-route-input" placeholder="e.g. Oral">
                                </div>
                                <div class="form-group" style="margin: 0;">
                                    <label><i class="fas fa-calendar-alt"></i> Duration</label>
                                    <input type="text" name="bill_items[1][duration]" class="form-control med-duration-input" placeholder="e.g. 7 days">
                                </div>
                                
                                <div class="form-group item-grid-med-instructions" style="margin: 0;">
                                    <label><i class="fas fa-info-circle"></i> Instructions</label>
                                    <input type="text" name="bill_items[1][item_instructions]" class="form-control med-instructions-input" placeholder="e.g. Take after meal with plenty of water">
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <datalist id="medicationsList">
                    <?php foreach ($inventory_medications as $med): ?>
                        <option value="<?= htmlspecialchars($med['medication_name']) ?>" 
                                data-price="<?= (float)($med['selling_price'] ?? 0) ?>">
                            <?= htmlspecialchars($med['generic_name'] ?? '') ?> • Stock: <?= (int)($med['stock_qty'] ?? 0) ?>
                        </option>
                    <?php endforeach; ?>
                </datalist>
                
                <div style="margin-top: 16px;">
                    <button type="button" class="btn-add-item" onclick="addMedication()">
                        <i class="fas fa-plus-circle"></i>
                        Add Medication
                    </button>
                </div>
                
                <div style="margin-top: 10px;">
                    <button type="button" class="btn-add-item" style="background: linear-gradient(135deg, #0EA5E9, #0284C7);" onclick="addOtherItem()">
                        <i class="fas fa-plus-circle"></i>
                        Add Other Item (Consultation, Lab, etc.)
                    </button>
                </div>
            </div>
        </div>

        <div class="total-summary">
            <div>
                <div class="summary-label"><i class="fas fa-coins"></i> Grand Total</div>
                <div class="summary-value mono" id="grandTotalDisplay">TSh 0</div>
            </div>
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <span class="summary-count">
                    <i class="fas fa-list"></i>
                    <span id="totalItemsCount">0</span> items
                </span>
                <span class="summary-count">
                    <i class="fas fa-pills"></i>
                    Meds: <span id="medCountCount">0</span>
                </span>
            </div>
        </div>

        <div class="action-buttons">
            <a href="patient_prescriptions.php?patient_id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" 
               class="btn-action cancel">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn-action save">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </form>

</main>

<script>
var MEDICATIONS_PRICE = {
    <?php foreach ($inventory_medications as $med): ?>
        <?= json_encode(strtolower(trim($med['medication_name']))) ?>: <?= (float)($med['selling_price'] ?? 0) ?>,
    <?php endforeach; ?>
};
</script>

<script>
// ================================================================
// VARIABLES
// ================================================================
var itemCounter = <?= max(1, count($bill_items)) ?>;
var container = document.getElementById('billItemsContainer');

// ================================================================
// GET MEDICATION PRICE
// ================================================================
function getMedicationPrice(medName) {
    if (!medName) return 0;
    return MEDICATIONS_PRICE[medName.toLowerCase().trim()] || 0;
}

// ================================================================
// ON ITEM NAME CHANGE (auto-fill price for medications)
// ================================================================
function onItemNameChange(input) {
    var item = input.closest('.bill-item');
    if (!item) return;
    
    var typeInput = item.querySelector('.item-type-input');
    var priceInput = item.querySelector('.item-price-input');
    
    if (typeInput && typeInput.value === 'medication' && priceInput) {
        var autoPrice = getMedicationPrice(input.value);
        if (autoPrice > 0 && (!parseFloat(priceInput.value) || priceInput.value === '0')) {
            priceInput.value = autoPrice;
        }
    }
    
    updateItemPrice(input);
}

// ================================================================
// UPDATE ITEM PRICE
// ================================================================
function updateItemPrice(input) {
    var item = input.closest('.bill-item');
    if (!item) return;
    
    var qtyInput = item.querySelector('.item-qty-input');
    var priceInput = item.querySelector('.item-price-input');
    var lineTotalDisplay = item.querySelector('.line-total-display');
    
    if (!qtyInput || !priceInput || !lineTotalDisplay) return;
    
    var qty = parseInt(qtyInput.value) || 0;
    var unitPrice = parseFloat(priceInput.value) || 0;
    var total = unitPrice * qty;
    
    lineTotalDisplay.textContent = 'TSh ' + numberFormat(total);
    updateGrandTotal();
}

// ================================================================
// UPDATE GRAND TOTAL
// ================================================================
function updateGrandTotal() {
    var items = document.querySelectorAll('.bill-item');
    var grandTotal = 0;
    var totalItems = 0;
    var medCount = 0;
    
    items.forEach(function(item) {
        var qty = parseInt(item.querySelector('.item-qty-input')?.value) || 0;
        var price = parseFloat(item.querySelector('.item-price-input')?.value) || 0;
        var type = item.querySelector('.item-type-input')?.value || 'medication';
        
        grandTotal += (qty * price);
        totalItems++;
        if (type === 'medication') medCount++;
    });
    
    document.getElementById('grandTotalDisplay').textContent = 'TSh ' + numberFormat(grandTotal);
    document.getElementById('totalItemsCount').textContent = totalItems;
    document.getElementById('medCountCount').textContent = medCount;
    document.getElementById('itemCountBadge').textContent = totalItems + ' items';
}

// ================================================================
// NUMBER FORMAT
// ================================================================
function numberFormat(num) {
    return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// ================================================================
// BUILD MEDICATION HTML
// ================================================================
function buildMedicationHTML(idx) {
    return `
        <div class="bill-item" data-index="${idx}" data-type="medication">
            <div class="item-number">${idx}</div>
            <button type="button" class="item-remove" onclick="removeItem(this)" title="Remove">
                <i class="fas fa-times"></i>
            </button>
            
            <span class="item-type-badge medication">
                <i class="fas fa-pills"></i> Medication
            </span>
            <input type="hidden" name="bill_items[${idx}][item_type]" class="item-type-input" value="medication">
            
            <div class="item-grid-basic">
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-tag"></i> Item Name</label>
                    <input type="text" name="bill_items[${idx}][item_name]" class="form-control item-name-input" list="medicationsList" placeholder="Start typing..." required oninput="onItemNameChange(this)">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-sort-numeric-up"></i> Quantity</label>
                    <input type="number" name="bill_items[${idx}][quantity]" class="form-control item-qty-input" value="1" min="1" required oninput="updateItemPrice(this)">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-money-bill-wave"></i> Unit Price (TSh)</label>
                    <input type="number" name="bill_items[${idx}][unit_price]" class="form-control item-price-input" value="0" min="0" step="0.01" required oninput="updateItemPrice(this)">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-calculator"></i> Line Total</label>
                    <div class="line-total-box"><span class="line-total-display">TSh 0</span></div>
                </div>
            </div>
            
            <div class="item-grid-med" style="display: grid;">
                <div class="med-section-title" style="grid-column: 1 / -1; margin-bottom: 0;">
                    <i class="fas fa-prescription-bottle-medical"></i> Medication Details
                </div>
                
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-prescription"></i> Dosage</label>
                    <input type="text" name="bill_items[${idx}][dosage]" class="form-control med-dosage-input" placeholder="e.g. 500mg">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-clock"></i> Frequency</label>
                    <input type="text" name="bill_items[${idx}][frequency]" class="form-control med-frequency-input" placeholder="e.g. 2x daily">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-route"></i> Route</label>
                    <input type="text" name="bill_items[${idx}][route]" class="form-control med-route-input" placeholder="e.g. Oral">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-calendar-alt"></i> Duration</label>
                    <input type="text" name="bill_items[${idx}][duration]" class="form-control med-duration-input" placeholder="e.g. 7 days">
                </div>
                
                <div class="form-group item-grid-med-instructions" style="margin: 0;">
                    <label><i class="fas fa-info-circle"></i> Instructions</label>
                    <input type="text" name="bill_items[${idx}][item_instructions]" class="form-control med-instructions-input" placeholder="e.g. Take after meal with plenty of water">
                </div>
            </div>
        </div>
    `;
}

// ================================================================
// BUILD OTHER ITEM HTML
// ================================================================
function buildOtherItemHTML(idx, type, icon, label) {
    return `
        <div class="bill-item" data-index="${idx}" data-type="${type}">
            <div class="item-number">${idx}</div>
            <button type="button" class="item-remove" onclick="removeItem(this)" title="Remove">
                <i class="fas fa-times"></i>
            </button>
            
            <span class="item-type-badge ${type}">
                <i class="fas ${icon}"></i> ${label}
            </span>
            <input type="hidden" name="bill_items[${idx}][item_type]" class="item-type-input" value="${type}">
            
            <div class="item-grid-basic">
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-tag"></i> Item Name</label>
                    <input type="text" name="bill_items[${idx}][item_name]" class="form-control item-name-input" value="${label}" placeholder="Enter item name..." required oninput="updateItemPrice(this)">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-sort-numeric-up"></i> Quantity</label>
                    <input type="number" name="bill_items[${idx}][quantity]" class="form-control item-qty-input" value="1" min="1" required oninput="updateItemPrice(this)">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-money-bill-wave"></i> Unit Price (TSh)</label>
                    <input type="number" name="bill_items[${idx}][unit_price]" class="form-control item-price-input" value="0" min="0" step="0.01" required oninput="updateItemPrice(this)">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label><i class="fas fa-calculator"></i> Line Total</label>
                    <div class="line-total-box"><span class="line-total-display">TSh 0</span></div>
                </div>
            </div>
        </div>
    `;
}

// ================================================================
// ADD MEDICATION
// ================================================================
function addMedication() {
    itemCounter++;
    container.insertAdjacentHTML('beforeend', buildMedicationHTML(itemCounter));
    renumberItems();
    updateGrandTotal();
    
    var newItem = container.querySelector('.bill-item:last-child');
    if (newItem) {
        var nameInput = newItem.querySelector('.item-name-input');
        if (nameInput) nameInput.focus();
    }
}

// ================================================================
// ADD OTHER ITEM
// ================================================================
function addOtherItem() {
    var choice = prompt(
        "Select item type:\n\n" +
        "1 = Consultation\n" +
        "2 = Lab Test\n" +
        "3 = Procedure\n" +
        "4 = Registration\n" +
        "5 = Equipment\n" +
        "6 = Other\n\n" +
        "Enter number:",
        "1"
    );
    
    if (!choice) return;
    
    var typeMap = {
        '1': { type: 'consultation', icon: 'fa-stethoscope', label: 'Consultation' },
        '2': { type: 'lab_test', icon: 'fa-flask', label: 'Lab Test' },
        '3': { type: 'procedure', icon: 'fa-syringe', label: 'Procedure' },
        '4': { type: 'registration', icon: 'fa-user-plus', label: 'Registration' },
        '5': { type: 'equipment', icon: 'fa-tools', label: 'Equipment' },
        '6': { type: 'other', icon: 'fa-ellipsis-h', label: 'Other' }
    };
    
    var selected = typeMap[choice.trim()];
    if (!selected) return;
    
    itemCounter++;
    container.insertAdjacentHTML('beforeend', buildOtherItemHTML(itemCounter, selected.type, selected.icon, selected.label));
    renumberItems();
    updateGrandTotal();
    
    var newItem = container.querySelector('.bill-item:last-child');
    if (newItem) {
        var nameInput = newItem.querySelector('.item-name-input');
        if (nameInput) nameInput.focus();
    }
}

// ================================================================
// REMOVE ITEM
// ================================================================
function removeItem(btn) {
    var item = btn.closest('.bill-item');
    if (!item) return;
    
    var items = document.querySelectorAll('.bill-item');
    if (items.length <= 1) {
        alert('At least one item is required.');
        return;
    }
    
    if (!confirm('Remove this item?')) return;
    
    item.style.transition = 'all 0.3s ease';
    item.style.opacity = '0';
    item.style.transform = 'translateX(-20px)';
    
    setTimeout(function() {
        item.remove();
        renumberItems();
        updateGrandTotal();
    }, 300);
}

// ================================================================
// RENUMBER ITEMS
// ================================================================
function renumberItems() {
    document.querySelectorAll('.bill-item').forEach(function(item, idx) {
        var numEl = item.querySelector('.item-number');
        if (numEl) numEl.textContent = idx + 1;
    });
}

// ================================================================
// INIT ON LOAD
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    updateGrandTotal();
});

// ================================================================
// FORM SUBMIT VALIDATION
// ================================================================
document.getElementById('editForm').addEventListener('submit', function(e) {
    var items = document.querySelectorAll('.bill-item');
    var hasValid = false;
    
    items.forEach(function(item) {
        var nameInput = item.querySelector('.item-name-input');
        var qtyInput = item.querySelector('.item-qty-input');
        if (nameInput?.value.trim() && parseInt(qtyInput?.value) > 0) {
            hasValid = true;
        }
    });
    
    if (!hasValid) {
        e.preventDefault();
        alert('Please add at least one valid item with quantity.');
        return false;
    }
    
    return confirm('Save changes to this prescription?\n\nChanges will update the bill and stock records.');
});

console.log('%cEdit Prescription V3 - Dosage/Frequency/Route', 'font-size:16px;font-weight:bold;color:#F59E0B;');
console.log('%cMedications have: Dosage, Frequency, Route, Duration, Instructions', 'font-size:12px;color:#34D399;');
console.log('%cNon-medications have: Name, Qty, Unit Price', 'font-size:12px;color:#34D399;');
</script>

</body>
</html>