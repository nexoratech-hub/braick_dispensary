<?php
// ================================================================
// FILE: frontend/pages/admin/view_pharmacy.php
// SUPER ADMIN - VIEW PHARMACY BRANCH DETAILS (V7 - PRESCRIPTION GROSS)
// ✅ V7: PRESCRIPTION = Medication_RAW (GROSS - bila discount, bila premium)
// ✅ V7: Prescription BILA round off (exact value, 2 decimal places)
// ✅ V7: Formula breakdown card imeondolewa (Prescription ipo Card pekee)
// ✅ SAWA KWA 100% NA AUDIT V13, ADMIN V20, CASHIERS V13.1, VIEW_CASHIER V14, PHARMACIES V3
// ✅ Recent Prescriptions: GROUPED BY PATIENT
// ✅ Blue theme + full dark mode support
// ✅ JetBrains Mono font kwa numbers
// ✅ Inventory button inafilter kwa BRANCH
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET SESSION DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// ✅ V7: ROUND TO NEAREST 50 FUNCTION (kwa cards zingine)
// ================================================================
function round_to_50($value) {
    return round($value / 50) * 50;
}

// ================================================================
// GET BRANCH ID
// ================================================================
$pharmacy_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

if ($pharmacy_id <= 0) {
    header('Location: pharmacies.php?branch=' . $selected_branch_id . '&error=invalid_id');
    exit;
}

// ================================================================
// ✅ V7: FETCH PHARMACY DETAILS - PRESCRIPTION GROSS
// Formula: Medication_RAW (GROSS - bila discount, bila premium)
// ================================================================
$stmt = $db->prepare("
    SELECT 
        b.*,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy' AND status = 'active') as active_pharmacists,
        (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy') as total_pharmacists,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active') as total_medicines,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND quantity <= reorder_level AND quantity > 0) as low_stock_items,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND quantity <= 0) as out_of_stock_items,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'pending') as pending_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'dispensed') as dispensed_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'confirmed') as confirmed_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id AND status = 'cancelled') as cancelled_prescriptions,
        (SELECT COUNT(*) FROM prescriptions WHERE branch_id = b.id) as total_prescriptions,
        
        -- ✅ V7: Medication RAW (GROSS - bila discount)
        (SELECT COALESCE(SUM(bi.total_price), 0) 
         FROM bill_items bi
         INNER JOIN bills bl ON bi.bill_id = bl.id
         WHERE bi.item_type = 'medication' 
         AND bi.status != 'cancelled'
         AND bl.status IN ('paid', 'partial')
         AND bl.patient_id IS NOT NULL
         AND bl.visit_id IS NOT NULL
         AND bl.bill_number NOT LIKE 'BILL-OTC-%'
         AND bl.branch_id = b.id) as medication_revenue_raw,
        
        -- Pharmacy Discount (info only - haipunguzwi kwenye Prescription)
        (SELECT COALESCE(SUM(bl.pharmacy_discount), 0) 
         FROM bills bl
         WHERE bl.status IN ('paid', 'partial')
         AND bl.patient_id IS NOT NULL
         AND bl.visit_id IS NOT NULL
         AND bl.bill_number NOT LIKE 'BILL-OTC-%'
         AND bl.pharmacy_discount > 0
         AND bl.branch_id = b.id) as pharmacy_discount_total,
        
        -- Pharmacy Premium (info only)
        (SELECT COALESCE(SUM(bl.pharmacy_premium), 0) 
         FROM bills bl
         WHERE bl.status IN ('paid', 'partial')
         AND bl.patient_id IS NOT NULL
         AND bl.visit_id IS NOT NULL
         AND bl.bill_number NOT LIKE 'BILL-OTC-%'
         AND bl.pharmacy_premium > 0
         AND bl.branch_id = b.id) as pharmacy_premium_total,
        
        (SELECT COUNT(*) FROM otc_sales WHERE branch_id = b.id) as total_otc_sales,
        (SELECT COALESCE(SUM(total_amount), 0) 
         FROM otc_sales 
         WHERE branch_id = b.id 
         AND payment_status = 'paid') as otc_revenue,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND expiry_date < CURDATE()) as expired_medicines,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) as expiring_soon_medicines,
        (SELECT COUNT(*) FROM medications_inventory WHERE branch_id = b.id AND status = 'active') as total_active_medicines
    FROM branches b
    WHERE b.id = ?
");
$stmt->execute([$pharmacy_id]);
$pharmacy = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pharmacy) {
    header('Location: pharmacies.php?branch=' . $selected_branch_id . '&error=notfound');
    exit;
}

// ================================================================
// ✅ V7: CALCULATE PRESCRIPTION REVENUE = GROSS
// ================================================================
$medication_revenue_raw = (float)($pharmacy['medication_revenue_raw'] ?? 0);
$pharmacy_discount = (float)($pharmacy['pharmacy_discount_total'] ?? 0);
$pharmacy_premium = (float)($pharmacy['pharmacy_premium_total'] ?? 0);

// ✅ V7: Prescription Revenue = Medication_RAW (GROSS - bila discount, bila premium, bila round)
$prescription_revenue = $medication_revenue_raw;

$otc_revenue = (float)($pharmacy['otc_revenue'] ?? 0);
$otc_revenue = round_to_50($otc_revenue);
$medication_revenue_raw = round_to_50($medication_revenue_raw);
$pharmacy_discount = round_to_50($pharmacy_discount);
$pharmacy_premium = round_to_50($pharmacy_premium);

$total_revenue = $prescription_revenue + $otc_revenue;

// ================================================================
// BUILD INVENTORY URL
// ================================================================
$inventory_base_url = '/dispensary_system/frontend/pages/admin/inventory.php';
$inventory_url = $inventory_base_url . '?branch=' . $pharmacy['id'];
$inventory_url_out = $inventory_base_url . '?branch=' . $pharmacy['id'] . '&stock_filter=out';
$inventory_url_low = $inventory_base_url . '?branch=' . $pharmacy['id'] . '&stock_filter=low';
$inventory_url_expired = $inventory_base_url . '?branch=' . $pharmacy['id'] . '&filter=expired';
$inventory_url_expiring = $inventory_base_url . '?branch=' . $pharmacy['id'] . '&filter=expiring';

