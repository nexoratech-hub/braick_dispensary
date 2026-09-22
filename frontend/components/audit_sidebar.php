<?php
// ================================================================
// FILE: frontend/components/audit_sidebar.php
// AUDIT - SIDEBAR (V12 - BRANCH LOCKED + STOCK MOVEMENT ADDED)
// ✅ Revenue = Payments (patient bills) + OTC (paid+partial) — LEO TU
// ✅ Branch ya aliye login TU - kila query ina branch_id
// ✅ Hakuna double counting
// ✅ Other Services badge = "All" (static)
// ✅ NEW: Stock Movement badge (medications sold, equipment used, stock in/out)
// ✅ Auto-refresh badge nyingine pekee (Revenue static kutoka PHP)
// ✅ Inareset 00:00 kila siku
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// AUDIT ROLE ONLY
if ($_SESSION['role'] !== 'audit') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin': header('Location: /dispensary_system/frontend/pages/admin/dashboard.php'); break;
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Audit User';
$user_role = $_SESSION['role'] ?? 'audit';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_is_online = $_SESSION['is_online'] ?? 1;

// ✅ AUDIT anaona branch yake TU - LAZIMA
$selected_branch_id = (int)$user_branch_id;

// ================================================================
// SESSION-BASED DATE RESET
// ================================================================
$today_date = date('Y-m-d');
if (!isset($_SESSION['audit_sidebar_last_date']) || $_SESSION['audit_sidebar_last_date'] !== $today_date) {
    $_SESSION['audit_sidebar_last_date'] = $today_date;
    unset($_SESSION['audit_cached_revenue_today']);
    unset($_SESSION['audit_cached_bills_today']);
    unset($_SESSION['audit_cached_otc_today']);
    unset($_SESSION['audit_cached_stock_today']);
}

// ================================================================
// DATABASE CONNECTION
// ================================================================
if (!isset($db) || $db === null) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/backend/config/database.php';
    try {
        $db = Database::getInstance()->getConnection();
    } catch (Exception $e) {
        error_log("Audit sidebar DB error: " . $e->getMessage());
        $db = null;
    }
}

// ================================================================
// ✅ BRANCH CONDITIONS - LAZIMA branch ya mtumiaji
// ================================================================
$branch_cond = " AND branch_id = ?";
$branch_params = [(int)$user_branch_id];

$branch_cond_p = " AND p.branch_id = ?";
$branch_params_p = [(int)$user_branch_id];

$branch_cond_o = " AND o.branch_id = ?";
$branch_params_o = [(int)$user_branch_id];

$branch_cond_sm = " AND sm.branch_id = ?";
$branch_params_sm = [(int)$user_branch_id];

// ================================================================
// GET BADGE DATA
// ================================================================
$total_revenue_today = 0;
$bills_today = 0;
$otc_today = 0;
$payments_today_count = 0;
$otc_today_count = 0;
$total_patients = 0;
$today_patients = 0;
$total_employees = 0;
$total_doctors = 0;
$total_inventory_items = 0;
$low_stock_count = 0;
$total_audit_logs = 0;
$today_audit_logs = 0;
$total_branches = 0;
$total_lab_tests = 0;

// ✅ STOCK MOVEMENT COUNTS (LEO TU)
$stock_movements_today = 0;
$medications_sold_today = 0;
$equipment_used_today = 0;
$stock_added_today = 0;
$stock_removed_today = 0;

