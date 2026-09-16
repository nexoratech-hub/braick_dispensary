<?php
// ================================================================
// FILE: frontend/pages/admin/pharmacy_revenue.php
// ADMIN - PHARMACY REVENUE DETAILS
// BRAICK DISPENSARY - USING EXISTING DB TABLES
// ✅ Uses SHARED header & sidebar
// ✅ Page-specific CSS only (no duplicates)
// ✅ Dark mode via header toggle
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET BRANCH ID
// ================================================================
$branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;

if ($branch_id <= 0) {
    $branch_id = $user_branch_id;
}

// ================================================================
// FETCH BRANCH DETAILS
// ================================================================
$branch = null;

try {
    $stmt = $db->prepare("
        SELECT 
            b.id, b.name, b.location, b.phone, b.email, b.logo, b.status, b.created_at, b.updated_at,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy' AND status = 'active') as active_pharmacists,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy') as total_pharmacists
        FROM branches b
        WHERE b.id = ?
    ");
    $stmt->execute([$branch_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$branch) {
        $stmt = $db->prepare("
            SELECT 
                b.id, b.name, b.location, b.phone, b.email, b.logo, b.status, b.created_at, b.updated_at,
                (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy' AND status = 'active') as active_pharmacists,
                (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'pharmacy') as total_pharmacists
            FROM branches b
            WHERE b.id = ?
        ");
        $stmt->execute([$user_branch_id]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        $branch_id = $user_branch_id;
    }
    
    if (!$branch) {
        $branch = [
            'id' => $user_branch_id,
            'name' => $user_branch_name ?? 'Dodoma',
            'location' => 'Dodoma City, Tanzania',
            'phone' => '+255 700 000 001',
            'email' => 'dodoma@braick.com',
            'status' => 'active',
            'active_pharmacists' => 0,
            'total_pharmacists' => 0
        ];
    }
} catch (Exception $e) {
    $branch = [
        'id' => $user_branch_id,
        'name' => $user_branch_name ?? 'Dodoma',
        'location' => 'Dodoma City, Tanzania',
        'phone' => '+255 700 000 001',
        'email' => 'dodoma@braick.com',
        'status' => 'active',
        'active_pharmacists' => 0,
        'total_pharmacists' => 0
    ];
}

// ================================================================
// PHARMACY REVENUE QUERIES
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(bi.total_price), 0) as total_revenue,
            COUNT(DISTINCT bi.bill_id) as total_prescriptions,
            COALESCE(AVG(bi.total_price), 0) as avg_prescription,
            COALESCE(MAX(bi.total_price), 0) as max_prescription,
            COALESCE(MIN(bi.total_price), 0) as min_prescription
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE bi.item_type = 'medication' 
        AND b.branch_id = ?
        AND b.status = 'paid'
        AND bi.status != 'cancelled'
    ");
    $stmt->execute([$branch_id]);
    $prescription_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $prescription_stats = ['total_revenue' => 0, 'total_prescriptions' => 0, 'avg_prescription' => 0, 'max_prescription' => 0, 'min_prescription' => 0];
}

try {
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COUNT(*) as total_otc_sales,
            COALESCE(AVG(total_amount), 0) as avg_otc,
            COALESCE(MAX(total_amount), 0) as max_otc,
            COALESCE(MIN(total_amount), 0) as min_otc
        FROM otc_sales 
        WHERE branch_id = ? 
        AND payment_status = 'paid'
    ");
    $stmt->execute([$branch_id]);
    $otc_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $otc_stats = ['total_revenue' => 0, 'total_otc_sales' => 0, 'avg_otc' => 0, 'max_otc' => 0, 'min_otc' => 0];
}

$pharmacy_total = ($prescription_stats['total_revenue'] ?? 0) + ($otc_stats['total_revenue'] ?? 0);

// Monthly revenue
$monthly_revenue = [];
try {
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            DATE_FORMAT(created_at, '%b %Y') as month_name,
            COALESCE(SUM(CASE WHEN source = 'prescription' THEN amount ELSE 0 END), 0) as prescribe_revenue,
            COALESCE(SUM(CASE WHEN source = 'otc' THEN amount ELSE 0 END), 0) as otc_revenue,
            COALESCE(SUM(amount), 0) as total_revenue
        FROM (
            SELECT 'prescription' as source, b.created_at, bi.total_price as amount
            FROM bill_items bi
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE bi.item_type = 'medication' 
            AND b.branch_id = ?
            AND b.status = 'paid'
            AND bi.status != 'cancelled'
            
            UNION ALL
            
            SELECT 'otc' as source, os.created_at, os.total_amount as amount
            FROM otc_sales os
            WHERE os.branch_id = ? 
            AND os.payment_status = 'paid'
        ) as combined
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b %Y')
        ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC
    ");
    $stmt->execute([$branch_id, $branch_id]);
    $monthly_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $monthly_revenue = [];
}

// Recent prescriptions
$recent_prescriptions = [];
try {
    $stmt = $db->prepare("
        SELECT 
            b.bill_number as sale_number,
            b.total_amount,
            b.discount_amount,
            b.paid_amount as net_amount,
            b.payment_method,
            b.created_at,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            u.full_name as pharmacist_name,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND item_type = 'medication') as item_count
        FROM bills b
        LEFT JOIN patients p ON b.patient_id = p.id
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.branch_id = ?
        AND b.status = 'paid'
        AND EXISTS (SELECT 1 FROM bill_items WHERE bill_id = b.id AND item_type = 'medication')
        ORDER BY b.created_at DESC
        LIMIT 15
    ");
    $stmt->execute([$branch_id]);
    $recent_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_prescriptions = [];
}

// Recent OTC
$recent_otc_sales = [];
try {
    $stmt = $db->prepare("
        SELECT 
            os.id, os.sale_number, os.customer_name, os.total_amount, os.discount_amount,
            os.total_amount as net_amount, os.payment_method, os.created_at,
            u.full_name as sold_by_name,
            (SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = os.id) as item_count
        FROM otc_sales os
        LEFT JOIN users u ON os.sold_by = u.id
        WHERE os.branch_id = ?
        AND os.payment_status = 'paid'
        ORDER BY os.created_at DESC
        LIMIT 15
    ");
    $stmt->execute([$branch_id]);
    $recent_otc_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_otc_sales = [];
}

// Top medications
$top_medications = [];
try {
    $stmt = $db->prepare("
        SELECT 
            pi.medication_name,
            SUM(pi.quantity) as total_quantity,
            COUNT(pi.id) as total_prescriptions,
            COALESCE(AVG(pi.unit_price), 0) as avg_price,
            SUM(pi.total_price) as total_revenue
        FROM prescription_items pi
        INNER JOIN prescriptions p ON pi.prescription_id = p.id
        WHERE p.branch_id = ?
        AND p.status = 'dispensed'
        GROUP BY pi.medication_name
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $stmt->execute([$branch_id]);
    $top_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $top_medications = [];
}

// Top OTC items
$top_otc_items = [];
try {
    $stmt = $db->prepare("
        SELECT 
            oi.item_name as medication_name,
            SUM(oi.quantity) as total_quantity,
            COUNT(oi.id) as total_sales,
            COALESCE(AVG(oi.unit_price), 0) as avg_price,
            SUM(oi.total_price) as total_revenue
        FROM otc_sale_items oi
        INNER JOIN otc_sales os ON oi.sale_id = os.id
        WHERE os.branch_id = ?
        AND os.payment_status = 'paid'
        GROUP BY oi.item_name
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $stmt->execute([$branch_id]);
    $top_otc_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $top_otc_items = [];
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function formatCurrency($amount) {
    return 'TSh ' . number_format($amount, 0);
}

function getPaymentMethodBadge($method) {
    $classes = [
        'cash' => 'success', 'm-pesa' => 'info', 'airtel_money' => 'info',
        'tigo_pesa' => 'info', 'halopesa' => 'info', 'bank' => 'purple',
        'card' => 'purple', 'insurance' => 'teal', 'other' => 'secondary'
    ];
    return $classes[$method] ?? 'secondary';
}

function getPaymentMethodIcon($method) {
    $icons = [
        'cash' => 'fa-money-bill-wave', 'm-pesa' => 'fa-mobile-alt',
        'airtel_money' => 'fa-mobile-alt', 'tigo_pesa' => 'fa-mobile-alt',
        'halopesa' => 'fa-mobile-alt', 'bank' => 'fa-university',
        'card' => 'fa-credit-card', 'insurance' => 'fa-shield-alt', 'other' => 'fa-circle'
    ];
    return $icons[$method] ?? 'fa-circle';
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE VARIABLES
       ================================================================ */
    :root {
        --pr-primary: #059669;
        --pr-primary-dark: #047857;
        --pr-primary-light: #34D399;
        --pr-primary-bg: #D1FAE5;
        --pr-primary-gradient: linear-gradient(135deg, #059669, #047857);
        --pr-primary-gradient-strong: linear-gradient(135deg, #047857, #065F46);
        --pr-success: #059669;
        --pr-success-bg: #D1FAE5;
        --pr-danger: #DC2626;
        --pr-danger-bg: #FEE2E2;
        --pr-warning: #D97706;
        --pr-warning-bg: #FEF3C7;
        --pr-purple: #7C3AED;
        --pr-purple-bg: #EDE9FE;
        --pr-teal: #0D9488;
        --pr-teal-bg: #ECFDF5;
        --pr-orange: #F59E0B;
        --pr-orange-bg: #FFFBEB;
        --pr-pink: #EC4899;
        --pr-indigo: #4F46E5;
        --pr-blue: #0B5ED7;
        --pr-blue-bg: #E8F0FE;
        --pr-gray-50: #F8FAFC;
        --pr-gray-100: #F1F5F9;
        --pr-gray-200: #E2E8F0;
        --pr-gray-300: #CBD5E1;
        --pr-gray-400: #94A3B8;
        --pr-gray-500: #64748B;
        --pr-gray-600: #475569;
        --pr-gray-700: #334155;
        --pr-gray-800: #1E293B;
        --pr-gray-900: #0F172A;
        --pr-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --pr-shadow: 0 1px 3px rgba(0,0,0,0.08);
        --pr-shadow-md: 0 8px 30px rgba(0,0,0,0.12);
        --pr-shadow-lg: 0 15px 50px rgba(0,0,0,0.15);
        --pr-bg-body: #F0FDF4;
        --pr-bg-card: #FFFFFF;
        --pr-text-primary: #1E293B;
        --pr-text-secondary: #64748B;
        --pr-border-color: #D1FAE5;
        --pr-radius: 16px;
        --pr-radius-lg: 24px;
        --pr-table-hover: #ECFDF5;
    }

    [data-theme="dark"] {
        --pr-bg-body: #0F172A;
        --pr-bg-card: #1E293B;
        --pr-text-primary: #F1F5F9;
        --pr-text-secondary: #94A3B8;
        --pr-border-color: #334155;
        --pr-primary: #34D399;
        --pr-primary-dark: #059669;
        --pr-primary-bg: #1A3A2A;
        --pr-shadow: 0 1px 3px rgba(0,0,0,0.3);
        --pr-shadow-md: 0 8px 30px rgba(0,0,0,0.3);
        --pr-table-hover: #1A3A2A;
    }

    /* ================================================================
       DARK MODE - PAGE YOTE
       ================================================================ */
    html[data-theme="dark"] body {
        background: #0F172A !important;
    }

    html[data-theme="dark"] .main-content {
        background: #0F172A !important;
        color: #F1F5F9;
    }

    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header-pr {
        background: var(--pr-primary-gradient-strong);
        border-radius: var(--pr-radius-lg);
        padding: 32px 40px;
        margin-bottom: 32px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(4, 120, 87, 0.35);
        position: relative;
        overflow: hidden;
    }

    .page-header-pr::before {
        content: '';
        position: absolute;
        top: -60%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-pr::after {
        content: '';
        position: absolute;
        bottom: -40%;
        left: -5%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.03);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-pr .page-title {
        color: white;
        font-size: 2rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-pr .page-title i {
        font-size: 2.2rem;
        opacity: 0.9;
    }

    .page-header-pr .page-subtitle {
        color: rgba(255,255,255,0.85);
        font-size: 1rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 8px;
    }

    .page-header-pr .page-subtitle strong {
        color: white;
        font-weight: 600;
    }

    .page-header-pr .role-badge-display {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 4px 16px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        backdrop-filter: blur(4px);
    }

    .page-header-pr .header-badge {
        background: rgba(255,255,255,0.12);
        color: white;
        padding: 4px 16px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
        backdrop-filter: blur(4px);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(255,255,255,0.1);
        transition: all 0.3s ease;
    }

    .page-header-pr .header-badge:hover {
        background: rgba(255,255,255,0.2);
        transform: translateY(-1px);
    }

    .page-header-pr .btn-outline-light {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 20px;
        border-radius: var(--pr-radius);
        font-weight: 500;
        font-size: 0.85rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        backdrop-filter: blur(4px);
        position: relative;
        z-index: 1;
    }

    .page-header-pr .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* ================================================================
       STATS CARDS
       ================================================================ */
    .stats-grid-pr {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 28px;
    }

    .stat-card-pr {
        background: var(--pr-bg-card);
        border-radius: var(--pr-radius-lg);
        border: 2px solid var(--pr-border-color);
        padding: 24px 28px;
        box-shadow: var(--pr-shadow-sm);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        color: white !important;
    }

    .stat-card-pr:hover {
        transform: translateY(-4px);
        box-shadow: var(--pr-shadow-md);
        border-color: var(--pr-primary);
    }

    .stat-card-pr .stat-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        margin-bottom: 10px;
        background: rgba(255,255,255,0.2) !important;
        color: white !important;
        border: 1px solid rgba(255,255,255,0.15);
    }

    .stat-card-pr .stat-number {
        font-size: 2.2rem;
        font-weight: 800;
        line-height: 1.1;
        color: white !important;
    }

    .stat-card-pr .stat-label {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 4px 0 0 0;
        color: rgba(255,255,255,0.85) !important;
    }

    .stat-card-pr .stat-sub {
        font-size: 0.7rem;
        margin: 4px 0 0 0;
        color: rgba(255,255,255,0.7) !important;
    }

    .stat-card-pr.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    .stat-card-pr.orange { background: linear-gradient(135deg, #D97706, #B45309); }
    .stat-card-pr.green { background: linear-gradient(135deg, #059669, #047857); }
    .stat-card-pr.teal { background: linear-gradient(135deg, #0D9488, #0F766E); }

    .stat-card-pr.purple .stat-icon { background: rgba(255,255,255,0.15) !important; }
    .stat-card-pr.orange .stat-icon { background: rgba(255,255,255,0.15) !important; }
    .stat-card-pr.green .stat-icon { background: rgba(255,255,255,0.15) !important; }
    .stat-card-pr.teal .stat-icon { background: rgba(255,255,255,0.15) !important; }

    [data-theme="dark"] .stat-card-pr.purple { background: linear-gradient(135deg, #6D28D9, #5B21B6); }
    [data-theme="dark"] .stat-card-pr.orange { background: linear-gradient(135deg, #B45309, #92400E); }
    [data-theme="dark"] .stat-card-pr.green { background: linear-gradient(135deg, #047857, #065F46); }
    [data-theme="dark"] .stat-card-pr.teal { background: linear-gradient(135deg, #0F766E, #0D5E56); }

    /* ================================================================
       TABLE CONTAINER
       ================================================================ */
    .table-container-pr {
        background: var(--pr-bg-card);
        border-radius: var(--pr-radius-lg);
        border: 2px solid var(--pr-border-color);
        overflow: hidden;
        box-shadow: var(--pr-shadow-sm);
        margin-bottom: 28px;
    }

    .table-container-pr .card-header-pr {
        padding: 14px 24px;
        background: var(--pr-primary-gradient-strong);
        border-bottom: 2px solid var(--pr-border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .table-container-pr .card-header-pr .card-title-pr {
        font-size: 0.9rem;
        font-weight: 700;
        color: white;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .table-container-pr .card-header-pr .card-title-pr i {
        color: rgba(255,255,255,0.8);
    }

    .table-container-pr .card-header-pr .card-action-pr {
        color: rgba(255,255,255,0.7);
        font-size: 0.7rem;
        text-decoration: none;
        transition: all 0.3s;
    }

    .table-container-pr .card-header-pr .card-action-pr:hover {
        color: white;
    }

    .table-container-pr .card-body-pr {
        padding: 20px 24px;
    }

    .chart-container-pr {
        position: relative;
        height: 280px;
        width: 100%;
    }

    .data-table-pr {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.82rem;
    }

    .data-table-pr thead th {
        background: var(--pr-bg-body);
        color: var(--pr-text-secondary);
        font-weight: 700;
        padding: 12px 16px;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--pr-border-color);
        text-align: left;
    }

    [data-theme="dark"] .data-table-pr thead th {
        background: #0F172A;
    }

    .data-table-pr td {
        padding: 10px 16px;
        border-bottom: 1px solid var(--pr-border-color);
        color: var(--pr-text-primary);
        vertical-align: middle;
    }

    .data-table-pr tbody tr:hover td {
        background: var(--pr-table-hover);
    }

    .data-table-pr tbody tr:last-child td {
        border-bottom: none;
    }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge-pr {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        color: white;
        letter-spacing: 0.02em;
    }

    .badge-success-pr { background: #059669; }
    .badge-danger-pr { background: #DC2626; }
    .badge-warning-pr { background: #D97706; color: #1E293B; }
    .badge-info-pr { background: #0B5ED7; }
    .badge-secondary-pr { background: #64748B; }
    .badge-purple-pr { background: #7C3AED; }
    .badge-teal-pr { background: #0D9488; }

    [data-theme="dark"] .badge-warning-pr { color: #1E293B; }

    /* ================================================================
       TEXT UTILITIES
       ================================================================ */
    .text-primary-pr { color: var(--pr-primary); }
    .text-success-pr { color: #059669; }
    .text-danger-pr { color: #DC2626; }
    .text-warning-pr { color: #D97706; }
    .text-purple-pr { color: #7C3AED; }
    .text-orange-pr { color: #D97706; }
    .text-green-pr { color: #059669; }
    .text-gray-400-pr { color: var(--pr-text-secondary); }
    .font-mono-pr { font-family: monospace; }
    .font-bold-pr { font-weight: 700; }
    .font-semibold-pr { font-weight: 600; }
    .font-medium-pr { font-weight: 500; }

    .empty-state-pr {
        text-align: center;
        padding: 32px 20px;
        color: var(--pr-text-secondary);
    }

    .empty-state-pr i {
        font-size: 3rem;
        display: block;
        margin-bottom: 12px;
        color: var(--pr-border-color);
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-pr {
        padding: 16px 0;
        border-top: 2px solid var(--pr-border-color);
        margin-top: 28px;
        text-align: center;
        font-size: 0.75rem;
        color: var(--pr-text-secondary);
    }

    .footer-pr .footer-brand-pr {
        color: var(--pr-primary);
        font-weight: 700;
    }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up {
        animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        opacity: 0;
    }

    .stats-grid-pr .stat-card-pr {
        animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        opacity: 0;
    }

    .stats-grid-pr .stat-card-pr:nth-child(1) { animation-delay: 0.05s; }
    .stats-grid-pr .stat-card-pr:nth-child(2) { animation-delay: 0.10s; }
    .stats-grid-pr .stat-card-pr:nth-child(3) { animation-delay: 0.15s; }
    .stats-grid-pr .stat-card-pr:nth-child(4) { animation-delay: 0.20s; }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .stats-grid-pr { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-pr { padding: 18px 20px; }
        .page-header-pr .page-title { font-size: 1.4rem; }
        .stats-grid-pr { grid-template-columns: 1fr 1fr; gap: 12px; }
        .stat-card-pr .stat-number { font-size: 1.6rem; }
        .stat-card-pr { padding: 16px 18px; }
        .data-table-pr { font-size: 0.7rem; }
        .data-table-pr thead th, .data-table-pr td { padding: 8px 10px; }
        .table-container-pr .card-header-pr { padding: 12px 16px; }
        .table-container-pr .card-body-pr { padding: 12px 16px; }
    }

    @media (max-width: 480px) {
        .stats-grid-pr { grid-template-columns: 1fr; gap: 10px; }
        .page-header-pr { flex-direction: column; align-items: flex-start !important; }
        .stat-card-pr .stat-number { font-size: 1.8rem; }
        .data-table-pr { font-size: 0.6rem; }
        .data-table-pr thead th, .data-table-pr td { padding: 6px 8px; }
    }

    /* ================================================================
       PRINT
       ================================================================ */
    @media print {
        .btn-outline-light, .page-header-pr .btn-outline-light { display: none !important; }
        .table-container-pr { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
        .page-header-pr {
            background: #059669 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header-pr">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription-bottle"></i>
                Pharmacy Revenue
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($branch['name'] ?? 'Dodoma') ?></strong>
                <span class="header-badge">
                    <i class="fas fa-<?= ($branch['status'] ?? 'active') === 'active' ? 'check-circle' : 'times-circle' ?>"></i>
                    <?= ucfirst($branch['status'] ?? 'Active') ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-money-bill-wave"></i> <?= formatCurrency($pharmacy_total) ?> Total
                </span>
                <span class="header-badge">
                    <i class="fas fa-prescription"></i> <?= $prescription_stats['total_prescriptions'] ?? 0 ?> Rx
                </span>
                <span class="header-badge">
                    <i class="fas fa-shopping-cart"></i> <?= $otc_stats['total_otc_sales'] ?? 0 ?> OTC
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="cashier_dashboard.php?branch=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="pharmacy_revenue_report.php?branch=<?= $branch_id ?>" class="btn-outline-light">
                <i class="fas fa-file-pdf"></i> Export Report
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid-pr">
        <div class="stat-card-pr purple">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <p class="stat-number"><?= formatCurrency($prescription_stats['total_revenue'] ?? 0) ?></p>
            <p class="stat-label">Prescription Revenue</p>
            <p class="stat-sub"><?= $prescription_stats['total_prescriptions'] ?? 0 ?> prescriptions • Avg: <?= formatCurrency($prescription_stats['avg_prescription'] ?? 0) ?></p>
        </div>
        
        <div class="stat-card-pr orange">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <p class="stat-number"><?= formatCurrency($otc_stats['total_revenue'] ?? 0) ?></p>
            <p class="stat-label">OTC Revenue</p>
            <p class="stat-sub"><?= $otc_stats['total_otc_sales'] ?? 0 ?> OTC sales • Avg: <?= formatCurrency($otc_stats['avg_otc'] ?? 0) ?></p>
        </div>
        
        <div class="stat-card-pr green">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <p class="stat-number"><?= formatCurrency($pharmacy_total) ?></p>
            <p class="stat-label">Total Pharmacy Revenue</p>
            <p class="stat-sub"><?= ($prescription_stats['total_prescriptions'] ?? 0) + ($otc_stats['total_otc_sales'] ?? 0) ?> total transactions</p>
        </div>
        
        <div class="stat-card-pr teal">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <p class="stat-number"><?= $branch['active_pharmacists'] ?? 0 ?>/<?= $branch['total_pharmacists'] ?? 0 ?></p>
            <p class="stat-label">Pharmacists</p>
            <p class="stat-sub">Active / Total</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MONTHLY REVENUE CHART -->
    <!-- ================================================================ -->
    <div class="table-container-pr animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="card-header-pr">
            <h3 class="card-title-pr">
                <i class="fas fa-chart-bar"></i>
                Monthly Pharmacy Revenue (Last 12 Months)
            </h3>
        </div>
        <div class="card-body-pr">
            <div class="chart-container-pr">
                <canvas id="monthlyRevenueChart"></canvas>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TOP MEDICATIONS -->
    <!-- ================================================================ -->
    <div class="table-container-pr animate-fade-in-up" style="animation-delay:0.3s;">
        <div class="card-header-pr">
            <h3 class="card-title-pr">
                <i class="fas fa-pills"></i>
                Top Prescription Medications (<?= count($top_medications) ?>)
            </h3>
        </div>
        <?php if (count($top_medications) > 0): ?>
            <div style="overflow-x:auto;">
                <table class="data-table-pr">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Medication Name</th>
                            <th style="text-align:center;">Prescriptions</th>
                            <th style="text-align:center;">Total Qty</th>
                            <th style="text-align:right;">Avg Price</th>
                            <th style="text-align:right;">Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($top_medications as $med): ?>
                            <tr>
                                <td class="font-semibold-pr text-gray-400-pr">#<?= $rank++ ?></td>
                                <td class="font-medium-pr"><?= htmlspecialchars($med['medication_name'] ?? 'N/A') ?></td>
                                <td style="text-align:center;"><?= $med['total_prescriptions'] ?? 0 ?></td>
                                <td style="text-align:center;"><?= $med['total_quantity'] ?? 0 ?></td>
                                <td style="text-align:right;font-family:monospace;"><?= formatCurrency($med['avg_price'] ?? 0) ?></td>
                                <td style="text-align:right;font-family:monospace;font-weight:700;color:#059669;"><?= formatCurrency($med['total_revenue'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state-pr">
                <i class="fas fa-pills"></i>
                <p>No prescription medication data found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- TOP OTC ITEMS -->
    <!-- ================================================================ -->
    <div class="table-container-pr animate-fade-in-up" style="animation-delay:0.35s;">
        <div class="card-header-pr">
            <h3 class="card-title-pr">
                <i class="fas fa-capsules"></i>
                Top OTC Items (<?= count($top_otc_items) ?>)
            </h3>
        </div>
        <?php if (count($top_otc_items) > 0): ?>
            <div style="overflow-x:auto;">
                <table class="data-table-pr">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th style="text-align:center;">Sales</th>
                            <th style="text-align:center;">Total Qty</th>
                            <th style="text-align:right;">Avg Price</th>
                            <th style="text-align:right;">Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($top_otc_items as $item): ?>
                            <tr>
                                <td class="font-semibold-pr text-gray-400-pr">#<?= $rank++ ?></td>
                                <td class="font-medium-pr"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
                                <td style="text-align:center;"><?= $item['total_sales'] ?? 0 ?></td>
                                <td style="text-align:center;"><?= $item['total_quantity'] ?? 0 ?></td>
                                <td style="text-align:right;font-family:monospace;"><?= formatCurrency($item['avg_price'] ?? 0) ?></td>
                                <td style="text-align:right;font-family:monospace;font-weight:700;color:#D97706;"><?= formatCurrency($item['total_revenue'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state-pr">
                <i class="fas fa-capsules"></i>
                <p>No OTC item data found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="table-container-pr animate-fade-in-up" style="animation-delay:0.4s;">
        <div class="card-header-pr">
            <h3 class="card-title-pr">
                <i class="fas fa-prescription"></i>
                Recent Prescriptions (<?= count($recent_prescriptions) ?>)
            </h3>
            <a href="prescriptions.php?branch=<?= $branch_id ?>" class="card-action-pr">View All →</a>
        </div>
        <?php if (count($recent_prescriptions) > 0): ?>
            <div style="overflow-x:auto;">
                <table class="data-table-pr">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Patient</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Discount</th>
                            <th>Net</th>
                            <th>Method</th>
                            <th>Pharmacist</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_prescriptions as $sale): ?>
                            <tr>
                                <td class="font-mono-pr" style="font-size:0.7rem;"><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($sale['patient_name'] ?? 'Walk-in') ?></td>
                                <td style="text-align:center;"><?= $sale['item_count'] ?? 0 ?></td>
                                <td class="font-semibold-pr"><?= formatCurrency($sale['total_amount'] ?? 0) ?></td>
                                <td class="text-orange-pr"><?= formatCurrency($sale['discount_amount'] ?? 0) ?></td>
                                <td class="font-semibold-pr text-green-pr"><?= formatCurrency($sale['net_amount'] ?? 0) ?></td>
                                <td>
                                    <span class="badge-pr badge-<?= getPaymentMethodBadge($sale['payment_method'] ?? 'cash') ?>-pr" style="font-size:0.6rem;padding:2px 10px;">
                                        <i class="fas <?= getPaymentMethodIcon($sale['payment_method'] ?? 'cash') ?>"></i>
                                        <?= ucfirst($sale['payment_method'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($sale['pharmacist_name'] ?? 'N/A') ?></td>
                                <td style="font-size:0.7rem;"><?= date('M d, Y h:i A', strtotime($sale['created_at'] ?? 'now')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state-pr">
                <i class="fas fa-prescription"></i>
                <p>No prescription sales found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT OTC SALES -->
    <!-- ================================================================ -->
    <div class="table-container-pr animate-fade-in-up" style="animation-delay:0.45s;">
        <div class="card-header-pr">
            <h3 class="card-title-pr">
                <i class="fas fa-shopping-cart"></i>
                Recent OTC Sales (<?= count($recent_otc_sales) ?>)
            </h3>
            <a href="otc_sales.php?branch=<?= $branch_id ?>" class="card-action-pr">View All →</a>
        </div>
        <?php if (count($recent_otc_sales) > 0): ?>
            <div style="overflow-x:auto;">
                <table class="data-table-pr">
                    <thead>
                        <tr>
                            <th>Sale #</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Discount</th>
                            <th>Net</th>
                            <th>Method</th>
                            <th>Sold By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_otc_sales as $sale): ?>
                            <tr>
                                <td class="font-mono-pr" style="font-size:0.7rem;"><?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                <td style="text-align:center;"><?= $sale['item_count'] ?? 0 ?></td>
                                <td class="font-semibold-pr"><?= formatCurrency($sale['total_amount'] ?? 0) ?></td>
                                <td class="text-orange-pr"><?= formatCurrency($sale['discount_amount'] ?? 0) ?></td>
                                <td class="font-semibold-pr text-green-pr"><?= formatCurrency($sale['net_amount'] ?? 0) ?></td>
                                <td>
                                    <span class="badge-pr badge-<?= getPaymentMethodBadge($sale['payment_method'] ?? 'cash') ?>-pr" style="font-size:0.6rem;padding:2px 10px;">
                                        <i class="fas <?= getPaymentMethodIcon($sale['payment_method'] ?? 'cash') ?>"></i>
                                        <?= ucfirst($sale['payment_method'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($sale['sold_by_name'] ?? 'N/A') ?></td>
                                <td style="font-size:0.7rem;"><?= date('M d, Y h:i A', strtotime($sale['created_at'] ?? 'now')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state-pr">
                <i class="fas fa-shopping-cart"></i>
                <p>No OTC sales found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-pr">
        <p>
            <span class="footer-brand-pr">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Pharmacy Revenue - <?= htmlspecialchars($branch['name'] ?? 'Dodoma') ?>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // MONTHLY REVENUE CHART
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('monthlyRevenueChart');
        if (ctx && typeof Chart !== 'undefined') {
            var labels = <?= json_encode(array_column($monthly_revenue, 'month_name')) ?>;
            var prescribeData = <?= json_encode(array_map('floatval', array_column($monthly_revenue, 'prescribe_revenue'))) ?>;
            var otcData = <?= json_encode(array_map('floatval', array_column($monthly_revenue, 'otc_revenue'))) ?>;
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Prescription Revenue',
                            data: prescribeData,
                            backgroundColor: 'rgba(124, 58, 237, 0.7)',
                            borderColor: 'rgba(124, 58, 237, 1)',
                            borderWidth: 2,
                            borderRadius: 6,
                            barPercentage: 0.4
                        },
                        {
                            label: 'OTC Revenue',
                            data: otcData,
                            backgroundColor: 'rgba(217, 119, 6, 0.7)',
                            borderColor: 'rgba(217, 119, 6, 1)',
                            borderWidth: 2,
                            borderRadius: 6,
                            barPercentage: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: { size: 12, weight: '600' },
                                padding: 20,
                                usePointStyle: true,
                                pointStyle: 'rectRounded'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': TSh ' + context.raw.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'TSh ' + value.toLocaleString();
                                }
                            },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    });

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

    console.log('%c💊 Braick Dispensary - Pharmacy Revenue', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch['name'] ?? 'Dodoma') ?>', 'font-size:13px; color:#059669;');
    console.log('%c💵 Total Revenue: <?= formatCurrency($pharmacy_total) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c✅ No duplicate CSS/JS', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header (shared)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>