// ================================================================
// GET PHARMACISTS
// ================================================================
$pharmacists = [];
try {
    $stmt = $db->prepare("
        SELECT id, full_name, email, phone, status, created_at 
        FROM users 
        WHERE branch_id = ? AND role = 'pharmacy'
        ORDER BY full_name
    ");
    $stmt->execute([$pharmacy_id]);
    $pharmacists = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $pharmacists = []; }

// ================================================================
// ✅ RECENT PRESCRIPTIONS - GROUPED BY PATIENT
// ================================================================
$recent_prescriptions = [];
try {
    // Hatua 1: Pata patients wa hivi karibuni (unique)
    $stmt = $db->prepare("
        SELECT DISTINCT 
            pat.id as patient_id,
            pat.full_name as patient_name,
            pat.patient_id as patient_code,
            pat.phone as patient_phone,
            MAX(p.created_at) as last_prescription_date
        FROM prescriptions p
        INNER JOIN patients pat ON p.patient_id = pat.id
        WHERE p.branch_id = ?
        GROUP BY pat.id, pat.full_name, pat.patient_id, pat.phone
        ORDER BY last_prescription_date DESC
        LIMIT 10
    ");
    $stmt->execute([$pharmacy_id]);
    $recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Hatua 2: Kwa kila patient, pata prescriptions zake zote
    foreach ($recent_patients as $patient) {
        $patient_id = $patient['patient_id'];
        
        $stmt = $db->prepare("
            SELECT 
                p.id,
                p.visit_id,
                p.prescription_number,
                p.status,
                p.created_at,
                v.visit_number,
                v.visit_date,
                u.full_name as doctor_name,
                (
                    SELECT GROUP_CONCAT(DISTINCT pi.medication_name SEPARATOR '||')
                    FROM prescription_items pi
                    WHERE pi.prescription_id = p.id
                ) as medicines_list,
                (
                    SELECT COUNT(*) 
                    FROM prescription_items pi
                    WHERE pi.prescription_id = p.id
                ) as medicines_count,
                COALESCE((
                    SELECT SUM(bi.total_price) 
                    FROM bill_items bi
                    WHERE bi.reference_id = p.id 
                    AND bi.reference_type = 'prescription'
                    AND bi.item_type = 'medication'
                    AND bi.status != 'cancelled'
                ), 0) as total_amount,
                COALESCE((
                    SELECT SUM(bi.discount_amount) 
                    FROM bill_items bi
                    WHERE bi.reference_id = p.id 
                    AND bi.reference_type = 'prescription'
                ), 0) as discount_amount
            FROM prescriptions p
            LEFT JOIN visits v ON p.visit_id = v.id
            LEFT JOIN users u ON p.doctor_id = u.id
            WHERE p.patient_id = ? AND p.branch_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$patient_id, $pharmacy_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by visit_id
        $visits_data = [];
        foreach ($prescriptions as $rx) {
            $visit_id = $rx['visit_id'] ?? 0;
            $visit_key = $visit_id > 0 ? 'v_' . $visit_id : 'no_visit_' . $rx['id'];
            
            if (!isset($visits_data[$visit_key])) {
                $visits_data[$visit_key] = [
                    'visit_id' => $visit_id,
                    'visit_number' => $rx['visit_number'] ?? 'N/A',
                    'visit_date' => $rx['visit_date'] ?? $rx['created_at'],
                    'doctor_name' => $rx['doctor_name'] ?? 'N/A',
                    'prescriptions' => [],
                    'total_amount' => 0,
                    'total_discount' => 0,
                    'total_medicines' => 0
                ];
            }
            
            $meds = !empty($rx['medicines_list']) ? explode('||', $rx['medicines_list']) : [];
            
            $visits_data[$visit_key]['prescriptions'][] = [
                'prescription_number' => $rx['prescription_number'],
                'status' => $rx['status'],
                'created_at' => $rx['created_at'],
                'medicines' => $meds,
                'medicines_count' => (int)$rx['medicines_count'],
                'total_amount' => (float)$rx['total_amount'],
                'discount_amount' => (float)$rx['discount_amount']
            ];
            
            $visits_data[$visit_key]['total_amount'] += (float)$rx['total_amount'];
            $visits_data[$visit_key]['total_discount'] += (float)$rx['discount_amount'];
            $visits_data[$visit_key]['total_medicines'] += (int)$rx['medicines_count'];
        }
        
        $patient['visits'] = array_values($visits_data);
        $patient['visit_count'] = count($visits_data);
        $patient['prescription_count'] = count($prescriptions);
        
        $patient_total = 0;
        $patient_medicines = 0;
        foreach ($visits_data as $v) {
            $patient_total += $v['total_amount'];
            $patient_medicines += $v['total_medicines'];
        }
        $patient['grand_total'] = $patient_total;
        $patient['grand_medicines'] = $patient_medicines;
        
        $recent_prescriptions[] = $patient;
    }
    
} catch (Exception $e) { 
    error_log("Grouped prescriptions error: " . $e->getMessage());
    $recent_prescriptions = []; 
}

// ================================================================
// GET RECENT INVENTORY
// ================================================================
$recent_inventory = [];
try {
    $stmt = $db->prepare("
        SELECT id, medication_name, category, quantity, reorder_level,
               selling_price, expiry_date, status, updated_at
        FROM medications_inventory
        WHERE branch_id = ?
        ORDER BY updated_at DESC
        LIMIT 10
    ");
    $stmt->execute([$pharmacy_id]);
    $recent_inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recent_inventory = []; }

// ================================================================
// GET RECENT OTC SALES
// ================================================================
$recent_otc_sales = [];
try {
    $stmt = $db->prepare("
        SELECT 
            os.id, 
            os.sale_number, 
            os.customer_name, 
            os.total_amount, 
            os.subtotal as net_amount,
            os.discount_amount, 
            os.payment_method, 
            os.payment_status, 
            os.created_at,
            (
                SELECT GROUP_CONCAT(DISTINCT osi.item_name SEPARATOR ', ')
                FROM otc_sale_items osi
                WHERE osi.sale_id = os.id
            ) as medicines_list,
            (
                SELECT COUNT(*) 
                FROM otc_sale_items osi
                WHERE osi.sale_id = os.id
            ) as medicines_count
        FROM otc_sales os
        WHERE os.branch_id = ?
        ORDER BY os.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$pharmacy_id]);
    $recent_otc_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recent_otc_sales = []; }

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $unread_notifications = 0; }

// ================================================================
// HELPERS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'active' => 'success', 'inactive' => 'danger',
        'pending' => 'warning', 'dispensed' => 'success',
        'confirmed' => 'info', 'cancelled' => 'danger',
        'paid' => 'success', 'partial' => 'warning', 'unpaid' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

function format_currency($amount) {
    if ($amount == 0) return 'TSh 0';
    return 'TSh ' . number_format($amount, 0);
}

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$total_employees = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_doctors = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_branches = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
$total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$pending_lab_tests = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending'");
    $pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) { $pending_lab_tests = 0; }

$pending_prescriptions = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
    $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) { $pending_prescriptions = 0; }