if ($db !== null) {
    
    // ============================================================
    // ✅ REVENUE: PAYMENTS (LEO TU) - ALIGNED WITH DASHBOARD
    // ✅ patient_id NOT NULL + visit_id NOT NULL + NOT BILL-OTC-%
    // ✅ LAZIMA branch ya mtumiaji
    // ============================================================
    try {
        $sql = "SELECT COALESCE(SUM(p.amount), 0) as total, COUNT(*) as count 
                FROM payments p
                INNER JOIN bills b ON p.bill_id = b.id
                WHERE p.bill_id IS NOT NULL
                AND b.patient_id IS NOT NULL
                AND b.visit_id IS NOT NULL
                AND b.bill_number NOT LIKE 'BILL-OTC-%'
                AND b.branch_id = ?
                AND DATE(p.received_at) = CURDATE()";
        $stmt = $db->prepare($sql);
        $stmt->execute([(int)$user_branch_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $bills_today = (float)($data['total'] ?? 0);
        $payments_today_count = (int)($data['count'] ?? 0);
    } catch (Exception $e) {
        error_log("Payments today error: " . $e->getMessage());
        $bills_today = 0;
        $payments_today_count = 0;
    }
    
    // ============================================================
    // ✅ OTC REVENUE (LEO TU) - ALIGNED WITH DASHBOARD
    // ✅ paid + partial (SIYO paid pekee)
    // ✅ LAZIMA branch ya mtumiaji
    // ============================================================
    try {
        $sql = "SELECT COALESCE(SUM(o.total_amount), 0) as total, COUNT(*) as count 
                FROM otc_sales o
                WHERE o.payment_status IN ('paid', 'partial')
                AND DATE(o.created_at) = CURDATE()
                $branch_cond_o";
        $stmt = $db->prepare($sql);
        $stmt->execute($branch_params_o);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $otc_today = (float)($data['total'] ?? 0);
        $otc_today_count = (int)($data['count'] ?? 0);
    } catch (Exception $e) {
        error_log("OTC today error: " . $e->getMessage());
        $otc_today = 0;
        $otc_today_count = 0;
    }
    
    $total_revenue_today = $bills_today + $otc_today;
    
    // ============================================================
    // PATIENTS - branch ya mtumiaji
    // ============================================================
    try {
        $sql = "SELECT COUNT(*) as count FROM patients WHERE 1=1" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $total_patients = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    try {
        $sql = "SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) = CURDATE()" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $today_patients = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    // ============================================================
    // EMPLOYEES - branch ya mtumiaji
    // ============================================================
    try {
        $sql = "SELECT COUNT(*) as count FROM users WHERE role NOT IN ('admin', 'audit') AND status = 'active'" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $total_employees = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    try {
        $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $total_doctors = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    // ============================================================
    // INVENTORY - branch ya mtumiaji
    // ============================================================
    try {
        $sql = "SELECT COUNT(*) as count FROM medications_inventory WHERE status = 'active'" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $total_inventory_items = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    try {
        $sql = "SELECT COUNT(*) as count FROM medications_inventory WHERE status = 'active' AND quantity <= 10" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $low_stock_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    // ============================================================
    // ✅ STOCK MOVEMENTS (LEO TU) - branch ya mtumiaji
    // Inahesabu: medications sold, equipment used, stock added, stock removed
    // ============================================================
    try {
        $check_sm = $db->query("SHOW TABLES LIKE 'stock_movements'");
        $has_stock_movements = ($check_sm && $check_sm->rowCount() > 0);
        
        if ($has_stock_movements) {
            // Total movements zote leo
            $sql = "SELECT COUNT(*) as count 
                    FROM stock_movements sm
                    WHERE DATE(sm.created_at) = CURDATE()
                    $branch_cond_sm";
            $stmt = $db->prepare($sql);
            $stmt->execute($branch_params_sm);
            $stock_movements_today = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            
            // Medications sold
            try {
                $sql = "SELECT COUNT(*) as count 
                        FROM stock_movements sm
                        WHERE DATE(sm.created_at) = CURDATE()
                        AND sm.movement_type IN ('sale', 'dispensed', 'sold', 'otc_sale')
                        $branch_cond_sm";
                $stmt = $db->prepare($sql);
                $stmt->execute($branch_params_sm);
                $medications_sold_today = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            } catch (Exception $e) {}
            
            // Equipment used for test
            try {
                $sql = "SELECT COUNT(*) as count 
                        FROM stock_movements sm
                        WHERE DATE(sm.created_at) = CURDATE()
                        AND sm.movement_type IN ('equipment_use', 'test_use', 'equipment', 'lab_use')
                        $branch_cond_sm";
                $stmt = $db->prepare($sql);
                $stmt->execute($branch_params_sm);
                $equipment_used_today = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            } catch (Exception $e) {}
            
            // Stock added
            try {
                $sql = "SELECT COUNT(*) as count 
                        FROM stock_movements sm
                        WHERE DATE(sm.created_at) = CURDATE()
                        AND sm.movement_type IN ('in', 'added', 'purchase', 'restock', 'received')
                        $branch_cond_sm";
                $stmt = $db->prepare($sql);
                $stmt->execute($branch_params_sm);
                $stock_added_today = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            } catch (Exception $e) {}
            
            // Stock removed
            try {
                $sql = "SELECT COUNT(*) as count 
                        FROM stock_movements sm
                        WHERE DATE(sm.created_at) = CURDATE()
                        AND sm.movement_type IN ('out', 'removed', 'damaged', 'expired', 'adjustment', 'transfer')
                        $branch_cond_sm";
                $stmt = $db->prepare($sql);
                $stmt->execute($branch_params_sm);
                $stock_removed_today = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            } catch (Exception $e) {}
        } else {
            // Fallback: bill_items + otc_sale_items kwa branch ya mtumiaji
            try {
                $sql = "SELECT COUNT(*) as count 
                        FROM bill_items bi
                        INNER JOIN bills b ON bi.bill_id = b.id
                        WHERE DATE(b.created_at) = CURDATE()
                        AND bi.item_type IN ('medication', 'medicine', 'drug')
                        AND b.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([(int)$user_branch_id]);
                $medications_sold_today = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            } catch (Exception $e) {}
            
            try {
                $sql = "SELECT COUNT(*) as count 
                        FROM otc_sale_items osi
                        INNER JOIN otc_sales o ON osi.otc_sale_id = o.id
                        WHERE DATE(o.created_at) = CURDATE()
                        AND o.payment_status IN ('paid', 'partial')
                        AND o.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([(int)$user_branch_id]);
                $medications_sold_today += (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            } catch (Exception $e) {}
            
            $stock_movements_today = $medications_sold_today + $equipment_used_today + $stock_added_today + $stock_removed_today;
        }
    } catch (Exception $e) {
        error_log("Stock movements error: " . $e->getMessage());
    }
    
    // ============================================================
    // LAB TESTS - branch ya mtumiaji
    // ============================================================
    try {
        $sql = "SELECT COUNT(*) as count FROM lab_tests WHERE status != 'cancelled'" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $total_lab_tests = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {
        try {
            $sql = "SELECT COUNT(*) as count FROM bill_items WHERE item_type = 'lab_test'" . $branch_cond;
            $stmt = $db->prepare($sql); $stmt->execute($branch_params);
            $total_lab_tests = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        } catch (Exception $e2) {}
    }
    
    // ============================================================
    // AUDIT LOGS - branch ya mtumiaji
    // ============================================================
    try {
        $sql = "SELECT COUNT(*) as count FROM activity_logs WHERE 1=1" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $total_audit_logs = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    try {
        $sql = "SELECT COUNT(*) as count FROM activity_logs WHERE DATE(created_at) = CURDATE()" . $branch_cond;
        $stmt = $db->prepare($sql); $stmt->execute($branch_params);
        $today_audit_logs = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
    
    // ============================================================
    // BRANCHES (kwa display tu)
    // ============================================================
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
        $total_branches = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Exception $e) {}
}

$current_page = basename($_SERVER['PHP_SELF']);

function isActive($page) {
    global $current_page;
    return $page === $current_page ? 'active' : '';
}

function isAuditPage($pages) {
    global $current_page;
    return in_array($current_page, $pages) ? 'active' : '';
}

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ================================================================
   AUDIT SIDEBAR STYLES (V12)
   ================================================================ */

.sidebar {
    position: fixed; 
    top: 0; left: 0; bottom: 0;
    width: 270px; 
    background: linear-gradient(180deg, #0B4EA8 0%, #0A3D7A 100%);
    color: white;
    z-index: 50; 
    overflow-y: auto;
    overflow-x: hidden;
    transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    transform: translateX(-100%);
    box-shadow: 4px 0 20px rgba(0,0,0,0.15);
    scroll-behavior: smooth;
}

[data-theme="dark"] .sidebar {
    background: linear-gradient(180deg, #0A3D7A 0%, #082F5E 100%);
    box-shadow: 4px 0 30px rgba(0,0,0,0.5);
}

.sidebar.open { transform: translateX(0) !important; }

.sidebar::-webkit-scrollbar { width: 5px; }
.sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
.sidebar::-webkit-scrollbar-thumb { background: #0AA84F; border-radius: 10px; }
.sidebar::-webkit-scrollbar-thumb:hover { background: #34D399; }

/* BRAND */
.sidebar-brand {
    padding: 18px 16px 14px;
    border-bottom: 2px solid rgba(255,255,255,0.08);
    background: rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 5;
    backdrop-filter: blur(10px);
}

.sidebar-brand .logo {
    width: 42px; 
    height: 42px; 
    border-radius: 10px;
    object-fit: cover; 
    background: white; 
    padding: 4px;
    border: 2px solid rgba(255,255,255,0.15);
    transition: transform 0.3s ease;
}

.sidebar-brand .logo:hover { transform: rotate(-5deg) scale(1.05); }

.sidebar-brand .brand-text { 
    color: white; 
    font-weight: 700; 
    font-size: 0.95rem; 
    line-height: 1.2; 
    letter-spacing: 0.5px;
}

.sidebar-brand .brand-sub { 
    color: #9EC5FE; 
    font-size: 0.65rem; 
    font-weight: 500;
    letter-spacing: 0.3px;
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 2px;
}

.audit-role-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #0AA84F;
    color: white;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 0.58rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    box-shadow: 0 2px 8px rgba(10, 168, 79, 0.4);
}

.audit-role-badge.view-only-role {
    background: linear-gradient(135deg, #F59E0B, #D97706);
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.4);
}

/* NAVIGATION */
.sidebar-nav { padding: 10px 8px 20px; }

.sidebar-nav .nav-label {
    font-size: 0.5rem; 
    text-transform: uppercase;
    letter-spacing: 0.08em; 
    color: #6EA8FE;
    padding: 8px 10px 4px; 
    margin: 8px 0 2px; 
    font-weight: 700; 
    opacity: 0.8;
}

.sidebar-nav .nav-label:first-of-type { margin-top: 0; }
.sidebar-nav .nav-label .label-icon { margin-right: 4px; }

.sidebar-link {
    display: flex; 
    align-items: center; 
    gap: 10px;
    padding: 8px 12px; 
    border-radius: 8px;
    color: #D2E3FC; 
    text-decoration: none;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 0.8rem; 
    font-weight: 500;
    margin: 1px 0; 
    background: transparent;
    cursor: pointer; 
    border: none;
    width: 100%; 
    text-align: left;
    position: relative;
}

.sidebar-link:hover {
    background: rgba(10, 168, 79, 0.4);
    color: white;
    box-shadow: 0 4px 12px rgba(10, 168, 79, 0.2);
    transform: translateX(4px);
}

.sidebar-link.active {
    background: rgba(10, 168, 79, 0.5);
    color: white;
    box-shadow: 0 4px 12px rgba(10, 168, 79, 0.3);
}

.sidebar-link.active::before {
    content: '';
    position: absolute;
    left: 0; 
    top: 15%; 
    bottom: 15%;
    width: 4px; 
    background: #0AA84F;
    border-radius: 0 4px 4px 0;
    box-shadow: 0 0 12px rgba(10, 168, 79, 0.5);
}

.sidebar-link i { 
    width: 20px; 
    text-align: center; 
    font-size: 0.9rem; 
    flex-shrink: 0; 
    opacity: 0.8;
    font-family: "Font Awesome 6 Free" !important;
    font-weight: 900 !important;
}

.sidebar-link:hover i,
.sidebar-link.active i { opacity: 1; }

.sidebar-link .link-text {
    flex: 1; 
    white-space: nowrap;
    overflow: hidden; 
    text-overflow: ellipsis;
}

/* BADGES */
.sidebar-link .badge {
    margin-left: auto;
    background: #0B5ED7 !important;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 700;
    color: #FFFFFF !important;
    transition: all 0.3s ease;
    flex-shrink: 0;
    min-width: 24px;
    text-align: center;
    border: 1.5px solid rgba(255,255,255,0.25);
    box-shadow: 0 2px 6px rgba(11, 94, 215, 0.4);
    line-height: 1.4;
    font-family: 'JetBrains Mono', 'Courier New', monospace !important;
    font-variant-numeric: tabular-nums;
}

.sidebar-link .badge.badge-new {
    background: #10B981 !important;
    color: #FFFFFF !important;
    border-color: rgba(255,255,255,0.4) !important;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.6);
    animation: pulse-new 2s infinite;
    font-size: 0.55rem;
    letter-spacing: 0.05em;
    font-weight: 800;
}

@keyframes pulse-new {
    0%, 100% { transform: scale(1); box-shadow: 0 2px 8px rgba(16, 185, 129, 0.6); }
    50% { transform: scale(1.08); box-shadow: 0 3px 14px rgba(16, 185, 129, 0.9); }
}

.sidebar-link .badge.badge-warning {
    background: #F59E0B !important;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.6);
}

.sidebar-link .badge.badge-revenue {
    background: linear-gradient(135deg, #10B981, #059669) !important;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.6);
    font-size: 0.58rem;
    padding: 2px 8px;
    font-weight: 700;
}

.sidebar-link .badge.badge-lab {
    background: linear-gradient(135deg, #7C3AED, #6D28D9) !important;
    box-shadow: 0 2px 8px rgba(124, 58, 237, 0.6);
    font-size: 0.58rem;
    padding: 2px 8px;
    font-weight: 700;
}

.sidebar-link .badge.badge-services {
    background: linear-gradient(135deg, #0891B2, #0E7490) !important;
    box-shadow: 0 2px 8px rgba(8, 145, 178, 0.6);
    font-size: 0.58rem;
    padding: 2px 8px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-family: 'Inter', sans-serif !important;
}

/* ✅ NEW: STOCK MOVEMENT BADGE */
.sidebar-link .badge.badge-stock {
    background: linear-gradient(135deg, #F97316, #EA580C) !important;
    box-shadow: 0 2px 8px rgba(249, 115, 22, 0.6);
    font-size: 0.58rem;
    padding: 2px 8px;
    font-weight: 700;
}

.sidebar-link:hover .badge {
    background: #1A73E8 !important;
    transform: scale(1.08);
    box-shadow: 0 3px 10px rgba(11, 94, 215, 0.6);
}

.sidebar-link.active .badge {
    background: #1A73E8 !important;
    color: #FFFFFF !important;
    border-color: rgba(255,255,255,0.4) !important;
}

/* LOGOUT */
.sidebar-link.logout-link {
    border-top: 2px solid rgba(255,255,255,0.06);
    padding-top: 10px; 
    margin-top: 4px;
    color: #FCA5A5;
}

.sidebar-link.logout-link:hover {
    background: #DC2626; 
    color: white;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
    transform: translateX(4px);
}

.sidebar-link.logout-link i { opacity: 1; }

/* STATUS FOOTER */
.sidebar-status {
    padding: 10px 16px;
    border-top: 2px solid rgba(255,255,255,0.06);
    display: flex; 
    align-items: center; 
    gap: 10px;
    background: rgba(0,0,0,0.1);
    position: sticky; 
    bottom: 0;
    backdrop-filter: blur(10px);
}

.sidebar-status .status-dot {
    width: 8px; 
    height: 8px; 
    border-radius: 50%;
    display: inline-block;
}

.sidebar-status .status-dot.online {
    background: #34D399;
    box-shadow: 0 0 8px rgba(52, 211, 153, 0.3);
    animation: pulse-dot 1.5s infinite;
}

.sidebar-status .status-dot.offline { background: #94A3B8; }

.sidebar-status .status-text {
    font-size: 0.65rem; 
    color: #D2E3FC; 
    font-weight: 500;
}

.sidebar-status .update-time {
    font-size: 0.5rem; 
    color: #6EA8FE;
    margin-left: auto; 
    display: flex; 
    align-items: center; 
    gap: 4px;
}

.sidebar-live-indicator {
    display: inline-flex; 
    align-items: center; 
    gap: 4px;
    font-size: 0.5rem; 
    color: #34D399;
    font-weight: 500;
}

.sidebar-live-indicator .dot {
    width: 6px; 
    height: 6px; 
    border-radius: 50%;
    background: #34D399; 
    animation: pulse-dot 1.5s infinite;
    display: inline-block;
}

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.3; transform: scale(0.8); }
}

/* OVERLAY */
#sidebarOverlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 45; 
    display: none;
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    transition: opacity 0.3s ease;
}

#sidebarOverlay.active { display: block !important; }

/* TOGGLE BUTTON */
#sidebarToggle {
    display: none;
    position: fixed !important;
    top: 16px !important;
    left: 16px !important;
    z-index: 2147483647 !important;
    width: 48px !important;
    height: 48px !important;
    border-radius: 12px;
    background: linear-gradient(135deg, #0B4EA8 0%, #0A3D7A 100%);
    color: white !important;
    border: 2px solid rgba(255,255,255,0.4);
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(11, 78, 168, 0.6);
    transition: all 0.3s ease;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    padding: 0;
    margin: 0;
    outline: none;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
    pointer-events: auto !important;
    visibility: visible !important;
    opacity: 1 !important;
}

#sidebarToggle:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(11, 78, 168, 0.8);
    background: linear-gradient(135deg, #0AA84F 0%, #0B4EA8 100%);
}

#sidebarToggle:active { transform: scale(0.92); }

[data-theme="dark"] #sidebarToggle {
    background: linear-gradient(135deg, #0A3D7A 0%, #082F5E 100%);
    border-color: rgba(255,255,255,0.5);
}

#sidebarToggle i {
    font-family: "Font Awesome 6 Free" !important;
    font-weight: 900 !important;
    pointer-events: none !important;
    font-size: 1.3rem;
    line-height: 1;
    color: white;
}

@media (max-width: 1024px) {
    #sidebarToggle { display: flex !important; }
}

@media (min-width: 1025px) {
    #sidebarToggle { display: none !important; }
}

/* RESPONSIVE */
@media (max-width: 1024px) {
    .sidebar {
        width: 280px;
        transform: translateX(-100%);
        z-index: 99999 !important;
        border-radius: 0 12px 12px 0;
        box-shadow: 2px 0 10px rgba(0,0,0,0.15);
        will-change: transform;
        isolation: isolate;
    }
    
    .sidebar.open {
        transform: translateX(0) !important;
        box-shadow: 4px 0 20px rgba(0,0,0,0.25);
    }
    
    #sidebarOverlay {
        display: none;
        z-index: 99998 !important;
    }
    
    #sidebarOverlay.active { display: block !important; }
    
    .top-nav { z-index: 40 !important; }
    
    .sidebar.open, .sidebar.open * { pointer-events: auto !important; }
    .sidebar-link { pointer-events: auto !important; cursor: pointer !important; }
    
    .sidebar-brand { padding: 14px 14px 10px; }
    .sidebar-brand .logo { width: 36px; height: 36px; }
    .sidebar-brand .brand-text { font-size: 0.85rem; }
    .sidebar-link { padding: 7px 10px; font-size: 0.75rem; gap: 8px; }
    .sidebar-link i { width: 18px; font-size: 0.8rem; }
    .sidebar-link .badge { font-size: 0.55rem; padding: 1px 7px; }
    .sidebar-nav .nav-label { font-size: 0.45rem; }
    .sidebar-status { padding: 8px 14px; }
}

@media (min-width: 1025px) {
    .sidebar {
        transform: translateX(0) !important;
        z-index: 50;
        box-shadow: 4px 0 20px rgba(0,0,0,0.08);
    }
    #sidebarOverlay { display: none !important; }
}

@media (max-width: 768px) {
    .sidebar { width: 300px; border-radius: 0 16px 16px 0; }
    .sidebar-link { padding: 6px 10px; font-size: 0.7rem; gap: 8px; }
    .sidebar-link i { width: 16px; font-size: 0.75rem; }
    .sidebar-link .badge { font-size: 0.5rem; padding: 1px 6px; }
    .sidebar-nav .nav-label { font-size: 0.4rem; }
    #sidebarToggle { width: 46px !important; height: 46px !important; font-size: 1.2rem; top: 14px !important; left: 14px !important; }
}

@media (max-width: 480px) {
    .sidebar {
        width: 100%; 
        max-width: 320px;
        border-radius: 0 20px 20px 0;
    }
    .sidebar-link { padding: 5px 8px; font-size: 0.65rem; gap: 6px; }
    .sidebar-link i { width: 14px; font-size: 0.7rem; }
    .sidebar-link .badge { font-size: 0.45rem; padding: 1px 5px; min-width: 16px; }
    .sidebar-nav .nav-label { font-size: 0.4rem; padding: 0 8px; }
    #sidebarToggle { width: 44px !important; height: 44px !important; font-size: 1.15rem; top: 12px !important; left: 12px !important; }
}

@media (max-width: 1024px) {
    body.sidebar-open {
        overflow: hidden !important;
        position: fixed !important;
        width: 100% !important;
        height: 100% !important;
    }
}

@media print {
    .sidebar { display: none !important; }
    #sidebarOverlay { display: none !important; }
    #sidebarToggle { display: none !important; }
}

.flex { display: flex; }
.items-center { align-items: center; }
.gap-3 { gap: 12px; }
.truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>

<div id="sidebarOverlay"></div>

<button id="sidebarToggle" type="button" aria-label="Toggle Sidebar" title="Toggle Sidebar" onclick="window.__toggleAuditSidebar && window.__toggleAuditSidebar(event)">
    <i class="fas fa-bars"></i>
</button>

<aside class="sidebar" id="sidebar" role="navigation" aria-label="Audit Sidebar">
    
    <!-- BRAND -->
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div class="truncate">
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">
                    <i class="fas fa-search"></i>
                    Audit & Reports
                </p>
            </div>
        </div>
        <div style="margin-top:6px;padding:0 2px;display:flex;gap:5px;flex-wrap:wrap;">
            <span class="audit-role-badge">
                <i class="fas fa-shield-alt"></i> AUDIT
            </span>
            <span class="audit-role-badge view-only-role">
                <i class="fas fa-eye"></i> VIEW ONLY
            </span>
        </div>
        <!-- ✅ BRANCH TAG -->
        <div style="margin-top:6px;padding:0 2px;">
            <span style="display:inline-flex;align-items:center;gap:4px;background:rgba(255,255,255,0.12);color:white;padding:3px 10px;border-radius:6px;font-size:0.6rem;font-weight:600;border:1px solid rgba(255,255,255,0.15);">
                <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
            </span>
        </div>
    </div>
    
    <!-- NAVIGATION -->
    <nav class="sidebar-nav">
        
        <div class="nav-label"><span class="label-icon">📋</span> Main Menu</div>
        
        <a href="/dispensary_system/frontend/pages/audit/dashboard.php" 
           class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i>
            <span class="link-text">Dashboard</span>
        </a>
        
        <div class="nav-label"><span class="label-icon">📊</span> Reports</div>
        
        <!-- ✅ REVENUE BADGE - INAONYESHA MARA MOJA kutoka PHP (branch ya mtumiaji) -->
        <a href="/dispensary_system/frontend/pages/audit/revenue.php?quick=today" 
           class="sidebar-link <?= isActive('revenue.php') || isAuditPage(['revenue_details.php']) ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span class="link-text">Revenue</span>
            <span class="badge badge-revenue" id="badgeRevenue">TSh <?= number_format($total_revenue_today, 0) ?></span>
        </a>
        
        <!-- INVENTORY -->
        <a href="/dispensary_system/frontend/pages/audit/inventory.php" 
           class="sidebar-link <?= isActive('inventory.php') || isAuditPage(['inventory_details.php']) ? 'active' : '' ?>">
            <i class="fas fa-pills"></i>
            <span class="link-text">Inventory</span>
            <span class="badge" id="badgeInventory"><?= $total_inventory_items ?></span>
            <?php if ($low_stock_count > 0): ?>
                <span class="badge badge-warning" id="badgeLowStock"><?= $low_stock_count ?></span>
            <?php endif; ?>
        </a>
        
        <!-- ✅ NEW: STOCK MOVEMENT -->
        <a href="/dispensary_system/frontend/pages/audit/stock_movement.php" 
           class="sidebar-link <?= isActive('stock_movement.php') || isAuditPage(['stock_movement_details.php', 'stock_in.php', 'stock_out.php', 'medication_movement.php', 'equipment_usage.php']) ? 'active' : '' ?>">
            <i class="fas fa-exchange-alt"></i>
            <span class="link-text">Stock Movement</span>
            <span class="badge badge-stock" id="badgeStockMovement"><?= number_format($stock_movements_today) ?></span>
            <?php if ($medications_sold_today > 0): ?>
                <span class="badge badge-new" id="badgeStockToday">+<?= $medications_sold_today ?></span>
            <?php endif; ?>
        </a>
        
        <!-- LAB TESTS -->
        <a href="/dispensary_system/frontend/pages/audit/lab_tests.php" 
           class="sidebar-link <?= isActive('lab_tests.php') || isAuditPage(['lab_test_details.php', 'view_lab_test.php', 'edit_lab_test.php']) ? 'active' : '' ?>">
            <i class="fas fa-flask"></i>
            <span class="link-text">Lab Tests</span>
            <span class="badge badge-lab" id="badgeLabTests"><?= number_format($total_lab_tests) ?></span>
        </a>
        
        <!-- OTHER SERVICES -->
        <a href="/dispensary_system/frontend/pages/audit/other_services.php" 
           class="sidebar-link <?= isActive('other_services.php') || isAuditPage(['other_service_details.php', 'view_service.php', 'edit_service.php', 'consultations.php', 'procedures.php', 'equipment_services.php']) ? 'active' : '' ?>">
            <i class="fas fa-concierge-bell"></i>
            <span class="link-text">Other Services</span>
            <span class="badge badge-services" id="badgeOtherServices">All</span>
        </a>
        
        <!-- PATIENTS -->
        <a href="/dispensary_system/frontend/pages/audit/patients.php" 
           class="sidebar-link <?= isActive('patients.php') || isAuditPage(['patient_details.php']) ? 'active' : '' ?>">
            <i class="fas fa-user-injured"></i>
            <span class="link-text">Patients</span>
            <span class="badge" id="badgePatients"><?= $total_patients ?></span>
            <?php if ($today_patients > 0): ?>
                <span class="badge badge-new" id="badgePatientsToday">+<?= $today_patients ?></span>
            <?php endif; ?>
        </a>
        
        <div class="nav-label"><span class="label-icon">👥</span> Performance</div>
        
        <a href="/dispensary_system/frontend/pages/audit/employees.php" 
           class="sidebar-link <?= isActive('employees.php') || isAuditPage(['employee_details.php']) ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span class="link-text">Employees</span>
            <span class="badge" id="badgeEmployees"><?= $total_employees ?></span>
        </a>
        
        <div class="nav-label"><span class="label-icon">🔍</span> Audit</div>
        
        <a href="/dispensary_system/frontend/pages/audit/audit_logs.php" 
           class="sidebar-link <?= isActive('audit_logs.php') || isAuditPage(['audit_log_details.php']) ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list"></i>
            <span class="link-text">Audit Logs</span>
            <span class="badge" id="badgeAuditLogs"><?= $total_audit_logs ?></span>
            <?php if ($today_audit_logs > 0): ?>
                <span class="badge badge-new" id="badgeTodayAudit">+<?= $today_audit_logs ?></span>
            <?php endif; ?>
        </a>
        
        <div class="nav-label"><span class="label-icon">👤</span> Account</div>
        
        <a href="/dispensary_system/frontend/pages/audit/profile.php" 
           class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i>
            <span class="link-text">Profile</span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/logout.php" 
           class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span class="link-text">Logout</span>
        </a>
        
    </nav>
    
    <!-- STATUS FOOTER -->
    <div class="sidebar-status">
        <span class="status-dot <?= $user_is_online ? 'online' : 'offline' ?>"></span>
        <span class="status-text"><?= $user_is_online ? 'Online' : 'Offline' ?></span>
        <span class="update-time">
            <span class="sidebar-live-indicator">
                <span class="dot"></span> Live
            </span>
        </span>
    </div>
</aside>

<script>
// ================================================================
// SIDEBAR TOGGLE V5 - GUARANTEED TO WORK
// ================================================================
(function() {
    'use strict';
    
    window.__toggleAuditSidebar = function(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        var toggle = document.getElementById('sidebarToggle');
        
        if (!sidebar) return false;
        
        var isOpen = sidebar.classList.contains('open');
        
        if (isOpen) {
            sidebar.classList.remove('open');
            if (overlay) {
                overlay.classList.remove('active');
                overlay.style.display = 'none';
            }
            document.body.classList.remove('sidebar-open');
            document.body.style.overflow = '';
            
            if (toggle) {
                var icon = toggle.querySelector('i');
                if (icon) icon.className = 'fas fa-bars';
            }
        } else {
            sidebar.classList.add('open');
            if (overlay) {
                overlay.classList.add('active');
                overlay.style.display = 'block';
            }
            document.body.classList.add('sidebar-open');
            document.body.style.overflow = 'hidden';
            
            if (toggle) {
                var icon = toggle.querySelector('i');
                if (icon) icon.className = 'fas fa-times';
            }
        }
        
        return false;
    };
    
    window.toggleSidebar = window.__toggleAuditSidebar;
    window.openSidebar = function() {
        var sidebar = document.getElementById('sidebar');
        if (sidebar && !sidebar.classList.contains('open')) {
            window.__toggleAuditSidebar();
        }
    };
    window.closeSidebar = function() {
        var sidebar = document.getElementById('sidebar');
        if (sidebar && sidebar.classList.contains('open')) {
            window.__toggleAuditSidebar();
        }
    };
    
    function attachListeners() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        var toggle = document.getElementById('sidebarToggle');
        
        if (!sidebar || !toggle) return false;
        
        var freshToggle = toggle.cloneNode(true);
        freshToggle.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            window.__toggleAuditSidebar(e);
            return false;
        };
        toggle.parentNode.replaceChild(freshToggle, toggle);
        toggle = freshToggle;
        
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (this.__busy) return;
            this.__busy = true;
            setTimeout(() => { toggle.__busy = false; }, 250);
            window.__toggleAuditSidebar(e);
        }, false);
        
        toggle.addEventListener('touchend', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (this.__busy) return;
            this.__busy = true;
            setTimeout(() => { toggle.__busy = false; }, 250);
            window.__toggleAuditSidebar(e);
        }, { passive: false });
        
        if (overlay) {
            overlay.onclick = function(e) {
                if (e.target === overlay) {
                    window.closeSidebar();
                }
            };
        }
        
        return true;
    }
    
    document.addEventListener('click', function(e) {
        var target = e.target;
        while (target && target !== document) {
            if (target.id === 'sidebarToggle') {
                e.preventDefault();
                e.stopPropagation();
                window.__toggleAuditSidebar(e);
                return false;
            }
            target = target.parentNode;
        }
    }, true);
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var sidebar = document.getElementById('sidebar');
            if (sidebar && sidebar.classList.contains('open')) {
                window.closeSidebar();
            }
        }
    });
    
    var resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 1024) {
                var sidebar = document.getElementById('sidebar');
                if (sidebar && sidebar.classList.contains('open')) {
                    window.closeSidebar();
                }
            }
        }, 200);
    });
    
    function attachLinkHandlers() {
        document.querySelectorAll('.sidebar-link').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1024) {
                    setTimeout(window.closeSidebar, 150);
                }
            });
        });
    }
    
    function init() {
        var ok = attachListeners();
        attachLinkHandlers();
        
        if (!ok) {
            setTimeout(init, 200);
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    window.addEventListener('load', init);
    setTimeout(init, 500);
    setTimeout(init, 1500);
    
    window.initAuditSidebar = init;
})();

