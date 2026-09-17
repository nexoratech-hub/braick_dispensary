<?php
// ================================================================
// FILE: frontend/pages/pharmacy/view_confirmed_prescriptions.php
// PHARMACY - VIEW CONFIRMED PRESCRIPTIONS (VIEW ONLY - ENGLISH)
// ✅ FIXED: Bill Info inaonyesha MEDICATION TOTAL pekee
// ✅ FIXED: Haijumuishi consultation, lab, n.k.
// ✅ ENGLISH ONLY
// ================================================================

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$currency = 'TSh';

try {
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $settings[$row['setting_key']] = $row['setting_value'];
    $currency = $settings['currency'] ?? 'TSh';
    
    // GET PATIENT
    $stmt = $db->prepare("SELECT * FROM patients WHERE id = ? AND branch_id = ?");
    $stmt->execute([$patient_id, $user_branch_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$patient) die("Patient not found");
    
    // GET VISIT
    $visit_info = null;
    if ($visit_id > 0) {
        $stmt = $db->prepare("SELECT * FROM visits WHERE id = ? AND patient_id = ?");
        $stmt->execute([$visit_id, $patient_id]);
        $visit_info = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // ✅ GET CONFIRMED PRESCRIPTIONS FOR THIS VISIT ONLY
    $prescriptions = [];
    if ($visit_id > 0) {
        $stmt = $db->prepare("
            SELECT p.*, u.full_name as doctor_name, v.visit_number, v.visit_date
            FROM prescriptions p
            LEFT JOIN users u ON p.doctor_id = u.id
            LEFT JOIN visits v ON p.visit_id = v.id
            WHERE p.patient_id = ? AND p.branch_id = ? AND p.visit_id = ?
              AND p.status = 'confirmed'
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$patient_id, $user_branch_id, $visit_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ✅ GET ITEMS FOR THIS VISIT ONLY
    $items = [];
    $total_quantity = 0;
    $total_amount = 0;
    $total_items = 0;
    
    if ($visit_id > 0) {
        $stmt = $db->prepare("
            SELECT pi.*, p.prescription_number, p.visit_id, p.status as prescription_status,
                   p.created_at as prescription_date, p.dispensed_at
            FROM prescription_items pi
            JOIN prescriptions p ON pi.prescription_id = p.id
            WHERE pi.patient_id = ? AND p.branch_id = ? AND p.visit_id = ?
              AND p.status = 'confirmed'
            ORDER BY pi.created_at DESC
        ");
        $stmt->execute([$patient_id, $user_branch_id, $visit_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $total_quantity += $item['quantity'];
            $total_amount += $item['total_price'];
            $total_items++;
        }
    }
    
    // ✅ GET MEDICATION-ONLY BILL INFO (NOT GRAND TOTAL)
    $bill = null;
    $other_charges = 0;
    $grand_total = 0;
    
    if ($visit_id > 0 && count($prescriptions) > 0) {
        $prescription_ids = array_column($prescriptions, 'id');
        
        if (!empty($prescription_ids)) {
            $placeholders = implode(',', array_fill(0, count($prescription_ids), '?'));
            
            $stmt = $db->prepare("
                SELECT 
                    b.id as bill_id,
                    b.bill_number,
                    b.status as bill_status,
                    b.paid_amount,
                    b.balance,
                    b.total_amount as bill_grand_total,
                    SUM(CASE WHEN bi.item_type = 'medication' THEN bi.total_price ELSE 0 END) as medication_total,
                    SUM(CASE WHEN bi.item_type = 'medication' THEN bi.discount_amount ELSE 0 END) as medication_discount,
                    SUM(CASE WHEN bi.item_type != 'medication' THEN bi.total_price ELSE 0 END) as other_total
                FROM bills b
                JOIN bill_items bi ON b.id = bi.bill_id
                WHERE bi.patient_id = ? 
                  AND bi.branch_id = ?
                  AND bi.reference_type = 'prescription'
                  AND bi.reference_id IN ($placeholders)
                  AND bi.status != 'cancelled'
                GROUP BY b.id
                ORDER BY b.created_at DESC
                LIMIT 1
            ");
            $bill_params = [$patient_id, $user_branch_id];
            foreach ($prescription_ids as $pid) $bill_params[] = $pid;
            $stmt->execute($bill_params);
            $bill_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($bill_data && $bill_data['bill_id']) {
                $med_total = (float)($bill_data['medication_total'] ?? 0);
                $med_discount = (float)($bill_data['medication_discount'] ?? 0);
                $other_total = (float)($bill_data['other_total'] ?? 0);
                
                $bill = [
                    'bill_id' => $bill_data['bill_id'],
                    'bill_number' => $bill_data['bill_number'],
                    'status' => $bill_data['bill_status'],
                    'paid_amount' => (float)($bill_data['paid_amount'] ?? 0),
                    'balance' => (float)($bill_data['balance'] ?? 0),
                    'medication_total' => $med_total - $med_discount,
                    'medication_subtotal' => $med_total,
                    'medication_discount' => $med_discount,
                    'other_total' => $other_total,
                    'grand_total' => (float)($bill_data['bill_grand_total'] ?? ($med_total + $other_total - $med_discount))
                ];
            }
        }
    }
    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

function formatMoney($amount) { return number_format($amount, 0, '.', ','); }
function calculateAge($dob) { if (empty($dob)) return 'N/A'; return (new DateTime($dob))->diff(new DateTime('today'))->y; }
function formatDate($datetime) { if (empty($datetime)) return 'N/A'; return date('d/m/Y h:i A', strtotime($datetime)); }

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/pharmacy_header.php';
include_once '../../components/pharmacy_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmed Prescriptions - Braick Dispensary</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
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
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --info: #3B82F6;
            --info-bg: #DBEAFE;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 10px;
            --radius-lg: 16px;
            --shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary-bg: #1E3A5F;
            --success-bg: #1A3A2A;
            --warning-bg: #3A2A1A;
            --info-bg: #1E3A5F;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: var(--font-primary);
            background: var(--bg-body);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
        }
        
        .mono, .money, .date-mono, .summary-number { font-family: var(--font-mono) !important; font-feature-settings: 'tnum'; }
        
        .main-content { margin-left: 270px; margin-top: 68px; padding: 24px 28px; min-height: calc(100vh - 68px); }
        
        .page-header {
            background: linear-gradient(135deg, #3B82F6, #0B5ED7, #0A4CA8);
            border-radius: var(--radius-lg);
            padding: 20px 28px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.3);
        }
        
        .page-header .page-title { color: white; font-size: 1.3rem; font-weight: 700; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .page-header .page-title i { font-size: 1.4rem; }
        .page-header .page-subtitle { color: rgba(255,255,255,0.85); font-size: 0.8rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }
        
        .page-header .btn-outline-light:hover { background: rgba(255,255,255,0.2); transform: translateY(-2px); }
        
        .visit-badge {
            background: linear-gradient(135deg, #FCD34D, #F59E0B);
            color: #78350F;
            padding: 3px 12px;
            border-radius: 8px;
            font-size: 0.65rem;
            font-weight: 800;
            font-family: var(--font-mono);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .visit-date-badge {
            background: rgba(255,255,255,0.25);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            font-family: var(--font-mono);
            border: 1px solid rgba(255,255,255,0.3);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .readonly-banner {
            background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
            border: 2px solid var(--primary);
            border-radius: var(--radius-lg);
            padding: 14px 22px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.85rem;
            color: var(--primary);
            font-weight: 600;
        }
        
        [data-theme="dark"] .readonly-banner { background: linear-gradient(135deg, #1E3A5F, #16294A); color: #93C5FD; }
        .readonly-banner i { font-size: 1.3rem; }
        
        .patient-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 20px;
            box-shadow: var(--shadow);
        }
        
        .patient-avatar {
            width: 64px; height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
            font-family: var(--font-mono);
        }
        
        .patient-info h2 { font-size: 1.2rem; font-weight: 700; }
        .patient-info .patient-details { display: flex; flex-wrap: wrap; gap: 12px; font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px; }
        .patient-info .patient-details span { display: inline-flex; align-items: center; gap: 4px; }
        
        .summary-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .summary-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            text-align: center;
            box-shadow: var(--shadow);
        }
        
        .summary-card .summary-number { font-size: 1.8rem; font-weight: 800; display: block; }
        .summary-card .summary-label { font-size: 0.65rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; }
        .summary-card.total .summary-number { color: var(--primary); }
        .summary-card.items .summary-number { color: #7C3AED; }
        .summary-card.qty .summary-number { color: var(--warning); }
        .summary-card.amount .summary-number { color: var(--success); }
        
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .item-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            padding: 18px 20px;
            box-shadow: var(--shadow);
            position: relative;
        }
        
        .item-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3B82F6, #6EA8FE);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        
        .item-card .item-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; flex-wrap: wrap; gap: 4px; }
        .item-card .item-name { font-size: 1rem; font-weight: 700; color: var(--primary); }
        .item-card .item-prescription { font-size: 0.6rem; color: var(--text-secondary); background: var(--primary-bg); padding: 2px 8px; border-radius: 12px; font-family: var(--font-mono); }
        
        .item-card .item-badge {
            position: absolute;
            top: 14px; right: 14px;
            font-size: 0.55rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 12px;
            background: #DBEAFE;
            color: #1E40AF;
            font-family: var(--font-mono);
        }
        
        .item-card .item-details { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 12px; margin: 8px 0; }
        .item-card .item-detail { display: flex; flex-direction: column; }
        
        .item-card .item-detail .label { font-size: 0.55rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 2px; }
        
        .item-card .item-detail .value-display {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            padding: 6px 10px;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            min-height: 32px;
            display: flex;
            align-items: center;
        }
        
        .item-card .item-detail .value-display.highlight { color: var(--primary); border-color: var(--primary-light); font-family: var(--font-mono); font-weight: 800; }
        
        .item-card .item-price { margin-top: 8px; text-align: right; font-weight: 700; font-size: 0.95rem; color: var(--success); font-family: var(--font-mono); }
        .item-card .item-price .label { font-weight: 400; color: var(--text-secondary); font-size: 0.7rem; font-family: var(--font-primary); }
        
        /* ✅ MEDICATION BILL INFO CARD (FIXED) */
        .bill-info-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--primary);
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }
        
        .bill-info-card .bill-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px dashed var(--border-color);
            flex-wrap: wrap;
        }
        
        .bill-info-card .bill-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
        }
        
        .bill-info-card .bill-item {
            text-align: center;
            padding: 12px;
            background: var(--bg-body);
            border-radius: var(--radius);
        }
        
        .bill-info-card .bill-label { font-size: 0.6rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; display: block; margin-bottom: 4px; }
        
        .bill-info-card .bill-value { font-size: 1.1rem; font-weight: 800; color: var(--text-primary); font-family: var(--font-mono); }
        .bill-info-card .bill-value.primary { color: var(--primary); }
        .bill-info-card .bill-value.success { color: var(--success); }
        .bill-info-card .bill-value.warning { color: var(--warning); }
        .bill-info-card .bill-value.danger { color: #DC2626; }
        
        .badge-status { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 0.65rem; font-weight: 700; }
        .badge-info { background: var(--info-bg); color: var(--info); border: 1px solid var(--info); }
        [data-theme="dark"] .badge-info { background: #1E3A5F; color: #93C5FD; border-color: #3B82F6; }
        
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 20px; border-radius: var(--radius); font-weight: 600; font-size: 0.8rem; cursor: pointer; border: none; text-decoration: none; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); }
        .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
        .btn-outline:hover { background: var(--bg-body); border-color: var(--primary); color: var(--primary); }
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--border-color);
        }
        
        .footer { padding: 10px 0; border-top: 1px solid var(--border-color); margin-top: 20px; text-align: center; font-size: 0.65rem; color: var(--text-secondary); }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        .pdf-modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999; backdrop-filter: blur(4px); }
        .pdf-modal-overlay.active { display: flex; align-items: center; justify-content: center; padding: 20px; }
        
        .pdf-modal { background: white; border-radius: var(--radius-lg); max-width: 900px; width: 100%; max-height: 90vh; display: flex; flex-direction: column; }
        
        .pdf-modal-header {
            padding: 16px 20px;
            background: #0B5ED7;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .pdf-modal-header .modal-title { color: white; font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; }
        .pdf-modal-header .modal-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .pdf-modal-header .modal-actions .btn { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.2); padding: 6px 14px; font-size: 0.75rem; }
        
        .pdf-modal-body { padding: 20px; overflow-y: auto; flex: 1; background: #f8f9fa; border-radius: 0 0 var(--radius-lg) var(--radius-lg); }
        .pdf-content { background: white; padding: 20px; border-radius: var(--radius); }
        
        .empty-state { text-align: center; padding: 80px 20px; background: var(--bg-card); border-radius: var(--radius-lg); border: 2px dashed var(--border-color); }
        .empty-state i { font-size: 4rem; color: var(--info); display: block; margin-bottom: 16px; opacity: 0.6; }
        .empty-state p { font-size: 1.1rem; font-weight: 600; color: var(--text-primary); }
        .empty-state .sub { font-size: 0.85rem; color: var(--text-secondary); margin-top: 6px; font-weight: 400; }
        
        @media (max-width: 1024px) { .main-content { margin-left: 0; padding: 14px; } .items-grid { grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); } }
        
        @media (max-width: 768px) {
            .page-header { padding: 14px 16px; }
            .items-grid { grid-template-columns: 1fr; }
            .summary-section { grid-template-columns: 1fr 1fr; }
            .patient-card { flex-direction: column; text-align: center; }
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
                <span style="background:rgba(255,255,255,0.2);color:white;padding:2px 10px;border-radius:20px;font-size:0.55rem;font-weight:600;text-transform:uppercase;">VIEW ONLY</span>
                <?php if ($visit_info): ?>
                    <span class="visit-badge"><i class="fas fa-calendar-check"></i> <?= htmlspecialchars($visit_info['visit_number'] ?? 'N/A') ?></span>
                    <span class="visit-date-badge"><i class="fas fa-calendar-day"></i> <?= $visit_info['visit_date'] ? date('d M Y', strtotime($visit_info['visit_date'])) : date('d M Y') ?></span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-lock"></i>
                Confirmed medications for this visit
                <span style="background:rgba(255,255,255,0.12);color:white;padding:2px 10px;border-radius:20px;font-size:0.55rem;">
                    <?= htmlspecialchars($patient['full_name']) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <a href="pending_prescriptions.php?status=confirmed" class="btn-outline-light"><i class="fas fa-arrow-left"></i> Back</a>
            <button onclick="generatePDF()" class="btn-outline-light"><i class="fas fa-file-pdf"></i> View PDF</button>
        </div>
    </div>

    <?php if ($patient && count($items) > 0): ?>
    
    <div class="readonly-banner">
        <i class="fas fa-lock"></i>
        <div>
            <strong>View Only Mode</strong> — These prescriptions have already been confirmed.
            <span style="opacity:0.8;">You cannot edit the medications, but you can print or download the PDF.</span>
        </div>
    </div>
    
    <div class="patient-card">
        <div class="patient-avatar" style="background: <?= '#' . substr(md5($patient['full_name']), 0, 6) ?>;">
            <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
        </div>
        <div class="patient-info">
            <h2><?= htmlspecialchars($patient['full_name']) ?></h2>
            <div class="patient-details">
                <span><i class="fas fa-id-card"></i> ID: <span class="mono"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span></span>
                <span><i class="fas fa-venus-mars"></i> <?= ucfirst($patient['gender'] ?? 'N/A') ?></span>
                <span><i class="fas fa-calendar-alt"></i> <span class="mono"><?= calculateAge($patient['date_of_birth'] ?? '') ?></span> yrs</span>
                <span><i class="fas fa-phone"></i> <span class="mono"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span></span>
            </div>
        </div>
        <div style="margin-left:auto;">
            <span class="badge-status badge-info">✅ Confirmed</span>
        </div>
    </div>

    <div class="summary-section">
        <div class="summary-card total">
            <span class="summary-number"><?= count($prescriptions) ?></span>
            <span class="summary-label">📋 Prescriptions</span>
        </div>
        <div class="summary-card items">
            <span class="summary-number"><?= $total_items ?></span>
            <span class="summary-label">📦 Items</span>
        </div>
        <div class="summary-card qty">
            <span class="summary-number"><?= $total_quantity ?></span>
            <span class="summary-label">📊 Total Quantity</span>
        </div>
        <div class="summary-card amount">
            <span class="summary-number"><?= $currency ?> <?= formatMoney($total_amount) ?></span>
            <span class="summary-label">💰 Medication Total</span>
        </div>
    </div>

    <div class="items-grid" id="itemsGrid">
        <?php foreach ($items as $index => $item): ?>
            <div class="item-card" data-item-id="<?= $item['id'] ?>">
                <div class="item-badge">#<?= $index + 1 ?> • CONFIRMED</div>
                
                <div class="item-header">
                    <span class="item-name"><?= htmlspecialchars($item['medication_name']) ?></span>
                    <span class="item-prescription"><?= htmlspecialchars($item['prescription_number'] ?? 'N/A') ?></span>
                </div>
                
                <div class="item-details">
                    <div class="item-detail">
                        <span class="label">💊 Dosage</span>
                        <div class="value-display"><?= htmlspecialchars($item['dosage'] ?: '—') ?></div>
                    </div>
                    <div class="item-detail">
                        <span class="label">🕐 Frequency</span>
                        <div class="value-display"><?= htmlspecialchars($item['frequency'] ?: '—') ?></div>
                    </div>
                    <div class="item-detail">
                        <span class="label">📦 Quantity</span>
                        <div class="value-display highlight"><?= $item['quantity'] ?? 0 ?></div>
                    </div>
                    <div class="item-detail">
                        <span class="label">📅 Duration</span>
                        <div class="value-display"><?= htmlspecialchars($item['duration'] ?: '—') ?></div>
                    </div>
                    <div class="item-detail" style="grid-column: span 2;">
                        <span class="label">📏 Route</span>
                        <div class="value-display"><?= htmlspecialchars($item['route'] ?: '—') ?></div>
                    </div>
                </div>
                
                <?php if (!empty($item['instructions'])): ?>
                    <div class="item-detail" style="grid-column: span 2; margin-top: 8px;">
                        <span class="label">📝 Instructions</span>
                        <div class="value-display" style="font-size: 0.78rem; min-height: 36px;"><?= htmlspecialchars($item['instructions']) ?></div>
                    </div>
                <?php endif; ?>
                
                <div class="item-price">
                    <span class="label">Total Price: </span>
                    <?= $currency ?> <?= formatMoney($item['total_price'] ?? 0) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($bill && $bill['medication_total'] > 0): ?>
    <!-- ✅ MEDICATION BILL INFO - FIXED -->
    <div class="bill-info-card">
        <div class="bill-title">
            <i class="fas fa-pills"></i>
            Medication Bill Info — #<?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
            <span style="font-size:0.65rem;font-weight:400;color:var(--text-secondary);margin-left:auto;">
                <i class="fas fa-info-circle"></i> Prescription items only
            </span>
        </div>
        <div class="bill-grid">
            <div class="bill-item">
                <span class="bill-label">💊 Medication Total</span>
                <span class="bill-value primary"><?= $currency ?> <?= formatMoney($bill['medication_total']) ?></span>
            </div>
            <div class="bill-item">
                <span class="bill-label">Discount</span>
                <span class="bill-value warning">-<?= $currency ?> <?= formatMoney($bill['medication_discount']) ?></span>
            </div>
            <div class="bill-item">
                <span class="bill-label">Paid</span>
                <span class="bill-value success"><?= $currency ?> <?= formatMoney($bill['paid_amount']) ?></span>
            </div>
            <div class="bill-item">
                <span class="bill-label">Balance</span>
                <span class="bill-value <?= $bill['balance'] > 0 ? 'danger' : 'success' ?>">
                    <?= $currency ?> <?= formatMoney($bill['balance']) ?>
                </span>
            </div>
        </div>
        
        <?php if ($bill['other_total'] > 0): ?>
        <div style="margin-top:14px;padding:10px 14px;background:var(--warning-bg);border-left:3px solid var(--warning);border-radius:8px;font-size:0.75rem;color:var(--warning);">
            <i class="fas fa-info-circle"></i>
            <strong>Note:</strong> This bill also includes other charges 
            (<strong><?= $currency ?> <?= formatMoney($bill['other_total']) ?></strong>) 
            such as consultation, lab tests, procedures, etc.<br>
            <strong>Grand Total:</strong> <?= $currency ?> <?= formatMoney($bill['grand_total']) ?> 
            <span style="opacity:0.8;">(Medication + Other charges - Discount)</span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="action-buttons">
        <a href="pending_prescriptions.php?status=confirmed" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Prescriptions
        </a>
        <button onclick="generatePDF()" class="btn btn-primary">
            <i class="fas fa-file-pdf"></i> Download PDF
        </button>
        <button onclick="window.print()" class="btn btn-outline">
            <i class="fas fa-print"></i> Print
        </button>
    </div>

    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <p>No confirmed prescriptions found for this visit</p>
            <p class="sub">Visit: <strong><?= htmlspecialchars($visit_info['visit_number'] ?? 'N/A') ?></strong></p>
            <a href="pending_prescriptions.php?status=confirmed" class="btn btn-primary" style="margin-top:16px;">
                <i class="fas fa-arrow-left"></i> Back to Prescriptions
            </a>
        </div>
    <?php endif; ?>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Confirmed Prescriptions (View Only)
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
        </p>
    </footer>

</main>

<div class="pdf-modal-overlay" id="pdfModal">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <div class="modal-title">
                <i class="fas fa-file-pdf"></i>
                Confirmed Prescriptions PDF - <?= htmlspecialchars($patient['full_name'] ?? 'Patient') ?>
            </div>
            <div class="modal-actions">
                <button onclick="downloadPDF()" class="btn"><i class="fas fa-download"></i> Download</button>
                <button onclick="window.print()" class="btn"><i class="fas fa-print"></i> Print</button>
                <button onclick="closePDFModal()" class="btn"><i class="fas fa-times"></i> Close</button>
            </div>
        </div>
        <div class="pdf-modal-body">
            <div class="pdf-content" id="pdfContent"></div>
        </div>
    </div>
</div>

<script>
    var htmlElement = document.documentElement;
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') htmlElement.setAttribute('data-theme', 'dark');
    else if (savedDarkMode === 'false') htmlElement.removeAttribute('data-theme');
    else {
        var cookieDark = document.cookie.match(/dark_mode=([^;]+)/);
        if (cookieDark && cookieDark[1] === 'true') htmlElement.setAttribute('data-theme', 'dark');
    }
    
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle && sidebar) sidebarToggle.addEventListener('click', function() { sidebar.classList.toggle('open'); });
    
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) footerTimestamp.textContent = 'Last updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    function formatMoney(amount) { return Number(amount).toLocaleString('en-US'); }
    
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');
        
        var itemsHtml = '';
        var totalQty = 0, totalAmount = 0, totalItems = 0;
        
        document.querySelectorAll('.item-card').forEach(function(card) {
            var medName = card.querySelector('.item-name')?.textContent || 'N/A';
            var prescription = card.querySelector('.item-prescription')?.textContent || 'N/A';
            var dosage = card.querySelectorAll('.value-display')[0]?.textContent?.trim() || 'N/A';
            var frequency = card.querySelectorAll('.value-display')[1]?.textContent?.trim() || 'N/A';
            var quantity = parseInt(card.querySelectorAll('.value-display')[2]?.textContent) || 0;
            var duration = card.querySelectorAll('.value-display')[3]?.textContent?.trim() || 'N/A';
            var route = card.querySelectorAll('.value-display')[4]?.textContent?.trim() || 'N/A';
            var price = parseInt(card.querySelector('.item-price')?.textContent?.replace(/[^0-9]/g, '')) || 0;
            
            totalQty += quantity;
            totalAmount += price;
            totalItems++;
            
            itemsHtml += `
                <div style="border:1px solid #E2E8F0;border-radius:8px;padding:12px 16px;margin-bottom:10px;page-break-inside:avoid;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <strong style="color:#0B5ED7;font-size:16px;">${medName}</strong>
                        <span style="font-size:12px;color:#64748B;font-family:monospace;">${prescription}</span>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 16px;font-size:14px;">
                        <div><span style="font-weight:600;color:#64748B;">Dosage:</span> ${dosage}</div>
                        <div><span style="font-weight:600;color:#64748B;">Frequency:</span> ${frequency}</div>
                        <div><span style="font-weight:600;color:#64748B;">Quantity:</span> ${quantity}</div>
                        <div><span style="font-weight:600;color:#64748B;">Duration:</span> ${duration}</div>
                        <div style="grid-column:span 2;"><span style="font-weight:600;color:#64748B;">Route:</span> ${route}</div>
                        <div style="grid-column:span 2;text-align:right;font-weight:700;color:#059669;font-size:15px;font-family:monospace;margin-top:6px;">
                            Total: <?= $currency ?> ${formatMoney(price)}
                        </div>
                    </div>
                </div>
            `;
        });
        
        var visitNumber = '<?= addslashes($visit_info['visit_number'] ?? 'N/A') ?>';
        var visitDate = '<?= $visit_info ? date('d M Y', strtotime($visit_info['visit_date'] ?? $visit_info['created_at'] ?? 'now')) : date('d M Y') ?>';
        
        content.innerHTML = `
            <div style="font-family:'Inter',sans-serif;padding:20px;">
                <div style="text-align:center;padding-bottom:16px;border-bottom:3px solid #3B82F6;margin-bottom:20px;">
                    <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" style="height:55px;margin-bottom:6px;" onerror="this.style.display='none'">
                    <div style="font-size:1.6rem;font-weight:800;color:#0B5ED7;">BRAICK DISPENSARY</div>
                    <div style="font-size:0.8rem;color:#64748B;">Committed to Your Health</div>
                    <div style="font-size:0.9rem;font-weight:600;color:#3B82F6;margin-top:6px;">✅ Confirmed Prescriptions</div>
                    <div style="font-size:0.7rem;color:#64748B;">Generated: ${new Date().toLocaleString()}</div>
                </div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;margin-bottom:16px;padding:12px 16px;background:#F8FAFC;border-radius:8px;">
                    <div><span style="font-weight:600;color:#64748B;">Patient:</span> <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></div>
                    <div><span style="font-weight:600;color:#64748B;">ID:</span> <span style="font-family:monospace;"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span></div>
                    <div><span style="font-weight:600;color:#64748B;">Gender:</span> <?= ucfirst($patient['gender'] ?? 'N/A') ?></div>
                    <div><span style="font-weight:600;color:#64748B;">Age:</span> <?= calculateAge($patient['date_of_birth'] ?? '') ?> yrs</div>
                    <div><span style="font-weight:600;color:#64748B;">Visit #:</span> <span style="font-family:monospace;font-weight:700;color:#3B82F6;">${visitNumber}</span></div>
                    <div><span style="font-weight:600;color:#64748B;">Visit Date:</span> <span style="font-family:monospace;">${visitDate}</span></div>
                </div>
                
                <div style="margin-bottom:16px;">
                    <div style="font-size:1rem;font-weight:700;color:#0B5ED7;border-bottom:2px solid #6EA8FE;padding-bottom:4px;margin-bottom:10px;">📋 Confirmed Items (${totalItems})</div>
                    ${itemsHtml}
                </div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;margin:16px 0;padding:12px 16px;background:#DBEAFE;border-radius:8px;">
                    <div style="text-align:center;">
                        <div style="font-size:0.6rem;font-weight:600;color:#64748B;text-transform:uppercase;">Total Items</div>
                        <div style="font-size:1.2rem;font-weight:700;color:#0B5ED7;font-family:monospace;">${totalItems}</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:0.6rem;font-weight:600;color:#64748B;text-transform:uppercase;">Total Quantity</div>
                        <div style="font-size:1.2rem;font-weight:700;color:#D97706;font-family:monospace;">${totalQty}</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:0.6rem;font-weight:600;color:#64748B;text-transform:uppercase;">Medication Total</div>
                        <div style="font-size:1.2rem;font-weight:700;color:#0B5ED7;font-family:monospace;"><?= $currency ?> ${formatMoney(totalAmount)}</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:0.6rem;font-weight:600;color:#64748B;text-transform:uppercase;">Status</div>
                        <div style="font-size:1.2rem;font-weight:700;color:#3B82F6;">✅ CONFIRMED</div>
                    </div>
                </div>
                
                <div style="margin-top:20px;padding-top:12px;border-top:2px solid #E2E8F0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                    <div style="font-size:14px;color:#64748B;">
                        <span>Prepared by: <?= htmlspecialchars($user_full_name) ?></span>
                        <span style="margin-left:16px;">Date: <?= date('F d, Y') ?></span>
                    </div>
                    <div style="text-align:center;padding:8px 20px;border:3px solid #3B82F6;border-radius:10px;background:#DBEAFE;min-width:160px;">
                        <div style="font-size:10px;color:#64748B;text-transform:uppercase;font-weight:700;">Official Stamp</div>
                        <div style="font-size:14px;font-weight:800;color:#0B5ED7;">BRAICK DISPENSARY</div>
                        <div style="font-size:12px;color:#64748B;">Approved By: _________________</div>
                    </div>
                </div>
                <div style="text-align:center;margin-top:8px;font-size:12px;color:#94A3B8;">Braick Dispensary • Generated on <?= date('F d, Y h:i:s A') ?> • All rights reserved</div>
            </div>
        `;
        
        modal.classList.add('active');
    }
    
    function closePDFModal() { document.getElementById('pdfModal').classList.remove('active'); }
    
    function downloadPDF() {
        var element = document.getElementById('pdfContent');
        var opt = {
            margin: [10, 10, 10, 10],
            filename: 'Confirmed_Prescriptions_Visit_<?= $visit_id ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff' },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
        };
        html2pdf().set(opt).from(element).save();
    }
    
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closePDFModal(); });
    document.getElementById('pdfModal').addEventListener('click', function(e) { if (e.target === this) closePDFModal(); });
    
    console.log('%c✅ Braick - Confirmed Prescriptions (Medication Only)', 'font-size:18px; font-weight:bold; color:#3B82F6;');
    console.log('%c✅ Bill Info inaonyesha MEDICATION total pekee', 'font-size:13px; color:#34D399; font-weight:bold;');
</script>

</body>
</html>