// ================================================================
// ✅ SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- GOOGLE FONTS - JETBRAINS MONO -->
<!-- ================================================================ -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS - V7 DESIGN MPYA -->
<!-- ================================================================ -->
<style>
    :root {
        --font-mono: 'JetBrains Mono', 'Courier New', monospace;
        --primary: #0B5ED7;
        --primary-dark: #0A4CA8;
        --success: #059669;
        --danger: #DC2626;
        --warning: #D97706;
        --purple: #7C3AED;
        --bg-card: #FFFFFF;
        --bg-body: #F1F5F9;
        --text-primary: #1E293B;
        --text-secondary: #64748B;
        --border-color: #E2E8F0;
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    }
    
    [data-theme="dark"] {
        --bg-card: #1E293B;
        --bg-body: #0F172A;
        --text-primary: #F1F5F9;
        --text-secondary: #94A3B8;
        --border-color: #334155;
    }
    
    .font-mono,
    .money-number,
    .prescription-number,
    .sale-number,
    .stat-number-small,
    .stat-amount-large,
    .phone-number,
    .date-value,
    .patient-rx-num,
    .patient-rx-avatar,
    .visit-rx-number,
    .patient-rx-total-value {
        font-family: var(--font-mono) !important;
        font-feature-settings: 'tnum' 1;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
    }
    
    /* PAGE HEADER */
    .page-header-pharm {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
        border-radius: 18px;
        padding: 26px 34px;
        margin-bottom: 26px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
        position: relative;
        overflow: hidden;
    }

    .page-header-pharm::before {
        content: '';
        position: absolute;
        top: -60%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-pharm .page-title {
        color: white;
        font-size: 1.7rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
        font-family: var(--font-mono);
    }

    .page-header-pharm .page-subtitle {
        color: rgba(255,255,255,0.88);
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 6px;
    }

    .page-header-pharm .role-badge-display {
        background: linear-gradient(135deg, #FCD34D, #F59E0B);
        color: #78350F;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .page-header-pharm .header-badge {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        backdrop-filter: blur(4px);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(255,255,255,0.1);
        transition: all 0.3s ease;
        font-family: var(--font-mono);
    }

    .page-header-pharm .header-badge:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-1px);
    }

    .page-header-pharm .btn-outline-light {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.82rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        backdrop-filter: blur(4px);
        position: relative;
        z-index: 1;
        cursor: pointer;
        font-family: var(--font-mono);
    }

    .page-header-pharm .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* DETAIL CARD */
    .detail-card-pharm {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 22px 26px;
        border: 2px solid var(--border-color);
        box-shadow: var(--shadow-sm);
        transition: all 0.3s ease;
        margin-bottom: 24px;
    }

    .detail-card-pharm:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
    }

    .detail-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 18px;
        padding-bottom: 14px;
        border-bottom: 2px dashed var(--border-color);
        flex-wrap: wrap;
        gap: 10px;
    }

    .detail-card-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
        font-family: var(--font-mono);
    }

    .detail-card-title i {
        color: var(--primary);
        font-size: 1.1rem;
    }

    .btn-inventory {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 20px;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 2px solid transparent;
        cursor: pointer;
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
        font-family: var(--font-mono);
    }

    .btn-inventory:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(5, 150, 105, 0.4);
        background: linear-gradient(135deg, #047857, #065F46);
        color: white;
    }

    .btn-inventory .count-badge {
        background: rgba(255,255,255,0.25);
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 800;
        font-family: var(--font-mono);
        margin-left: 4px;
    }

    .detail-grid-pharm {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 18px;
    }

    .detail-label-pharm {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin: 0 0 4px 0;
        font-family: var(--font-mono);
    }

    .detail-value-pharm {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    /* STATS GRID */
    .stats-grid-8-pharm {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 24px;
    }

    .stat-card-8-pharm {
        border-radius: 14px;
        padding: 16px 18px;
        border: none;
        display: flex;
        flex-direction: column;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        color: white;
        position: relative;
        overflow: hidden;
        min-height: 130px;
        cursor: pointer;
        text-decoration: none;
    }

    .stat-card-8-pharm::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 160px;
        height: 160px;
        background: rgba(255,255,255,0.06);
        border-radius: 50%;
        pointer-events: none;
        transition: all 0.5s ease;
    }

    .stat-card-8-pharm:hover {
        transform: translateY(-4px) scale(1.01);
        box-shadow: 0 10px 32px rgba(0,0,0,0.2);
    }

    .stat-card-8-pharm:hover::before {
        transform: scale(1.3);
        right: -10%;
    }

    .stat-card-8-pharm .stat-icon-pharm {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
        background: rgba(255,255,255,0.18);
        color: white;
        border: 1px solid rgba(255,255,255,0.12);
        backdrop-filter: blur(8px);
        transition: all 0.3s ease;
        position: relative;
        z-index: 1;
        margin-bottom: 4px;
    }

    .stat-card-8-pharm:hover .stat-icon-pharm {
        transform: scale(1.05) rotate(-2deg);
        background: rgba(255,255,255,0.3);
    }

    .stat-card-8-pharm .stat-content-pharm {
        position: relative;
        z-index: 1;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .stat-card-8-pharm .stat-label-pharm {
        font-size: 0.6rem;
        color: rgba(255,255,255,0.85);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin: 0 0 1px 0;
        font-family: var(--font-mono);
    }

    .stat-card-8-pharm .stat-number-small {
        font-size: 2.2rem;
        font-weight: 800;
        color: white;
        margin: 0;
        line-height: 1.1;
    }

    .stat-card-8-pharm .stat-amount-large {
        font-size: 1.6rem;
        font-weight: 800;
        color: white;
        margin: 0;
        line-height: 1.2;
    }

    .stat-card-8-pharm .stat-sub-pharm {
        font-size: 0.6rem;
        color: rgba(255,255,255,0.9);
        margin-top: 3px;
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        font-family: var(--font-mono);
    }

    .stat-card-8-pharm .stat-arrow-pharm {
        position: absolute;
        right: 12px;
        bottom: 12px;
        color: rgba(255,255,255,0.12);
        font-size: 0.7rem;
        transition: all 0.3s ease;
        z-index: 1;
    }

    .stat-card-8-pharm:hover .stat-arrow-pharm {
        transform: translateX(6px);
        color: rgba(255,255,255,0.4);
    }

    .card-blue-pharm { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .card-blue-pharm:hover { box-shadow: 0 10px 32px rgba(11, 94, 215, 0.4); }
    .card-red-pharm { background: linear-gradient(135deg, #DC2626, #B91C1C); }
    .card-red-pharm:hover { box-shadow: 0 10px 32px rgba(220, 38, 38, 0.4); }
    .card-green-pharm { background: linear-gradient(135deg, #059669, #047857); }
    .card-green-pharm:hover { box-shadow: 0 10px 32px rgba(5, 150, 105, 0.4); }
    .card-orange-pharm { background: linear-gradient(135deg, #D97706, #B45309); }
    .card-orange-pharm:hover { box-shadow: 0 10px 32px rgba(217, 119, 6, 0.4); }
    .card-purple-pharm { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    .card-purple-pharm:hover { box-shadow: 0 10px 32px rgba(124, 58, 237, 0.4); }

    /* CARDS */
    .card-pharm {
        background: var(--bg-card);
        border-radius: 16px;
        border: 2px solid var(--border-color);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        margin-bottom: 24px;
        transition: all 0.3s ease;
    }

    .card-pharm:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
    }

    .card-header-pharm {
        padding: 14px 20px;
        background: linear-gradient(135deg, #0A4CA8, #083C8A);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        position: relative;
        overflow: hidden;
    }

    .card-header-pharm::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.04);
        border-radius: 50%;
        pointer-events: none;
    }

    .card-header-pharm .card-title-pharm {
        color: white;
        font-size: 0.9rem;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
        position: relative;
        z-index: 1;
        font-family: var(--font-mono);
    }

    .card-header-pharm .card-title-pharm i {
        font-size: 1rem;
        color: rgba(255,255,255,0.9);
    }

    .card-header-pharm .card-action-pharm {
        color: rgba(255,255,255,0.7);
        font-size: 0.7rem;
        text-decoration: none;
        transition: all 0.3s;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        position: relative;
        z-index: 1;
        font-family: var(--font-mono);
    }

    .card-header-pharm .card-action-pharm:hover {
        color: white;
        transform: translateX(2px);
    }

    .card-body-pharm {
        padding: 0;
        overflow-x: auto;
    }

    /* DATA TABLE */
    .data-table-pharm {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.78rem;
        font-family: var(--font-mono);
    }

    .data-table-pharm thead {
        background: var(--bg-body);
    }

    [data-theme="dark"] .data-table-pharm thead {
        background: #0F172A;
    }

    .data-table-pharm thead th {
        padding: 10px 14px;
        text-align: left;
        font-weight: 700;
        color: var(--text-secondary);
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--border-color);
        white-space: nowrap;
        font-family: var(--font-mono);
    }

    .data-table-pharm td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: top;
        font-family: var(--font-mono);
    }

    .data-table-pharm tr:hover td {
        background: var(--bg-body);
    }

    [data-theme="dark"] .data-table-pharm tr:hover td {
        background: #0F172A;
    }

    .data-table-pharm tr:last-child td {
        border-bottom: none;
    }

    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-semibold { font-weight: 600; }
    .font-medium { font-weight: 500; }
    .text-xs { font-size: 0.7rem; }
    .text-blue-600 { color: #0B5ED7; }
    .text-green-600 { color: #059669; }
    .text-red-600 { color: #DC2626; }
    .text-yellow-600 { color: #D97706; }
    .text-purple-600 { color: #7C3AED; }
    .text-gray-400 { color: #94A3B8; }
    .text-gray-500 { color: #64748B; }
    [data-theme="dark"] .text-blue-600 { color: #60A5FA; }
    [data-theme="dark"] .text-green-600 { color: #34D399; }
    [data-theme="dark"] .text-red-600 { color: #F87171; }
    [data-theme="dark"] .text-yellow-600 { color: #FBBF24; }
    [data-theme="dark"] .text-purple-600 { color: #A78BFA; }

    /* MEDICINES CELL */
    .medicines-cell {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 180px;
        max-width: 280px;
    }
    
    .med-item {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 10px;
        border-radius: 8px;
        font-size: 0.7rem;
        font-weight: 600;
        background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
        color: #1E40AF;
        border: 1px solid #BFDBFE;
        width: fit-content;
        max-width: 100%;
        transition: all 0.2s ease;
        font-family: var(--font-mono);
    }
    
    .med-item:hover {
        transform: translateX(2px);
        box-shadow: 0 2px 6px rgba(11, 94, 215, 0.15);
    }
    
    .med-item i {
        font-size: 0.65rem;
        color: #0B5ED7;
        flex-shrink: 0;
    }
    
    .med-item span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .med-item.otc {
        background: linear-gradient(135deg, #D1FAE5, #A7F3D0);
        color: #047857;
        border-color: #6EE7B7;
    }
    
    .med-item.otc i {
        color: #059669;
    }
    
    .med-more {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 10px;
        border-radius: 8px;
        font-size: 0.68rem;
        font-weight: 700;
        background: linear-gradient(135deg, #FEF3C7, #FDE68A);
        color: #92400E;
        border: 1px dashed #F59E0B;
        width: fit-content;
        cursor: pointer;
        transition: all 0.2s ease;
        user-select: none;
        font-family: var(--font-mono);
    }
    
    .med-more:hover {
        background: linear-gradient(135deg, #FDE68A, #FCD34D);
        transform: translateX(2px);
        box-shadow: 0 2px 6px rgba(245, 158, 11, 0.25);
    }
    
    .med-more i {
        font-size: 0.65rem;
        color: #D97706;
    }
    
    [data-theme="dark"] .med-item {
        background: #1E3A5F;
        color: #93C5FD;
        border-color: #3B82F6;
    }
    
    [data-theme="dark"] .med-item i {
        color: #60A5FA;
    }
    
    [data-theme="dark"] .med-item.otc {
        background: #1A3A2A;
        color: #6EE7B7;
        border-color: #059669;
    }
    
    [data-theme="dark"] .med-item.otc i {
        color: #34D399;
    }
    
    [data-theme="dark"] .med-more {
        background: #3D2E0A;
        color: #FBBF24;
        border-color: #D97706;
    }
    
    [data-theme="dark"] .med-more i {
        color: #FCD34D;
    }

    /* STATUS BADGES */
    .status-badge-pharm {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 700;
        white-space: nowrap;
        font-family: var(--font-mono);
    }

    .status-badge-pharm.success { background: #D1FAE5; color: #059669; }
    .status-badge-pharm.danger { background: #FEE2E2; color: #DC2626; }
    .status-badge-pharm.warning { background: #FEF3C7; color: #D97706; }
    .status-badge-pharm.info { background: #EFF6FF; color: #0B5ED7; }
    .status-badge-pharm.secondary { background: #F1F5F9; color: #64748B; }

    [data-theme="dark"] .status-badge-pharm.success { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge-pharm.danger { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .status-badge-pharm.warning { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .status-badge-pharm.info { background: #1E3A5F; color: #3B82F6; }
    [data-theme="dark"] .status-badge-pharm.secondary { background: #2D3748; color: #94A3B8; }

    /* BUTTONS */
    .btn-add-pharm {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 700;
        text-decoration: none;
        background: rgba(255,255,255,0.2);
        color: white;
        border: 1px solid rgba(255,255,255,0.3);
        transition: all 0.3s ease;
        font-family: var(--font-mono);
        position: relative;
        z-index: 1;
    }

    .btn-add-pharm:hover {
        background: rgba(255,255,255,0.35);
        color: white;
        transform: translateY(-2px);
    }

    .btn-edit-small {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 700;
        text-decoration: none;
        background: rgba(11, 94, 215, 0.1);
        color: #0B5ED7;
        border: 1px solid rgba(11, 94, 215, 0.3);
        transition: all 0.2s ease;
        font-family: var(--font-mono);
    }

    .btn-edit-small:hover {
        background: #0B5ED7;
        color: white;
        transform: translateY(-1px);
    }

    /* EMPTY STATE */
    .empty-state-pharm {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-secondary);
        font-family: var(--font-mono);
    }

    .empty-state-pharm i {
        font-size: 2.5rem;
        color: var(--border-color);
        margin-bottom: 8px;
        display: block;
    }

    /* ✅ PATIENT RX GROUP */
    .patients-rx-container {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 16px;
        background: var(--bg-body);
    }

    .patient-rx-group {
        background: var(--bg-card);
        border-radius: 14px;
        border: 2px solid var(--border-color);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .patient-rx-group:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.1);
    }

    .patient-rx-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        padding: 14px 20px;
        background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
        cursor: pointer;
        transition: all 0.3s ease;
        flex-wrap: wrap;
    }

    [data-theme="dark"] .patient-rx-header {
        background: linear-gradient(135deg, #1E3A5F, #16294A);
    }

    .patient-rx-header:hover {
        background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
    }

    [data-theme="dark"] .patient-rx-header:hover {
        background: linear-gradient(135deg, #1E40AF, #1E3A5F);
    }

    .patient-rx-left {
        display: flex;
        align-items: center;
        gap: 14px;
        flex: 1;
        min-width: 200px;
    }

    .patient-rx-num {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.85rem;
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.3);
    }

    .patient-rx-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1.15rem;
        flex-shrink: 0;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15);
        border: 2px solid white;
    }

    [data-theme="dark"] .patient-rx-avatar {
        border-color: #1E293B;
    }

    .patient-rx-info {
        flex: 1;
        min-width: 0;
    }

    .patient-rx-name {
        font-size: 0.95rem;
        font-weight: 800;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 4px;
    }

    .patient-rx-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: rgba(11, 94, 215, 0.12);
        color: var(--primary);
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 700;
        font-family: var(--font-mono);
    }

    .patient-rx-meta {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 0.68rem;
        color: var(--text-secondary);
        flex-wrap: wrap;
        font-weight: 500;
        font-family: var(--font-mono);
    }

    .patient-rx-meta span {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .patient-rx-meta i {
        font-size: 0.62rem;
        opacity: 0.75;
    }

    .patient-rx-right {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .patient-rx-total {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        padding: 6px 14px;
        background: linear-gradient(135deg, #059669, #047857);
        border-radius: 10px;
        color: white;
        box-shadow: 0 3px 10px rgba(5, 150, 105, 0.25);
    }

    .patient-rx-total-label {
        font-size: 0.55rem;
        font-weight: 700;
        opacity: 0.9;
        letter-spacing: 0.06em;
        font-family: var(--font-mono);
    }

    .patient-rx-total-value {
        font-size: 0.95rem;
        font-weight: 800;
    }

    .patient-rx-chevron {
        color: var(--text-secondary);
        font-size: 0.9rem;
        transition: transform 0.3s ease;
        flex-shrink: 0;
    }

    .patient-rx-chevron.rotated {
        transform: rotate(180deg);
    }

    .patient-rx-body {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.5s ease, padding 0.3s ease;
        padding: 0 16px;
    }

    .patient-rx-body.open {
        max-height: 8000px;
        padding: 16px;
    }

    /* VISIT BLOCK */
    .visit-rx-block {
        background: var(--bg-body);
        border-radius: 12px;
        border: 2px solid var(--border-color);
        overflow: hidden;
        margin-bottom: 14px;
        transition: all 0.3s ease;
    }

    .visit-rx-block:last-child {
        margin-bottom: 0;
    }

    .visit-rx-block:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
    }

    .visit-rx-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        background: linear-gradient(135deg, #FEF3C7, #FDE68A);
        border-bottom: 2px solid var(--border-color);
        flex-wrap: wrap;
    }

    [data-theme="dark"] .visit-rx-header {
        background: linear-gradient(135deg, #3D2E0A, #2D2015);
    }

    .visit-rx-left {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .visit-rx-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: linear-gradient(135deg, #FCD34D, #F59E0B);
        color: #78350F;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
    }

    .visit-rx-number {
        font-weight: 800;
        font-size: 0.82rem;
        color: #78350F;
        background: rgba(255, 255, 255, 0.6);
        padding: 3px 12px;
        border-radius: 8px;
        border: 1px solid rgba(245, 158, 11, 0.3);
        font-family: var(--font-mono);
    }

    [data-theme="dark"] .visit-rx-number {
        background: rgba(255, 255, 255, 0.1);
        color: #FCD34D;
    }

    .visit-rx-date {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 600;
        font-family: var(--font-mono);
    }

    .visit-rx-doctor {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.72rem;
        color: var(--primary);
        font-weight: 700;
        background: rgba(11, 94, 215, 0.1);
        padding: 3px 10px;
        border-radius: 12px;
        font-family: var(--font-mono);
    }

    [data-theme="dark"] .visit-rx-doctor {
        background: rgba(59, 130, 246, 0.15);
        color: #93C5FD;
    }

    .visit-rx-right {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .visit-rx-stat {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.68rem;
        color: var(--text-secondary);
        font-weight: 700;
        background: rgba(255, 255, 255, 0.7);
        padding: 3px 10px;
        border-radius: 10px;
        border: 1px solid var(--border-color);
        font-family: var(--font-mono);
    }

    [data-theme="dark"] .visit-rx-stat {
        background: rgba(255, 255, 255, 0.08);
    }

    .visit-rx-stat-total {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        border-color: transparent;
        font-weight: 800;
        box-shadow: 0 2px 6px rgba(11, 94, 215, 0.25);
    }

    .visit-rx-body {
        padding: 0;
        overflow-x: auto;
    }

    .visit-table {
        width: 100%;
        border-collapse: collapse;
    }

    .visit-table thead th {
        background: var(--bg-card);
        padding: 8px 14px;
        font-size: 0.58rem;
        border-bottom: 1px solid var(--border-color);
    }

    [data-theme="dark"] .visit-table thead th {
        background: #1E293B;
    }

    .visit-table td {
        padding: 8px 14px;
        font-size: 0.72rem;
    }

    /* FOOTER */
    .footer-pharm {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-family: var(--font-mono);
    }

    .footer-pharm .footer-brand-pharm {
        color: var(--primary);
        font-weight: 700;
    }

    /* ANIMATIONS */
    @keyframes fadeInUpPharm {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up-pharm {
        animation: fadeInUpPharm 0.5s ease forwards;
        opacity: 0;
    }

    @keyframes pulse-dot {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }

    .pulse-dot {
        display: inline-block;
        width: 6px; height: 6px;
        border-radius: 50%;
        background: currentColor;
        margin-right: 4px;
        animation: pulse-dot 1.5s infinite;
    }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
        .stats-grid-8-pharm { grid-template-columns: repeat(4, 1fr); }
    }

    @media (max-width: 768px) {
        .stats-grid-8-pharm { grid-template-columns: 1fr 1fr; }
        .page-header-pharm { padding: 18px 20px; flex-direction: column; align-items: flex-start; }
        .page-header-pharm .page-title { font-size: 1.3rem; }
        .stat-card-8-pharm { padding: 14px 16px; min-height: 110px; }
        .stat-card-8-pharm .stat-number-small { font-size: 1.8rem; }
        .stat-card-8-pharm .stat-amount-large { font-size: 1.4rem; }
        .detail-card-header { flex-direction: column; align-items: flex-start; }
        .btn-inventory { width: 100%; justify-content: center; }
        .medicines-cell { min-width: 140px; max-width: 200px; }
        .med-item { font-size: 0.65rem; padding: 2px 8px; }
        
        .patients-rx-container { padding: 10px; gap: 8px; }
        .patient-rx-header { padding: 12px 14px; gap: 10px; }
        .patient-rx-avatar { width: 38px; height: 38px; font-size: 1rem; }
        .patient-rx-num { width: 26px; height: 26px; font-size: 0.75rem; }
        .patient-rx-name { font-size: 0.85rem; }
        .patient-rx-meta { font-size: 0.6rem; gap: 8px; }
        .patient-rx-total { padding: 4px 10px; }
        .patient-rx-total-value { font-size: 0.8rem; }
        .patient-rx-body.open { padding: 10px; }
        .visit-rx-header { padding: 8px 12px; }
        .visit-rx-number { font-size: 0.72rem; }
    }

    @media (max-width: 480px) {
        .stats-grid-8-pharm { grid-template-columns: 1fr; }
        .stat-card-8-pharm { padding: 12px 14px; min-height: 90px; }
        .page-header-pharm .page-title { font-size: 1.1rem; }
    }
</style>

<!-- MAIN CONTENT -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-pharm animate-fade-in-up-pharm">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription-bottle-medical"></i>
                Pharmacy Details
                <span class="role-badge-display"><i class="fas fa-user-shield"></i> ADMIN</span>
                <span class="header-badge" style="background:rgba(252,211,77,0.15);border-color:rgba(252,211,77,0.3);color:#FCD34D;">
                    <span class="pulse-dot"></span> V7 GROSS
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($pharmacy['name']) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-<?= $pharmacy['status'] === 'active' ? 'check-circle' : 'times-circle' ?>"></i>
                    <?= ucfirst($pharmacy['status']) ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#6EE7B7;">
                    <i class="fas fa-pills"></i> <span class="font-mono"><?= number_format($pharmacy['total_medicines'] ?? 0) ?></span> Medicines
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-money-bill-wave"></i> <span class="font-mono"><?= format_currency($total_revenue) ?></span>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="edit_pharmacy.php?id=<?= $pharmacy['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="pharmacies.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- PHARMACY INFO -->
    <div class="detail-card-pharm animate-fade-in-up-pharm" style="animation-delay:0.05s;">
        <div class="detail-card-header">
            <div class="detail-card-title">
                <i class="fas fa-info-circle"></i>
                Pharmacy Information
            </div>
            <a href="<?= $inventory_url ?>" class="btn-inventory" title="Open Inventory for <?= htmlspecialchars($pharmacy['name']) ?>">
                <i class="fas fa-boxes-stacked"></i>
                Inventory
                <span class="count-badge"><?= number_format($pharmacy['total_medicines'] ?? 0) ?></span>
            </a>
        </div>
        
        <div class="detail-grid-pharm">
            <div>
                <p class="detail-label-pharm"><i class="fas fa-map-marker-alt" style="margin-right:4px;"></i> Location</p>
                <p class="detail-value-pharm"><?= htmlspecialchars($pharmacy['location'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label-pharm"><i class="fas fa-phone" style="margin-right:4px;"></i> Phone</p>
                <p class="detail-value-pharm font-mono"><?= htmlspecialchars($pharmacy['phone'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label-pharm"><i class="fas fa-envelope" style="margin-right:4px;"></i> Email</p>
                <p class="detail-value-pharm"><?= htmlspecialchars($pharmacy['email'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label-pharm"><i class="fas fa-calendar-plus" style="margin-right:4px;"></i> Created</p>
                <p class="detail-value-pharm font-mono"><?= date('M d, Y h:i A', strtotime($pharmacy['created_at'] ?? 'now')) ?></p>
            </div>
            <div>
                <p class="detail-label-pharm"><i class="fas fa-user-md" style="margin-right:4px;"></i> Pharmacists</p>
                <p class="detail-value-pharm">
                    <span class="font-mono"><?= $pharmacy['active_pharmacists'] ?? 0 ?></span> Active / 
                    <span class="font-mono"><?= $pharmacy['total_pharmacists'] ?? 0 ?></span> Total
                </p>
            </div>
        </div>
    </div>

    <!-- 8 STATS CARDS -->
    <div class="stats-grid-8-pharm animate-fade-in-up-pharm" style="animation-delay:0.1s;">
        <a href="<?= $inventory_url ?>" class="stat-card-8-pharm card-blue-pharm">
            <div class="stat-icon-pharm"><i class="fas fa-pills"></i></div>
            <div class="stat-content-pharm">
                <p class="stat-label-pharm">Total Medicines</p>
                <p class="stat-number-small"><?= number_format($pharmacy['total_medicines'] ?? 0) ?></p>
                <p class="stat-sub-pharm">Active inventory items</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow-pharm"></i>
        </a>
        
        <!-- ✅ V7: Prescription Card = GROSS (Bila round off) -->
        <a href="prescriptions.php?branch=<?= $pharmacy['id'] ?>&filter=all" class="stat-card-8-pharm card-purple-pharm">
            <div class="stat-icon-pharm"><i class="fas fa-prescription"></i></div>
            <div class="stat-content-pharm">
                <p class="stat-label-pharm">Prescriptions (Gross)</p>
                <div>
                    <span class="stat-number-small"><?= number_format($pharmacy['total_prescriptions'] ?? 0) ?></span>
                    <span class="stat-amount-large" style="font-size:1.1rem;display:block;">TSh <?= number_format($prescription_revenue, 2, '.', ',') ?></span>
                </div>
                <p class="stat-sub-pharm"><?= $pharmacy['pending_prescriptions'] ?? 0 ?> pending · <?= $pharmacy['dispensed_prescriptions'] ?? 0 ?> dispensed</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow-pharm"></i>
        </a>
        
        <a href="otc_sales.php?branch=<?= $pharmacy['id'] ?>" class="stat-card-8-pharm card-orange-pharm">
            <div class="stat-icon-pharm"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-content-pharm">
                <p class="stat-label-pharm">OTC Sales</p>
                <div>
                    <span class="stat-number-small"><?= number_format($pharmacy['total_otc_sales'] ?? 0) ?></span>
                    <span class="stat-amount-large" style="font-size:1.2rem;display:block;">TSh <?= number_format($otc_revenue, 0) ?></span>
                </div>
                <p class="stat-sub-pharm">💰 Paid OTC revenue</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow-pharm"></i>
        </a>
        
        <a href="reports.php?branch=<?= $pharmacy['id'] ?>&type=pharmacy" class="stat-card-8-pharm card-green-pharm">
            <div class="stat-icon-pharm"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-content-pharm">
                <p class="stat-label-pharm">Total Revenue</p>
                <p class="stat-amount-large">TSh <?= number_format($total_revenue, 0) ?></p>
                <p class="stat-sub-pharm">Rx: TSh <?= number_format($prescription_revenue, 2, '.', ',') ?> · OTC: TSh <?= number_format($otc_revenue, 0) ?></p>
            </div>
            <i class="fas fa-arrow-right stat-arrow-pharm"></i>
        </a>
        
        <a href="<?= $inventory_url_out ?>" class="stat-card-8-pharm card-orange-pharm">
            <div class="stat-icon-pharm"><i class="fas fa-times-circle"></i></div>
            <div class="stat-content-pharm">
                <p class="stat-label-pharm">Out of Stock</p>
                <p class="stat-number-small"><?= number_format($pharmacy['out_of_stock_items'] ?? 0) ?></p>
                <p class="stat-sub-pharm">❌ Items with zero quantity</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow-pharm"></i>
        </a>
        
        <a href="<?= $inventory_url_low ?>" class="stat-card-8-pharm card-orange-pharm">
            <div class="stat-icon-pharm"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-content-pharm">
                <p class="stat-label-pharm">Low Stock</p>
                <p class="stat-number-small"><?= number_format($pharmacy['low_stock_items'] ?? 0) ?></p>
                <p class="stat-sub-pharm">⚠️ Below reorder level</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow-pharm"></i>
        </a>
        
        <a href="<?= $inventory_url_expired ?>" class="stat-card-8-pharm card-red-pharm">
            <div class="stat-icon-pharm"><i class="fas fa-skull"></i></div>
            <div class="stat-content-pharm">
                <p class="stat-label-pharm">Expired</p>
                <p class="stat-number-small"><?= number_format($pharmacy['expired_medicines'] ?? 0) ?></p>
                <p class="stat-sub-pharm">💀 Past expiry date</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow-pharm"></i>
        </a>
        
        <a href="<?= $inventory_url_expiring ?>" class="stat-card-8-pharm card-red-pharm">
            <div class="stat-icon-pharm"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-content-pharm">
                <p class="stat-label-pharm">Expiring Soon</p>
                <p class="stat-number-small"><?= number_format($pharmacy['expiring_soon_medicines'] ?? 0) ?></p>
                <p class="stat-sub-pharm">⏰ Next 30 days</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow-pharm"></i>
        </a>
    </div>

    <!-- ============================================================ -->
    <!-- ✅ RECENT PRESCRIPTIONS - GROUPED BY PATIENT -->
    <!-- ============================================================ -->
    <div class="card-pharm animate-fade-in-up-pharm" style="animation-delay:0.15s;">
        <div class="card-header-pharm">
            <h3 class="card-title-pharm">
                <i class="fas fa-prescription-bottle-medical"></i> Recent Prescriptions (Gross)
                <span style="background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:0.7rem;font-family:var(--font-mono);">
                    <?= count($recent_prescriptions) ?> Patients
                </span>
            </h3>
            <a href="prescriptions.php?branch=<?= $pharmacy['id'] ?>" class="card-action-pharm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="card-body-pharm" style="padding:0;background:var(--bg-body);">
            <?php if (count($recent_prescriptions) > 0): ?>
                <div class="patients-rx-container">
                    <?php $patient_num = 1; foreach ($recent_prescriptions as $patient): 
                        $initial = strtoupper(substr($patient['patient_name'] ?? 'P', 0, 1));
                        $visit_count = $patient['visit_count'] ?? 0;
                        $prescription_count = $patient['prescription_count'] ?? 0;
                        $grand_total = $patient['grand_total'] ?? 0;
                        $grand_medicines = $patient['grand_medicines'] ?? 0;
                        $patient_key = 'pat_' . $patient['patient_id'];
                    ?>
                        <div class="patient-rx-group" data-patient-key="<?= $patient_key ?>">
                            
                            <!-- PATIENT HEADER -->
                            <div class="patient-rx-header" onclick="togglePatientRx('<?= $patient_key ?>')">
                                <div class="patient-rx-left">
                                    <span class="patient-rx-num"><?= $patient_num++ ?></span>
                                    <div class="patient-rx-avatar" style="background: <?= '#' . substr(md5($patient['patient_name'] ?? 'X'), 0, 6) ?>;">
                                        <?= $initial ?>
                                    </div>
                                    <div class="patient-rx-info">
                                        <div class="patient-rx-name">
                                            <?= htmlspecialchars($patient['patient_name'] ?? 'N/A') ?>
                                            <span class="patient-rx-badge">
                                                <i class="fas fa-id-card"></i>
                                                <?= htmlspecialchars($patient['patient_code'] ?? 'N/A') ?>
                                            </span>
                                        </div>
                                        <div class="patient-rx-meta">
                                            <?php if (!empty($patient['patient_phone']) && $patient['patient_phone'] !== 'N/A'): ?>
                                                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['patient_phone']) ?></span>
                                            <?php endif; ?>
                                            <span><i class="fas fa-calendar-check"></i> <?= $visit_count ?> Visit(s)</span>
                                            <span><i class="fas fa-prescription"></i> <?= $prescription_count ?> Rx</span>
                                            <span><i class="fas fa-pills"></i> <?= $grand_medicines ?> Medicine(s)</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="patient-rx-right">
                                    <div class="patient-rx-total">
                                        <span class="patient-rx-total-label">TOTAL</span>
                                        <span class="patient-rx-total-value"><?= format_currency($grand_total) ?></span>
                                    </div>
                                    <i class="fas fa-chevron-down patient-rx-chevron" id="chevron-<?= $patient_key ?>"></i>
                                </div>
                            </div>
                            
                            <!-- PATIENT BODY (VISITS) -->
                            <div class="patient-rx-body" id="body-<?= $patient_key ?>">
                                <?php if (!empty($patient['visits'])): 
                                    $visit_num = 1;
                                    foreach ($patient['visits'] as $visit): 
                                        $visit_key = $patient_key . '_v' . $visit_num;
                                        $visit_total = $visit['total_amount'] ?? 0;
                                        $visit_discount = $visit['total_discount'] ?? 0;
                                        $visit_medicines = $visit['total_medicines'] ?? 0;
                                ?>
                                    <div class="visit-rx-block">
                                        
                                        <!-- VISIT HEADER -->
                                        <div class="visit-rx-header">
                                            <div class="visit-rx-left">
                                                <span class="visit-rx-icon">
                                                    <i class="fas fa-calendar-check"></i>
                                                </span>
                                                <span class="visit-rx-number">
                                                    <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
                                                </span>
                                                <span class="visit-rx-date">
                                                    <i class="fas fa-calendar-day"></i>
                                                    <?= !empty($visit['visit_date']) ? date('M d, Y', strtotime($visit['visit_date'])) : 'N/A' ?>
                                                </span>
                                                <span class="visit-rx-doctor">
                                                    <i class="fas fa-user-md"></i>
                                                    Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?>
                                                </span>
                                            </div>
                                            <div class="visit-rx-right">
                                                <span class="visit-rx-stat">
                                                    <i class="fas fa-pills"></i>
                                                    <?= $visit_medicines ?> Meds
                                                </span>
                                                <span class="visit-rx-stat visit-rx-stat-total">
                                                    <?= format_currency($visit_total) ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <!-- VISIT BODY (PRESCRIPTIONS) -->
                                        <div class="visit-rx-body">
                                            <table class="data-table-pharm visit-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width:40px;">#</th>
                                                        <th>Prescription #</th>
                                                        <th>💊 Medicines</th>
                                                        <th class="text-right">Amount</th>
                                                        <th>Status</th>
                                                        <th>Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php $rx_num = 1; foreach ($visit['prescriptions'] as $rx): 
                                                        $meds = $rx['medicines'] ?? [];
                                                        $total_meds = count($meds);
                                                        $show_first = 3;
                                                        $rx_key = $visit_key . '_rx' . $rx_num;
                                                    ?>
                                                        <tr>
                                                            <td class="text-center text-xs font-mono" style="color:var(--text-secondary);">
                                                                <?= $rx_num++ ?>
                                                            </td>
                                                            <td class="font-mono text-xs" style="color:var(--primary);font-weight:700;">
                                                                <?= htmlspecialchars($rx['prescription_number'] ?? 'N/A') ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($total_meds > 0): ?>
                                                                    <div class="medicines-cell">
                                                                        <?php foreach (array_slice($meds, 0, $show_first) as $med): ?>
                                                                            <div class="med-item">
                                                                                <i class="fas fa-pills"></i>
                                                                                <span><?= htmlspecialchars(trim($med)) ?></span>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                        
                                                                        <?php if ($total_meds > $show_first): ?>
                                                                            <div class="med-more" 
                                                                                 onclick="toggleMeds(this)" 
                                                                                 data-full-list="<?= htmlspecialchars(implode(', ', $meds)) ?>"
                                                                                 data-count="<?= $total_meds ?>">
                                                                                <i class="fas fa-plus-circle"></i>
                                                                                <span>+<?= $total_meds - $show_first ?> more</span>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <span class="text-xs text-gray-400">—</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-right font-semibold text-blue-600 font-mono">
                                                                <?= format_currency($rx['total_amount'] ?? 0) ?>
                                                                <?php if (($rx['discount_amount'] ?? 0) > 0): ?>
                                                                    <span class="text-xs text-red-600 font-mono" style="display:block;">
                                                                        (Disc: TSh <?= number_format($rx['discount_amount'], 0) ?>)
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="status-badge-pharm <?= getStatusBadge($rx['status'] ?? 'pending') ?>">
                                                                    <?= ucfirst($rx['status'] ?? 'Pending') ?>
                                                                </span>
                                                            </td>
                                                            <td class="text-xs font-mono" style="color:var(--text-secondary);">
                                                                <?= !empty($rx['created_at']) ? date('M d, Y H:i', strtotime($rx['created_at'])) : 'N/A' ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                    </div>
                                <?php $visit_num++; endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state-pharm" style="padding:20px;">
                                        <i class="fas fa-prescription"></i>
                                        <p>No visits found for this patient</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state-pharm">
                    <i class="fas fa-prescription"></i>
                    <p>No prescriptions found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RECENT INVENTORY -->
    <div class="card-pharm animate-fade-in-up-pharm" style="animation-delay:0.2s;">
        <div class="card-header-pharm">
            <h3 class="card-title-pharm">
                <i class="fas fa-boxes-stacked"></i> Recent Inventory Updates
            </h3>
            <a href="<?= $inventory_url ?>" class="card-action-pharm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="card-body-pharm">
            <?php if (count($recent_inventory) > 0): ?>
                <table class="data-table-pharm">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Price</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_inventory as $item): 
                            $is_out = ($item['quantity'] ?? 0) <= 0;
                            $is_low = ($item['quantity'] ?? 0) <= ($item['reorder_level'] ?? 0) && ($item['quantity'] ?? 0) > 0;
                            $is_expired = !empty($item['expiry_date']) && strtotime($item['expiry_date']) < time();
                            $is_expiring = !empty($item['expiry_date']) && strtotime($item['expiry_date']) > time() && strtotime($item['expiry_date']) < strtotime('+30 days');
                            if ($is_out || $is_expired) $status_class = 'danger';
                            elseif ($is_low || $is_expiring) $status_class = 'warning';
                            else $status_class = 'success';
                        ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
                                <td class="text-right font-semibold font-mono <?= $is_out ? 'text-red-600' : ($is_low ? 'text-yellow-600' : 'text-green-600') ?>">
                                    <?= number_format($item['quantity'] ?? 0) ?>
                                </td>
                                <td class="text-right font-mono">TSh <?= number_format($item['selling_price'] ?? 0, 0) ?></td>
                                <td class="font-mono <?= $is_expired ? 'text-red-600 font-semibold' : ($is_expiring ? 'text-yellow-600' : 'text-gray-500') ?>">
                                    <?= !empty($item['expiry_date']) ? date('M d, Y', strtotime($item['expiry_date'])) : 'N/A' ?>
                                </td>
                                <td>
                                    <span class="status-badge-pharm <?= $status_class ?>">
                                        <?php if ($is_out): ?><i class="fas fa-times-circle"></i> Out
                                        <?php elseif ($is_low): ?><i class="fas fa-exclamation-triangle"></i> Low
                                        <?php elseif ($is_expired): ?><i class="fas fa-skull"></i> Expired
                                        <?php elseif ($is_expiring): ?><i class="fas fa-clock"></i> Soon
                                        <?php else: ?><i class="fas fa-check-circle"></i> OK
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="edit_inventory.php?id=<?= $item['id'] ?>&branch=<?= $pharmacy['id'] ?>" class="btn-edit-small">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state-pharm">
                    <i class="fas fa-boxes"></i>
                    <p>No inventory items found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RECENT OTC SALES -->
    <div class="card-pharm animate-fade-in-up-pharm" style="animation-delay:0.25s;">
        <div class="card-header-pharm">
            <h3 class="card-title-pharm">
                <i class="fas fa-cash-register"></i> Recent OTC Sales
            </h3>
            <a href="otc_sales.php?branch=<?= $pharmacy['id'] ?>" class="card-action-pharm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="card-body-pharm">
            <?php if (count($recent_otc_sales) > 0): ?>
                <table class="data-table-pharm">
                    <thead>
                        <tr>
                            <th>Sale #</th>
                            <th>Customer</th>
                            <th>💊 Medicines</th>
                            <th class="text-right">Net Amount</th>
                            <th class="text-right">Discount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_otc_sales as $sale): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                <td>
                                    <?php if (!empty($sale['medicines_list'])): 
                                        $meds = explode(', ', $sale['medicines_list']);
                                        $total_meds = count($meds);
                                        $show_first = 2;
                                    ?>
                                        <div class="medicines-cell">
                                            <?php foreach (array_slice($meds, 0, $show_first) as $med): ?>
                                                <div class="med-item otc">
                                                    <i class="fas fa-capsules"></i>
                                                    <span><?= htmlspecialchars(trim($med)) ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                            
                                            <?php if ($total_meds > $show_first): ?>
                                                <div class="med-more" 
                                                     onclick="toggleMeds(this)" 
                                                     data-full-list="<?= htmlspecialchars($sale['medicines_list']) ?>"
                                                     data-count="<?= $total_meds ?>"
                                                     data-otc="1">
                                                    <i class="fas fa-plus-circle"></i>
                                                    <span>+<?= $total_meds - $show_first ?> more</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right font-semibold text-green-600 font-mono">
                                    TSh <?= number_format($sale['total_amount'] ?? 0, 0) ?>
                                </td>
                                <td class="text-right text-red-600 font-mono">
                                    <?php if (($sale['discount_amount'] ?? 0) > 0): ?>
                                        - TSh <?= number_format($sale['discount_amount'] ?? 0, 0) ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge-pharm <?= getStatusBadge($sale['payment_status'] ?? 'pending') ?>">
                                        <?= ucfirst($sale['payment_status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs font-mono"><?= date('M d, Y', strtotime($sale['created_at'] ?? 'now')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state-pharm">
                    <i class="fas fa-shopping-cart"></i>
                    <p>No OTC sales found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PHARMACISTS -->
    <div class="card-pharm animate-fade-in-up-pharm" style="animation-delay:0.3s;">
        <div class="card-header-pharm">
            <h3 class="card-title-pharm">
                <i class="fas fa-user-md"></i> Pharmacists 
                <span style="background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:0.7rem;font-family:var(--font-mono);">
                    <?= count($pharmacists) ?>
                </span>
            </h3>
            <a href="add_employee.php?branch=<?= $pharmacy['id'] ?>&role=pharmacy" class="btn-add-pharm">
                <i class="fas fa-plus"></i> Add Pharmacist
            </a>
        </div>
        <div class="card-body-pharm">
            <?php if (count($pharmacists) > 0): ?>
                <table class="data-table-pharm">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pharmacists as $pharmacist): ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($pharmacist['full_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($pharmacist['email'] ?? 'N/A') ?></td>
                                <td class="font-mono"><?= htmlspecialchars($pharmacist['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge-pharm <?= $pharmacist['status'] === 'active' ? 'success' : 'danger' ?>">
                                        <?= ucfirst($pharmacist['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="view_employee.php?id=<?= $pharmacist['id'] ?>&branch=<?= $pharmacy['id'] ?>" class="btn-edit-small">
                                        <i class="fas fa-user"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state-pharm">
                    <i class="fas fa-user-md"></i>
                    <p>No pharmacists assigned</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer-pharm">
        <p>
            <span class="footer-brand-pharm">Braick Dispensary</span> Management System V7
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Pharmacy Details - <?= htmlspecialchars($pharmacy['name']) ?>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span class="font-mono" id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- PAGE-SPECIFIC JAVASCRIPT -->
<script>
    // ================================================================
    // ✅ TOGGLE PATIENT RX GROUP
    // ================================================================
    function togglePatientRx(patientKey) {
        var body = document.getElementById('body-' + patientKey);
        var chevron = document.getElementById('chevron-' + patientKey);
        
        if (body) body.classList.toggle('open');
        if (chevron) chevron.classList.toggle('rotated');
    }

    // Fungua patient wa kwanza kwa default
    document.addEventListener('DOMContentLoaded', function() {
        var firstPatient = document.querySelector('.patient-rx-group');
        if (firstPatient) {
            var firstKey = firstPatient.getAttribute('data-patient-key');
            if (firstKey) {
                var firstBody = document.getElementById('body-' + firstKey);
                var firstChevron = document.getElementById('chevron-' + firstKey);
                if (firstBody) firstBody.classList.add('open');
                if (firstChevron) firstChevron.classList.add('rotated');
            }
        }
    });

    // ================================================================
    // ✅ TOGGLE MEDICINES
    // ================================================================
    function toggleMeds(element) {
        var fullList = element.getAttribute('data-full-list') || '';
        var totalCount = parseInt(element.getAttribute('data-count')) || 0;
        var isOtc = element.getAttribute('data-otc') === '1';
        var meds = fullList.split(', ').map(function(m) { return m.trim(); }).filter(function(m) { return m; });
        
        var parentCell = element.closest('.medicines-cell');
        if (!parentCell) return;
        
        var isExpanded = element.getAttribute('data-expanded') === 'true';
        var iconClass = isOtc ? 'fa-capsules' : 'fa-pills';
        var itemClass = isOtc ? 'med-item otc' : 'med-item';
        var otcAttr = isOtc ? ' data-otc="1"' : '';
        var showFirst = isOtc ? 2 : 3;
        
        if (isExpanded) {
            var html = '';
            var showLimit = Math.min(showFirst, meds.length);
            for (var i = 0; i < showLimit; i++) {
                html += '<div class="' + itemClass + '"><i class="fas ' + iconClass + '"></i><span>' + escapeHtml(meds[i]) + '</span></div>';
            }
            if (meds.length > showFirst) {
                html += '<div class="med-more" onclick="toggleMeds(this)" data-full-list="' + escapeHtml(fullList) + '" data-count="' + totalCount + '"' + otcAttr + '><i class="fas fa-plus-circle"></i><span>+' + (meds.length - showFirst) + ' more</span></div>';
            }
            parentCell.innerHTML = html;
        } else {
            var html = '';
            for (var i = 0; i < meds.length; i++) {
                html += '<div class="' + itemClass + '"><i class="fas ' + iconClass + '"></i><span>' + escapeHtml(meds[i]) + '</span></div>';
            }
            html += '<div class="med-more" onclick="toggleMeds(this)" data-full-list="' + escapeHtml(fullList) + '" data-count="' + totalCount + '" data-expanded="true"' + otcAttr + '><i class="fas fa-minus-circle"></i><span>Show less</span></div>';
            parentCell.innerHTML = html;
        }
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ================================================================
    // FOOTER TIME
    // ================================================================
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    console.log('%c💊 Braick - View Pharmacy V7 GROSS', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ V7: PRESCRIPTION = Medication_RAW (GROSS - bila discount, bila premium)', 'font-size:13px; color:#059669; font-weight:bold;');
    console.log('%c✅ V7: Prescription BILA round off (exact value, 2 decimal places)', 'font-size:13px; color:#FCD34D; font-weight:bold;');
    console.log('%c✅ SAWA KWA 100% NA AUDIT V13, ADMIN V20, CASHIERS V13.1, VIEW_CASHIER V14, PHARMACIES V3', 'font-size:13px; color:#FCD34D; font-weight:bold;');
    console.log('%c✅ Recent Prescriptions: Grouped by patient name', 'font-size:13px; color:#7C3AED;');
    console.log('%c🏥 Pharmacy: <?= htmlspecialchars($pharmacy['name']) ?> (ID: <?= $pharmacy['id'] ?>)', 'font-size:13px; color:#059669;');
    console.log('%c💊 Prescription (GROSS): TSh <?= number_format($prescription_revenue, 2, '.', ',') ?>', 'font-size:13px; color:#7C3AED; font-weight:bold;');
    console.log('%c🛒 OTC: TSh <?= number_format($otc_revenue, 0) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue, 0) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>