// ================================================================
// ✅ AUTO REFRESH SIDEBAR BADGE KILA DAKIKA 1
// ✅ Revenue badge HAIRefresh - inatumia PHP value (SAHIHI)
// ✅ Badge nyingine zina-refresh kutoka API
// ✅ Branch ya mtumiaji TU
// ✅ NEW: Stock Movement badge ina-refresh
// ================================================================
(function() {
    var lastDate = new Date().toDateString();
    var userBranchId = <?= (int)$user_branch_id ?>;
    
    function refreshBadges() {
        var currentDate = new Date().toDateString();
        if (currentDate !== lastDate) {
            lastDate = currentDate;
            window.location.reload();
            return;
        }
        
        var url = '/dispensary_system/frontend/api/get_sidebar_badges.php?branch=' + userBranchId + '&role=audit&_t=' + Date.now();
        
        fetch(url, { 
            cache: 'no-store',
            headers: { 
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache'
            }
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    // ❌ REVENUE BADGE - IMEONDOLWA (inatumia PHP value)
                    
                    var invBadge = document.getElementById('badgeInventory');
                    if (invBadge && data.total_inventory_items !== undefined) {
                        invBadge.textContent = data.total_inventory_items;
                    }
                    
                    // ✅ STOCK MOVEMENT BADGE UPDATE
                    var stockBadge = document.getElementById('badgeStockMovement');
                    if (stockBadge && data.stock_movements_today !== undefined) {
                        stockBadge.textContent = Number(data.stock_movements_today).toLocaleString();
                    }
                    
                    var labBadge = document.getElementById('badgeLabTests');
                    if (labBadge && data.total_lab_tests !== undefined) {
                        labBadge.textContent = Number(data.total_lab_tests).toLocaleString();
                    }
                    
                    // OTHER SERVICES BADGE = "All" - STATIC
                    
                    var patBadge = document.getElementById('badgePatients');
                    if (patBadge && data.total_patients !== undefined) {
                        patBadge.textContent = data.total_patients;
                    }
                    
                    var empBadge = document.getElementById('badgeEmployees');
                    if (empBadge && data.total_employees !== undefined) {
                        empBadge.textContent = data.total_employees;
                    }
                    
                    var logBadge = document.getElementById('badgeAuditLogs');
                    if (logBadge && data.total_audit_logs !== undefined) {
                        logBadge.textContent = data.total_audit_logs;
                    }
                }
            })
            .catch(function(err) {});
    }
    
    setInterval(refreshBadges, 60000);
    setTimeout(refreshBadges, 5000);
    
    console.log('%c🔄 Audit sidebar badge auto-refresh active', 'color:#10B981;font-size:12px;');
    console.log('%c📌 Revenue badge = static (PHP) — hairefresh kutoka API', 'color:#F59E0B;font-size:11px;');
    console.log('%c📌 Other Services badge = "All" (STATIC)', 'color:#0891B2;font-size:11px;');
    console.log('%c📦 Stock Movement badge inaonyesha movements za leo', 'color:#F97316;font-size:11px;');
})();

