<?php
// ================================================================
// FILE: frontend/pages/admin/prescription_details.php
// PRESCRIPTION DETAILS - VIEW ALL PRESCRIPTIONS FOR A PATIENT
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

// ================================================================
// ROLE CHECK - ONLY ADMIN CAN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../../auth/login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET PARAMETERS
// ================================================================
$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

if ($prescription_id <= 0) {
    header('Location: prescriptions.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_branch_name = 'All Branches';
if ($selected_branch_id !== 'all') {
    foreach ($branches_list as $b) {
        if ($b['id'] == $selected_branch_id) {
            $selected_branch_name = $b['name'];
            break;
        }
    }
}

// ================================================================
// GET CURRENT PRESCRIPTION DETAILS
// ================================================================
$sql = "
    SELECT 
        p.*,
        pat.id as patient_id,
        pat.full_name as patient_name,
        pat.patient_id as patient_number,
        pat.phone as patient_phone,
        pat.gender as patient_gender,
        pat.date_of_birth,
        pat.address as patient_address,
        u.full_name as doctor_name,
        u.specialty as doctor_specialty,
        b.name as branch_name,
        COALESCE((
            SELECT SUM(pi.total_price) 
            FROM prescription_items pi 
            WHERE pi.prescription_id = p.id
        ), 0) as prescription_amount,
        COALESCE((
            SELECT COUNT(*) 
            FROM prescription_items pi 
            WHERE pi.prescription_id = p.id
        ), 0) as item_count
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.doctor_id = u.id
    LEFT JOIN branches b ON p.branch_id = b.id
    WHERE p.id = ?
";

$stmt = $db->prepare($sql);
$stmt->execute([$prescription_id]);
$current_prescription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_prescription) {
    header('Location: prescriptions.php?branch=' . urlencode($selected_branch_id) . '&error=not_found');
    exit;
}

$patient_id = $current_prescription['patient_id'];

// ================================================================
// GET ALL PRESCRIPTIONS FOR THIS PATIENT WITH THEIR ITEMS
// ================================================================
$all_prescriptions_sql = "
    SELECT 
        p.id,
        p.prescription_number,
        p.status,
        p.created_at,
        p.diagnosis,
        p.notes,
        u.full_name as doctor_name,
        COALESCE((
            SELECT SUM(pi.total_price) 
            FROM prescription_items pi 
            WHERE pi.prescription_id = p.id
        ), 0) as total_amount,
        COALESCE((
            SELECT COUNT(*) 
            FROM prescription_items pi 
            WHERE pi.prescription_id = p.id
        ), 0) as item_count
    FROM prescriptions p
    LEFT JOIN users u ON p.doctor_id = u.id
    WHERE p.patient_id = ?
    ORDER BY p.created_at DESC
";

$stmt = $db->prepare($all_prescriptions_sql);
$stmt->execute([$patient_id]);
$all_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ITEMS FOR EACH PRESCRIPTION
// ================================================================
foreach ($all_prescriptions as &$prescription) {
    $items_sql = "
        SELECT 
            pi.*,
            mi.medication_name as inventory_medication_name,
            mi.category as inventory_category,
            mi.unit as inventory_unit,
            mi.batch_number as inventory_batch_number,
            mi.expiry_date as inventory_expiry_date,
            mi.quantity as inventory_stock
        FROM prescription_items pi
        LEFT JOIN medications_inventory mi ON pi.inventory_id = mi.id
        WHERE pi.prescription_id = ?
        ORDER BY pi.created_at DESC
    ";
    $stmt = $db->prepare($items_sql);
    $stmt->execute([$prescription['id']]);
    $prescription['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($prescription);

// ================================================================
// HANDLE ACTIONS - Cancel, Edit, Delete (MOVED TO TOP FOR EARLY EXECUTION)
// ================================================================

// ✅ HANDLE DELETE ITEM - FIRST PRIORITY
if (isset($_GET['delete_item']) && is_numeric($_GET['delete_item'])) {
    $delete_item_id = (int)$_GET['delete_item'];
    $delete_prescription_id = isset($_GET['pid']) ? (int)$_GET['pid'] : $prescription_id;
    $delete_branch = isset($_GET['branch']) ? $_GET['branch'] : $selected_branch_id;
    
    try {
        $db->beginTransaction();
        
        // Get item details to return stock
        $stmt = $db->prepare("SELECT inventory_id, quantity FROM prescription_items WHERE id = ?");
        $stmt->execute([$delete_item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            // Return stock to inventory if linked
            if ($item['inventory_id']) {
                $stmt = $db->prepare("UPDATE medications_inventory SET quantity = quantity + ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['inventory_id']]);
            }
            
            // Delete the item
            $stmt = $db->prepare("DELETE FROM prescription_items WHERE id = ?");
            $stmt->execute([$delete_item_id]);
        }
        
        $db->commit();
        
        // ✅ REDIRECT IMMEDIATELY - NO OUTPUT BEFORE HEADER
        header('Location: prescription_details.php?id=' . $delete_prescription_id . '&branch=' . urlencode($delete_branch) . '&item_deleted=1');
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        header('Location: prescription_details.php?id=' . $prescription_id . '&branch=' . urlencode($selected_branch_id) . '&error=delete_failed');
        exit;
    }
}

// ✅ HANDLE CANCEL ITEM - SECOND PRIORITY
if (isset($_GET['cancel_item']) && is_numeric($_GET['cancel_item'])) {
    $cancel_item_id = (int)$_GET['cancel_item'];
    $cancel_prescription_id = isset($_GET['pid']) ? (int)$_GET['pid'] : $prescription_id;
    $cancel_branch = isset($_GET['branch']) ? $_GET['branch'] : $selected_branch_id;
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("SELECT inventory_id, quantity FROM prescription_items WHERE id = ?");
        $stmt->execute([$cancel_item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            if ($item['inventory_id']) {
                $stmt = $db->prepare("UPDATE medications_inventory SET quantity = quantity + ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['inventory_id']]);
            }
            
            $stmt = $db->prepare("DELETE FROM prescription_items WHERE id = ?");
            $stmt->execute([$cancel_item_id]);
        }
        
        $db->commit();
        header('Location: prescription_details.php?id=' . $cancel_prescription_id . '&branch=' . urlencode($cancel_branch) . '&item_cancelled=1');
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        header('Location: prescription_details.php?id=' . $prescription_id . '&branch=' . urlencode($selected_branch_id) . '&error=cancel_failed');
        exit;
    }
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE HEADERS
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- HTML CONTENT REMAINS THE SAME -->
<!-- ================================================================ -->
<!-- START HTML -->
<!-- ================================================================ -->
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Prescriptions - Braick Dispensary</title>
    <link rel="icon" href="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ================================================================ */
        /* ALL CSS STYLES - SAME AS BEFORE */
        /* ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8) !important;
            border-radius: 16px !important;
            padding: 20px 28px !important;
            margin-bottom: 20px !important;
            display: flex !important;
            flex-wrap: wrap !important;
            justify-content: space-between !important;
            align-items: center !important;
            gap: 12px !important;
            box-shadow: 0 4px 24px rgba(11, 94, 215, 0.3) !important;
        }
        .page-header .page-title { color: white !important; font-size: 1.4rem !important; font-weight: 700 !important; }
        .page-header .page-title i { color: white !important; }
        .page-header .page-subtitle { color: rgba(255,255,255,0.9) !important; font-size: 0.85rem !important; }
        .page-header .branch-tag { background: rgba(255,255,255,0.2) !important; color: white !important; padding: 3px 14px !important; border-radius: 20px !important; font-size: 0.7rem !important; font-weight: 600 !important; }
        
        .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 16px;
            border-radius: 10px;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .detail-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        .detail-card:hover {
            border-color: #0B5ED7;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.05);
        }
        
        .detail-card .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        .detail-card .card-title i { color: #0B5ED7; }
        
        .scroll-controls {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-shrink: 0;
            margin-left: auto;
        }
        .scroll-btn {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 0.8rem;
            font-weight: 700;
        }
        .scroll-btn:hover {
            border-color: #0B5ED7;
            color: #0B5ED7;
            background: #E8F0FE;
            transform: scale(1.08);
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.2);
        }
        [data-theme="dark"] .scroll-btn {
            background: #1E293B;
            border-color: #334155;
            color: #94A3B8;
        }
        [data-theme="dark"] .scroll-btn:hover {
            border-color: #3B82F6;
            color: #3B82F6;
            background: #1A2A4A;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        .info-grid .info-item {
            display: flex;
            flex-direction: column;
        }
        .info-grid .info-item .label {
            font-size: 0.6rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        .info-grid .info-item .value {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-top: 2px;
        }
        .info-grid .info-item .value.mono { font-family: monospace; }
        .info-grid .info-item .value.amount { color: #0B5ED7; font-weight: 700; font-size: 1rem; }
        
        .status-badge {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .status-badge.warning { background: #FEF3C7; color: #D97706; }
        .status-badge.success { background: #D1FAE5; color: #059669; }
        .status-badge.danger { background: #FEE2E2; color: #EF4444; }
        .status-badge.info { background: #E8F0FE; color: #0B5ED7; }
        .status-badge.secondary { background: #E2E8F0; color: #64748B; }
        
        [data-theme="dark"] .status-badge.warning { background: #3A2A1A; color: #FBBF24; }
        [data-theme="dark"] .status-badge.success { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge.danger { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .status-badge.info { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .status-badge.secondary { background: #2D3748; color: #94A3B8; }
        
        .table-scroll-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scroll-behavior: smooth;
        }
        .table-scroll-wrapper::-webkit-scrollbar { height: 6px; }
        .table-scroll-wrapper::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 10px; }
        .table-scroll-wrapper::-webkit-scrollbar-thumb { background: #0B5ED7; border-radius: 10px; }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            min-width: 1000px;
        }
        .data-table thead th {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 10px 14px;
            border-bottom: 3px solid #0A4CA8;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }
        .data-table tbody td {
            padding: 8px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        .data-table tbody tr:hover td { background: var(--primary-bg); }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:nth-child(even) td { background: var(--gray-50); }
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td { background: #1A1A2E; }
        
        .amount-cell { font-weight: 700; color: #0B5ED7; font-family: 'Courier New', monospace; font-size: 0.85rem; }
        [data-theme="dark"] .amount-cell { color: #60A5FA; }
        
        .action-buttons-group {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
            justify-content: center;
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            cursor: pointer;
            min-width: 60px;
        }
        .btn-action i { font-size: 0.65rem; }
        .btn-action:hover { transform: translateY(-2px) scale(1.03); }
        .btn-action:active { transform: scale(0.95); }
        
        .btn-edit {
            background: linear-gradient(135deg, #D97706, #B45309);
            color: white;
            box-shadow: 0 2px 8px rgba(217, 119, 6, 0.25);
        }
        .btn-edit:hover { box-shadow: 0 6px 20px rgba(217, 119, 6, 0.4); background: linear-gradient(135deg, #B45309, #92400E); }
        
        .btn-delete {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
        }
        .btn-delete:hover { box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4); background: linear-gradient(135deg, #B91C1C, #991B1B); }
        
        .btn-cancel {
            background: linear-gradient(135deg, #6B7280, #4B5563);
            color: white;
            box-shadow: 0 2px 8px rgba(107, 114, 128, 0.25);
        }
        .btn-cancel:hover { box-shadow: 0 6px 20px rgba(107, 114, 128, 0.4); background: linear-gradient(135deg, #4B5563, #374151); }
        
        .prescription-group {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .prescription-group:hover {
            border-color: #0B5ED7;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
        }
        .prescription-group .pg-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            padding: 10px 16px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .prescription-group .pg-header .pg-number {
            font-family: monospace;
            font-weight: 700;
            color: #ffffff;
            font-size: 0.85rem;
        }
        .prescription-group .pg-header .pg-number .status-badge {
            background: rgba(255,255,255,0.2) !important;
            color: #ffffff !important;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .prescription-group .pg-header .pg-info {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            color: rgba(255,255,255,0.9);
        }
        .prescription-group .pg-header .pg-info i {
            color: rgba(255,255,255,0.7);
        }
        .prescription-group .pg-header .pg-info .amount {
            font-weight: 700;
            color: #ffffff;
            background: rgba(255,255,255,0.15);
            padding: 2px 12px;
            border-radius: 12px;
        }
        .prescription-group .pg-body {
            padding: 0;
            overflow-x: auto;
        }
        .prescription-group .pg-body table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
        }
        .prescription-group .pg-body table th {
            background: var(--bg-body);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.6rem;
            text-transform: uppercase;
            padding: 6px 10px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        [data-theme="dark"] .prescription-group .pg-body table th {
            background: #1A1A2E;
        }
        .prescription-group .pg-body table td {
            padding: 6px 10px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        .prescription-group .pg-body table tr:last-child td {
            border-bottom: none;
        }
        .prescription-group .pg-body table tr:hover td {
            background: var(--primary-bg);
        }
        .prescription-group .pg-footer {
            background: var(--gray-50);
            padding: 8px 16px;
            display: flex;
            justify-content: flex-end;
            font-weight: 700;
            font-size: 0.85rem;
            color: #0B5ED7;
            border-top: 1px solid var(--border-color);
        }
        [data-theme="dark"] .prescription-group .pg-footer {
            background: #1A1A2E;
        }
        
        .no-items {
            padding: 12px 16px;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.8rem;
        }
        
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: #0B5ED7; font-weight: 600; }
        .text-gray-300 { color: var(--text-muted); }
        .mx-2 { margin-left: 8px; margin-right: 8px; }
        
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 12px;
            z-index: 999;
            max-width: 360px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: #059669; }
        .toast-custom.error { background: #EF4444; }
        .toast-custom.info { background: #0B5ED7; }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .page-header { padding: 14px 18px !important; }
            .page-header .page-title { font-size: 1.1rem !important; }
            .info-grid { grid-template-columns: 1fr 1fr; }
            .prescription-group .pg-header { flex-direction: column; align-items: stretch; }
            .btn-action { font-size: 0.5rem; padding: 3px 8px; min-width: 45px; }
            .scroll-btn { width: 28px; height: 28px; font-size: 0.65rem; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .info-grid { grid-template-columns: 1fr; }
            .page-header .page-title { font-size: 0.95rem !important; }
            .detail-card { padding: 12px 14px; }
            .scroll-btn { width: 24px; height: 24px; font-size: 0.55rem; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
    </style>
</head>
<body>

<!-- TOP NAVIGATION -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper" style="flex:1;display:flex;align-items:center;background:var(--bg-body);border-radius:10px;border:1px solid var(--border-color);padding:0 12px;">
            <i class="fas fa-search text-gray-400"></i>
            <span style="padding:8px 12px;font-size:0.85rem;color:var(--text-secondary);">
                <i class="fas fa-user"></i> <?= htmlspecialchars($current_prescription['patient_name'] ?? 'Unknown') ?> - Prescriptions
            </span>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header - Blue Background -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-md mr-2"></i>
                Patient Prescriptions
                <?php if ($selected_branch_id !== 'all'): ?>
                    <span class="branch-tag ml-2">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($selected_branch_name) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i> Patient: <strong><?= htmlspecialchars($current_prescription['patient_name'] ?? 'Unknown') ?></strong>
                <span class="branch-tag ml-2">
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($current_prescription['patient_number'] ?? 'N/A') ?>
                </span>
                <span class="ml-2 inline-flex bg-white/20 text-white px-3 py-1 rounded-full text-xs border border-white/10">
                    <i class="fas fa-prescription"></i> <?= count($all_prescriptions) ?> prescriptions
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="prescriptions.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="../patients/view_patient.php?id=<?= $patient_id ?>" class="btn-outline-light">
                <i class="fas fa-external-link-alt"></i> View Patient
            </a>
        </div>
    </div>

    <!-- PATIENT INFO -->
    <div class="detail-card animate-fade-in-up">
        <div class="card-title">
            <i class="fas fa-user"></i> Patient Information
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="label">Patient Name</span>
                <span class="value"><?= htmlspecialchars($current_prescription['patient_name'] ?? 'Unknown') ?></span>
            </div>
            <div class="info-item">
                <span class="label">Patient ID</span>
                <span class="value mono"><?= htmlspecialchars($current_prescription['patient_number'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="label">Gender</span>
                <span class="value"><?= htmlspecialchars($current_prescription['patient_gender'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="label">Phone</span>
                <span class="value"><?= htmlspecialchars($current_prescription['patient_phone'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($current_prescription['date_of_birth'])): ?>
            <div class="info-item">
                <span class="label">Date of Birth</span>
                <span class="value"><?= date('M d, Y', strtotime($current_prescription['date_of_birth'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($current_prescription['patient_address'])): ?>
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="label">Address</span>
                <span class="value"><?= htmlspecialchars($current_prescription['patient_address']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ALL PRESCRIPTIONS WITH ITEMS -->
    <div class="detail-card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="card-title">
            <i class="fas fa-prescription"></i> All Prescriptions
            <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);margin-left:8px;">
                (<?= count($all_prescriptions) ?> prescriptions)
            </span>
            <!-- Scroll Controls -->
            <div class="scroll-controls">
                <button class="scroll-btn" onclick="scrollTable('left')" title="Scroll Left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="scroll-btn" onclick="scrollTable('right')" title="Scroll Right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <?php if (count($all_prescriptions) > 0): ?>
            <?php foreach ($all_prescriptions as $prescription): 
                $grand_total = 0;
                foreach ($prescription['items'] as $item) {
                    $grand_total += $item['total_price'];
                }
            ?>
            <div class="prescription-group" id="pg-<?= $prescription['id'] ?>">
                <div class="pg-header">
                    <div>
                        <span class="pg-number"><?= htmlspecialchars($prescription['prescription_number']) ?></span>
                        <span class="status-badge" style="background:rgba(255,255,255,0.2) !important; color:#ffffff !important; border:1px solid rgba(255,255,255,0.2); font-size:0.55rem; padding:1px 10px; margin-left:8px;">
                            <?php if ($prescription['status'] === 'pending'): ?>⏳<?php elseif ($prescription['status'] === 'confirmed'): ?>✅<?php elseif ($prescription['status'] === 'dispensed'): ?>💊<?php else: ?>❌<?php endif; ?>
                            <?= ucfirst($prescription['status'] ?? 'Unknown') ?>
                        </span>
                    </div>
                    <div class="pg-info">
                        <span><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></span>
                        <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($prescription['created_at'])) ?></span>
                        <span><i class="fas fa-pills"></i> <?= $prescription['item_count'] ?? 0 ?> items</span>
                        <span class="amount">TSh <?= number_format($prescription['total_amount'] ?? 0, 0) ?></span>
                    </div>
                </div>
                
                <div class="pg-body">
                    <?php if (count($prescription['items']) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:30px;">#</th>
                                    <th style="min-width:130px;">Medication</th>
                                    <th style="min-width:70px;">Dosage</th>
                                    <th style="min-width:80px;">Frequency</th>
                                    <th style="min-width:50px;text-align:center;">Qty</th>
                                    <th style="min-width:60px;">Duration</th>
                                    <th style="min-width:70px;">Route</th>
                                    <th style="min-width:120px;">Instructions</th>
                                    <th style="min-width:90px;text-align:right;">Unit Price</th>
                                    <th style="min-width:90px;text-align:right;">Total</th>
                                    <th style="min-width:200px;text-align:center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($prescription['items'] as $item): ?>
                                    <tr>
                                        <td class="font-bold text-blue-600 dark:text-blue-400"><?= $i++ ?></td>
                                        <td>
                                            <div class="font-semibold"><?= htmlspecialchars($item['medication_name'] ?? 'Unknown') ?></div>
                                            <?php if (!empty($item['inventory_batch_number'])): ?>
                                                <div class="text-xs text-gray-400">Batch: <?= htmlspecialchars($item['inventory_batch_number']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($item['dosage'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($item['frequency'] ?? 'N/A') ?></td>
                                        <td class="text-center font-bold"><?= $item['quantity'] ?? 0 ?></td>
                                        <td><?= htmlspecialchars($item['duration'] ?? 'N/A') ?> days</td>
                                        <td><?= htmlspecialchars($item['route'] ?? 'N/A') ?></td>
                                        <td>
                                            <?php if (!empty($item['instructions'])): ?>
                                                <span style="font-size:0.65rem;background:var(--primary-bg);color:#0B5ED7;padding:1px 6px;border-radius:4px;">
                                                    <?= htmlspecialchars(substr($item['instructions'], 0, 30)) . (strlen($item['instructions']) > 30 ? '...' : '') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right amount-cell">TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                        <td class="text-right amount-cell">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                        <td>
                                            <div class="action-buttons-group">
                                                <a href="edit_prescription_item.php?id=<?= $item['id'] ?>&prescription_id=<?= $prescription['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" 
                                                   class="btn-action btn-edit" title="Edit Item">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="?cancel_item=<?= $item['id'] ?>&id=<?= $prescription_id ?>&branch=<?= urlencode($selected_branch_id) ?>&pid=<?= $prescription['id'] ?>" 
                                                   class="btn-action btn-cancel" title="Cancel Item"
                                                   onclick="return confirm('⚠️ Cancel this item?\n\nMedication: <?= htmlspecialchars($item['medication_name'] ?? 'Unknown') ?>\nQuantity: <?= $item['quantity'] ?? 0 ?>\nAmount: TSh <?= number_format($item['total_price'] ?? 0, 0) ?>')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                                <a href="?delete_item=<?= $item['id'] ?>&id=<?= $prescription_id ?>&branch=<?= urlencode($selected_branch_id) ?>&pid=<?= $prescription['id'] ?>" 
                                                   class="btn-action btn-delete" title="Delete Item"
                                                   onclick="return confirm('⚠️ Delete this item permanently?\n\nMedication: <?= htmlspecialchars($item['medication_name'] ?? 'Unknown') ?>\nQuantity: <?= $item['quantity'] ?? 0 ?>\nAmount: TSh <?= number_format($item['total_price'] ?? 0, 0) ?>')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="9" style="text-align:right;font-weight:600;padding:6px 10px;">
                                        <span style="font-size:0.7rem;color:var(--text-secondary);">GRAND TOTAL:</span>
                                    </td>
                                    <td style="font-weight:700;color:#0B5ED7;padding:6px 10px;text-align:right;">
                                        TSh <?= number_format($grand_total, 0) ?>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    <?php else: ?>
                        <div class="no-items">
                            <i class="fas fa-prescription" style="color:#0B5ED7;margin-right:6px;"></i>
                            No items in this prescription
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- Overall Total -->
            <div style="background:linear-gradient(135deg, #0B5ED7, #0A4CA8);border-radius:12px;padding:12px 20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-top:8px;color:white;">
                <span style="font-weight:600;font-size:0.85rem;opacity:0.9;">
                    <i class="fas fa-calculator"></i> OVERALL TOTAL (All Prescriptions)
                </span>
                <span style="font-size:1.1rem;font-weight:700;">
                    <?php 
                        $overall_total = 0;
                        foreach ($all_prescriptions as $p) {
                            $overall_total += $p['total_amount'];
                        }
                    ?>
                    TSh <?= number_format($overall_total, 0) ?>
                </span>
            </div>
            
        <?php else: ?>
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-prescription text-3xl block mb-2" style="color:#0B5ED7;"></i>
                <p style="color:var(--text-primary);font-weight:500;">No prescriptions found for this patient</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Patient Prescriptions
            <span class="text-gray-300 mx-2">|</span>
            <?= htmlspecialchars($current_prescription['patient_name'] ?? 'Unknown') ?>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- JAVASCRIPT -->
<script>
    // ================================================================
    // TABLE SCROLL FUNCTION
    // ================================================================
    function scrollTable(direction) {
        var wrappers = document.querySelectorAll('.prescription-group .pg-body');
        if (!wrappers || wrappers.length === 0) {
            var mainWrapper = document.querySelector('.table-scroll-wrapper');
            if (mainWrapper) {
                wrappers = [mainWrapper];
            } else {
                return;
            }
        }
        
        var scrollAmount = 250;
        wrappers.forEach(function(wrapper) {
            if (direction === 'left') {
                wrapper.scrollLeft -= scrollAmount;
            } else {
                wrapper.scrollLeft += scrollAmount;
            }
        });
    }

    // ================================================================
    // DARK MODE
    // ================================================================
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');

    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // TOAST FUNCTION
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    // ================================================================
    // SHOW TOAST MESSAGES FROM PHP
    // ================================================================
    <?php if (isset($_GET['item_deleted']) && $_GET['item_deleted'] == 1): ?>
        showToast('✅ Success', 'Item deleted successfully!', 'success');
        // Remove the parameter from URL to prevent re-triggering
        if (window.history && window.history.replaceState) {
            var cleanUrl = window.location.href.split('?')[0] + '?id=<?= $prescription_id ?>&branch=<?= urlencode($selected_branch_id) ?>';
            window.history.replaceState({}, document.title, cleanUrl);
        }
    <?php endif; ?>
    
    <?php if (isset($_GET['item_cancelled']) && $_GET['item_cancelled'] == 1): ?>
        showToast('✅ Success', 'Item cancelled successfully!', 'success');
        if (window.history && window.history.replaceState) {
            var cleanUrl = window.location.href.split('?')[0] + '?id=<?= $prescription_id ?>&branch=<?= urlencode($selected_branch_id) ?>';
            window.history.replaceState({}, document.title, cleanUrl);
        }
    <?php endif; ?>
    
    <?php if (isset($_GET['error']) && $_GET['error'] == 'delete_failed'): ?>
        showToast('⚠️ Error', 'Failed to delete item. Please try again.', 'error');
        if (window.history && window.history.replaceState) {
            var cleanUrl = window.location.href.split('?')[0] + '?id=<?= $prescription_id ?>&branch=<?= urlencode($selected_branch_id) ?>';
            window.history.replaceState({}, document.title, cleanUrl);
        }
    <?php endif; ?>
    
    <?php if (isset($_GET['error']) && $_GET['error'] == 'cancel_failed'): ?>
        showToast('⚠️ Error', 'Failed to cancel item. Please try again.', 'error');
        if (window.history && window.history.replaceState) {
            var cleanUrl = window.location.href.split('?')[0] + '?id=<?= $prescription_id ?>&branch=<?= urlencode($selected_branch_id) ?>';
            window.history.replaceState({}, document.title, cleanUrl);
        }
    <?php endif; ?>

    // ================================================================
    // DATE TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c🏥 Braick Dispensary - Patient Prescriptions', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($current_prescription['patient_name'] ?? 'Unknown') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Total Prescriptions: <?= count($all_prescriptions) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Delete works in ONE click - immediate redirect with exit', 'font-size:13px; color:#34D399;');
    console.log('%c⬅️➡️ Scroll buttons fixed - scrolls each prescription table', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>