console.log('%c🔍 Audit Sidebar V12 - BRANCH LOCKED + STOCK MOVEMENT', 'font-size:16px; font-weight:bold; color:#0B4EA8;');
console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?> (ID: <?= $selected_branch_id ?>)', 'font-size:13px; color:#10B981; font-weight:bold;');
console.log('%c✅ Revenue badge = static (PHP) — MARA MOJA', 'font-size:13px; color:#F59E0B; font-weight:bold;');
console.log('%c✅ Payments query: patient_id + visit_id + NOT BILL-OTC-% + branch_id', 'font-size:13px; color:#10B981; font-weight:bold;');
console.log('%c✅ OTC query: paid + partial + branch_id', 'font-size:13px; color:#10B981; font-weight:bold;');
console.log('%c✅ Other Services Badge = "All" (STATIC)', 'font-size:13px; color:#0891B2; font-weight:bold;');
console.log('%c✅ Stock Movement = Meds Sold + Equipment Used + Stock In/Out', 'font-size:13px; color:#F97316; font-weight:bold;');
console.log('%c💰 Payments Today (Branch): TSh <?= number_format($bills_today, 0) ?> (<?= $payments_today_count ?> payments)', 'font-size:13px; color:#0B5ED7;');
console.log('%c💊 OTC Today (Branch): TSh <?= number_format($otc_today, 0) ?> (<?= $otc_today_count ?> sales)', 'font-size:13px; color:#0891B2;');
console.log('%c📊 TOTAL TODAY: TSh <?= number_format($total_revenue_today, 0) ?>', 'font-size:14px; color:#10B981; font-weight:bold;');
console.log('%c📦 Stock Movements Today: <?= number_format($stock_movements_today) ?>', 'font-size:13px; color:#F97316; font-weight:bold;');
console.log('%c   💊 Medications Sold: <?= number_format($medications_sold_today) ?>', 'font-size:12px; color:#EA580C;');
console.log('%c   🔬 Equipment Used: <?= number_format($equipment_used_today) ?>', 'font-size:12px; color:#EA580C;');
console.log('%c   📥 Stock Added: <?= number_format($stock_added_today) ?>', 'font-size:12px; color:#EA580C;');
console.log('%c   📤 Stock Removed: <?= number_format($stock_removed_today) ?>', 'font-size:12px; color:#EA580C;');
</script>