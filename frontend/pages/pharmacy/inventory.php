<?php
// ================================================================
// FILE: frontend/pages/pharmacy/inventory.php
// PHARMACY - MEDICINE INVENTORY (VIEW + ADD)
// ================================================================
// FIXED: 
// 1. Out of stock items removed from main inventory
// 2. Low Stock card includes out of stock
// 3. No separate Out of Stock card
// 4. PRICE FROM 1 TSh (not 100)
// 5. Auto-assign to user's branch
// ================================================================

session_start();

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// SESSION - Default to pharm.peter
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    $_SESSION['user_id'] = 5;
    $_SESSION['full_name'] = 'Peter Ngalula';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'pharm.peter';
    $_SESSION['email'] = 'peter@braick.com';
    $_SESSION['phone'] = '+255 700 000 004';
    $_SESSION['is_admin'] = false;
    $_SESSION['profile_pic'] = '';
}

$user_id = $_SESSION['user_id'] ?? 5;
$user_full_name = $_SESSION['full_name'] ?? 'Peter Ngalula';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

$db = getDB();

// ================================================================
// GET CATEGORIES FOR DROPDOWN
// ================================================================
$categories = [];
$stmt = $db->query("SELECT DISTINCT category FROM medications_inventory WHERE category IS NOT NULL AND category != '' ORDER BY category");
$categories = $stmt->fetchAll();

// ================================================================
// PRE-DEFINED CATEGORIES FOR DROPDOWN
// ================================================================
$predefined_categories = [
    'Antibiotics',
    'Painkillers',
    'Antipyretics',
    'Antihistamines',
    'Antacids',
    'Antivirals',
    'Antifungals',
    'Antimalarials',
    'Vitamins',
    'Supplements',
    'Respiratory',
    'Cardiovascular',
    'Diabetes',
    'Hypertension',
    'Dermatological',
    'Eye Drops',
    'Ear Drops',
    'Injectables',
    'IV Fluids',
    'Other'
];

// ================================================================
// PROCESS POST REQUESTS - ADD MEDICINE
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ================================================================
    // ADD MEDICINE - FIXED: Price from 1 TSh, Auto branch
    // ================================================================
    if ($action === 'add_medicine') {
        $medication_name = trim($_POST['medication_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        if (empty($category) && !empty($_POST['category_manual'])) {
            $category = trim($_POST['category_manual']);
        }
        $unit = trim($_POST['unit'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $reorder_level = (int)($_POST['reorder_level'] ?? 10);
        $unit_cost = (float)($_POST['unit_cost'] ?? 0);
        $selling_price = (float)($_POST['selling_price'] ?? 0);
        $supplier = trim($_POST['supplier'] ?? '');
        $expiry_date = $_POST['expiry_date'] ?? '';
        $batch_number = trim($_POST['batch_number'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        if (empty($batch_number)) {
            $batch_number = 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        }
        
        $errors = [];
        if (empty($medication_name)) {
            $errors[] = 'Medicine name is required';
        }
        if ($quantity < 0) {
            $errors[] = 'Quantity cannot be negative';
        }
        // ✅ FIXED: Selling price can be as low as 1 TSh
        if ($selling_price < 0) {
            $errors[] = 'Selling price cannot be negative';
        }
        if (!empty($expiry_date) && strtotime($expiry_date) < strtotime(date('Y-m-d'))) {
            $errors[] = 'Expiry date cannot be in the past';
        }
        
        if (empty($errors)) {
            try {
                // ✅ AUTO-ASSIGN TO USER'S BRANCH
                $stmt = $db->prepare("
                    INSERT INTO medications_inventory (
                        medication_name, category, unit, quantity, reorder_level,
                        unit_cost, selling_price, supplier, expiry_date, batch_number,
                        branch_id, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $medication_name, $category, $unit, $quantity, $reorder_level,
                    $unit_cost, $selling_price, $supplier, $expiry_date, $batch_number,
                    $user_branch_id, $status  // ✅ Auto-assign to user's branch
                ]);
                
                $message = "✅ Medicine added successfully to <strong>" . htmlspecialchars($user_branch_name) . "</strong>! Batch: <strong>$batch_number</strong>";
                $message_type = 'success';
                
                $_SESSION['inventory_message'] = $message;
                $_SESSION['inventory_message_type'] = $message_type;
                header('Location: inventory.php?added=1');
                exit;
                
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
}

// ================================================================
// CHECK FOR SESSION MESSAGES
// ================================================================
if (isset($_SESSION['inventory_message'])) {
    $message = $_SESSION['inventory_message'];
    $message_type = $_SESSION['inventory_message_type'] ?? 'success';
    unset($_SESSION['inventory_message']);
    unset($_SESSION['inventory_message_type']);
}

// ================================================================
// GET FILTERS FROM URL
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$stock_filter = isset($_GET['stock']) ? trim($_GET['stock']) : '';
$expiry_filter = isset($_GET['expiry']) ? trim($_GET['expiry']) : '';
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;

// ================================================================
// BUILD QUERY - EXCLUDE OUT OF STOCK FROM MAIN INVENTORY
// ================================================================
$query = "
    SELECT *, 
        DATEDIFF(expiry_date, CURDATE()) as days_remaining
    FROM medications_inventory 
    WHERE branch_id = ?
    AND quantity > 0  -- ✅ EXCLUDE OUT OF STOCK
";
$params = [$user_branch_id];

if (!empty($search)) {
    $query .= " AND medication_name LIKE ?";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $query .= " AND category = ?";
    $params[] = $category_filter;
}

if ($status_filter === 'active') {
    $query .= " AND status = 'active'";
} elseif ($status_filter === 'inactive') {
    $query .= " AND status = 'inactive'";
}

if ($stock_filter === 'low') {
    // ✅ LOW STOCK: includes quantity <= reorder_level AND quantity > 0
    $query .= " AND quantity <= reorder_level AND quantity > 0 AND status = 'active'";
} elseif ($stock_filter === 'out') {
    // ✅ OUT OF STOCK: quantity = 0
    $query .= " AND quantity = 0 AND status = 'active'";
}

if ($expiry_filter === 'expiring') {
    $query .= " AND expiry_date IS NOT NULL 
                AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                AND status = 'active'";
}

$query .= " ORDER BY medication_name ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$inventory = $stmt->fetchAll();

// ================================================================
// GET VIEW DATA
// ================================================================
$view_data = null;
if ($view_id > 0) {
    $stmt = $db->prepare("SELECT * FROM medications_inventory WHERE id = ? AND branch_id = ?");
    $stmt->execute([$view_id, $user_branch_id]);
    $view_data = $stmt->fetch();
}

// ================================================================
// GET STATISTICS
// ================================================================
// Total active medicines (including out of stock for counting)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM medications_inventory WHERE branch_id = ? AND status = 'active'");
$stmt->execute([$user_branch_id]);
$total_medicines = $stmt->fetch()['count'] ?? 0;

// Low Stock + Out of Stock combined
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? 
    AND status = 'active' 
    AND quantity <= reorder_level
");
$stmt->execute([$user_branch_id]);
$low_stock_count = $stmt->fetch()['count'] ?? 0;

// Out of Stock only (for display in filter)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND quantity = 0 AND status = 'active'
");
$stmt->execute([$user_branch_id]);
$out_of_stock = $stmt->fetch()['count'] ?? 0;

// Expiring soon
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND expiry_date IS NOT NULL 
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND status = 'active'
");
$stmt->execute([$user_branch_id]);
$expiring_soon = $stmt->fetch()['count'] ?? 0;

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$pending_prescriptions = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescription_sales WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_prescriptions = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

$profile_pic = $_SESSION['profile_pic'] ?? '';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!-- ================================================================ -->
<!-- STYLES -->
<!-- ================================================================ -->
<style>
    :root {
        --primary: #0B5ED7;
        --primary-dark: #0A3D8A;
        --primary-light: #E8F0FE;
        --success: #059669;
        --success-dark: #047857;
        --success-light: #D1FAE5;
        --warning: #D97706;
        --warning-light: #FEF3C7;
        --danger: #DC2626;
        --danger-light: #FEE2E2;
        --purple: #7C3AED;
        --purple-light: #EDE9FE;
        
        --bg-body: #F1F5F9;
        --bg-card: #FFFFFF;
        --border-color: #E2E8F0;
        --text-primary: #0F172A;
        --text-secondary: #475569;
        --text-muted: #94A3B8;
        --table-stripe: #F8FAFC;
        --table-hover: #E8F0FE;
        --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
    }
    
    [data-theme="dark"] {
        --bg-body: #0F172A;
        --bg-card: #1E293B;
        --border-color: #334155;
        --text-primary: #F1F5F9;
        --text-secondary: #94A3B8;
        --text-muted: #64748B;
        --table-stripe: #1E293B;
        --table-hover: #1E3A5F;
        --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --shadow-lg: 0 8px 30px rgba(0,0,0,0.4);
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        border-radius: 16px;
        padding: 18px 20px;
        border: none;
        transition: all 0.3s;
        color: white;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        min-height: 100px;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    
    .stat-card:active {
        transform: scale(0.97);
    }
    
    .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
    .stat-card.red { background: linear-gradient(135deg, #DC2626, #991B1B); }
    .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    
    .stat-card .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        background: rgba(255,255,255,0.15);
        color: white;
        flex-shrink: 0;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover .stat-icon {
        transform: scale(1.1) rotate(-5deg);
        background: rgba(255,255,255,0.25);
    }
    
    .stat-card .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: white;
        line-height: 1.2;
    }
    
    .stat-card .stat-label {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.85);
        font-weight: 500;
        margin-top: 2px;
    }
    
    .stat-card .stat-trend {
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: rgba(255,255,255,0.15);
        color: white;
        display: inline-block;
        margin-top: 4px;
    }
    
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .card-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .card-title .title-blue { color: var(--primary); }
    
    .result-count {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .result-count strong {
        color: var(--primary);
    }
    
    .filter-group {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
    }
    
    .filter-btn {
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        border: 2px solid var(--border-color);
        background: transparent;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    
    .filter-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .filter-btn.active {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }
    
    .filter-btn.active:hover {
        background: var(--primary-dark);
        border-color: var(--primary-dark);
    }
    
    .filter-btn.clear-filter {
        border-color: var(--danger);
        color: var(--danger);
    }
    
    .filter-btn.clear-filter:hover {
        background: var(--danger);
        color: white;
    }
    
    .search-form {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .search-form input[type="text"],
    .search-form select {
        padding: 8px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
        flex: 1;
        min-width: 120px;
    }
    
    .search-form input:focus,
    .search-form select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .search-form .btn-search {
        padding: 8px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        border: none;
        background: var(--primary);
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .search-form .btn-search:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .search-form .btn-reset {
        padding: 8px 16px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        border: 2px solid var(--border-color);
        background: transparent;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    
    .search-form .btn-reset:hover {
        border-color: var(--danger);
        color: var(--danger);
    }
    
    .btn-add {
        background: var(--success);
        color: white;
        padding: 10px 24px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.9rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
    }
    
    .btn-add:hover {
        background: var(--success-dark);
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(5, 150, 105, 0.35);
    }
    
    .btn-add i {
        font-size: 1rem;
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.82rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-outline:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: var(--primary-light);
    }
    
    .header-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .table-scroll-wrapper {
        position: relative;
        overflow: hidden;
        border-radius: 12px;
        border: 2px solid var(--border-color);
        background: var(--bg-card);
    }
    
    .scroll-controls {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 12px;
        background: var(--bg-body);
        border-bottom: 2px solid var(--border-color);
        gap: 8px;
        flex-wrap: wrap;
    }
    
    [data-theme="dark"] .scroll-controls {
        background: #1E293B;
    }
    
    .scroll-controls .scroll-info {
        font-size: 0.7rem;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .scroll-controls .scroll-info i {
        color: var(--primary);
    }
    
    .scroll-controls .btn-group {
        display: flex;
        gap: 4px;
    }
    
    .scroll-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        padding: 6px 14px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        background: var(--bg-card);
        color: var(--text-secondary);
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        min-width: 70px;
    }
    
    .scroll-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: var(--primary-light);
        transform: translateY(-1px);
    }
    
    [data-theme="dark"] .scroll-btn:hover {
        background: #1E3A5F;
        border-color: var(--primary-light);
        color: var(--primary-light);
    }
    
    .scroll-btn:active {
        transform: scale(0.95);
    }
    
    .scroll-btn i {
        font-size: 0.8rem;
    }
    
    .scroll-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    .table-wrap {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 550px;
        scroll-behavior: smooth;
        padding-bottom: 0;
    }
    
    .table-wrap::-webkit-scrollbar {
        height: 8px;
        width: 8px;
    }
    
    .table-wrap::-webkit-scrollbar-track {
        background: var(--bg-body);
        border-radius: 4px;
    }
    
    .table-wrap::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 4px;
    }
    
    .table-wrap::-webkit-scrollbar-thumb:hover {
        background: var(--primary-dark);
    }
    
    .table-wrap::-webkit-scrollbar-corner {
        background: var(--bg-body);
    }
    
    .data-table {
        width: 100%;
        min-width: 1100px;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.82rem;
    }
    
    .data-table thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: var(--primary);
        color: white;
        border-bottom: 3px solid var(--primary-dark);
        white-space: nowrap;
        padding: 10px 14px;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
    }
    
    .data-table thead th:first-child {
        border-radius: 8px 0 0 0;
    }
    
    .data-table thead th:last-child {
        border-radius: 0 8px 0 0;
    }
    
    .data-table tbody tr:nth-child(even) {
        background: var(--primary-light);
    }
    
    .data-table tbody tr:hover td {
        background: var(--success-light);
    }
    
    [data-theme="dark"] .data-table tbody tr:nth-child(even) {
        background: #1E293B;
    }
    
    [data-theme="dark"] .data-table tbody tr:hover td {
        background: #1A3A2A;
    }
    
    .data-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
        white-space: nowrap;
    }
    
    .col-sno { min-width: 40px; width: 40px; text-align: center; }
    .col-name { min-width: 180px; }
    .col-category { min-width: 120px; }
    .col-qty { min-width: 60px; text-align: center; }
    .col-reorder { min-width: 80px; text-align: center; }
    .col-stock { min-width: 120px; }
    .col-price { min-width: 100px; }
    .col-expiry { min-width: 120px; }
    .col-days { min-width: 80px; text-align: center; }
    .col-batch { min-width: 150px; }
    .col-supplier { min-width: 120px; }
    .col-status { min-width: 80px; text-align: center; }
    .col-actions { min-width: 80px; text-align: center; }
    
    .status-badge {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .status-badge.active {
        background: var(--success-light);
        color: var(--success);
    }
    
    .status-badge.inactive {
        background: var(--danger-light);
        color: var(--danger);
    }
    
    [data-theme="dark"] .status-badge.active {
        background: #1A3A2A;
        color: #34D399;
    }
    
    [data-theme="dark"] .status-badge.inactive {
        background: #3A1A1A;
        color: #F87171;
    }
    
    .stock-badge {
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .stock-badge.ok {
        background: var(--success-light);
        color: var(--success);
    }
    
    .stock-badge.low {
        background: var(--warning-light);
        color: var(--warning);
        animation: pulse-low 1.5s infinite;
    }
    
    .stock-badge.out {
        background: var(--danger-light);
        color: var(--danger);
        animation: pulse-low 1s infinite;
    }
    
    @keyframes pulse-low {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
    }
    
    [data-theme="dark"] .stock-badge.ok {
        background: #1A3A2A;
        color: #34D399;
    }
    
    [data-theme="dark"] .stock-badge.low {
        background: #3D2E0A;
        color: #FBBF24;
    }
    
    [data-theme="dark"] .stock-badge.out {
        background: #3A1A1A;
        color: #F87171;
    }
    
    .expiry-badge {
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .expiry-badge.valid {
        background: var(--success-light);
        color: var(--success);
    }
    
    .expiry-badge.expiring {
        background: var(--warning-light);
        color: var(--warning);
        animation: pulse-low 1.5s infinite;
    }
    
    .expiry-badge.expired {
        background: var(--danger-light);
        color: var(--danger);
        animation: pulse-low 1s infinite;
    }
    
    .days-remaining {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .days-remaining.good {
        background: var(--success-light);
        color: var(--success);
    }
    
    .days-remaining.warning {
        background: var(--warning-light);
        color: var(--warning);
        animation: pulse-low 1.5s infinite;
    }
    
    .days-remaining.danger {
        background: var(--danger-light);
        color: var(--danger);
        animation: pulse-low 1s infinite;
    }
    
    .batch-number {
        font-family: monospace;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 4px;
        background: var(--primary-light);
        color: var(--primary);
    }
    
    [data-theme="dark"] .batch-number {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    .action-btn {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .action-btn.view {
        background: var(--purple);
        color: white;
    }
    
    .action-btn.view:hover {
        background: #6D28D9;
        transform: scale(1.05);
    }
    
    .message-box {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
        animation: slideDown 0.4s ease;
    }
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .message-box.success {
        background: var(--success-light);
        color: #065F46;
        border: 2px solid #6EE7B7;
    }
    
    .message-box.error {
        background: var(--danger-light);
        color: #991B1B;
        border: 2px solid #FCA5A5;
    }
    
    .message-box i {
        font-size: 1.3rem;
    }
    
    [data-theme="dark"] .message-box.success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }
    
    [data-theme="dark"] .message-box.error {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }
    
    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 3rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 12px;
    }
    
    .empty-state p {
        font-size: 0.95rem;
    }
    
    .empty-state .sub {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-top: 4px;
    }
    
    .view-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 16px;
    }
    
    .view-grid .view-item {
        padding: 10px 14px;
        background: var(--bg-body);
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }
    
    .view-grid .view-item .view-label {
        font-size: 0.6rem;
        text-transform: uppercase;
        color: var(--text-secondary);
        font-weight: 600;
        letter-spacing: 0.05em;
    }
    
    .view-grid .view-item .view-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-top: 2px;
    }
    
    .view-grid .full-width {
        grid-column: 1 / -1;
    }
    
    [data-theme="dark"] .view-grid .view-item {
        background: #1E293B;
    }
    
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
        animation: fadeIn 0.3s ease;
    }
    
    .modal-overlay.show {
        display: flex;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .modal-content {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 28px 32px;
        max-width: 750px;
        width: 95%;
        max-height: 90vh;
        overflow-y: auto;
        border: 2px solid var(--border-color);
        box-shadow: var(--shadow-lg);
        animation: slideUp 0.3s ease;
    }
    
    @keyframes slideUp {
        from { transform: translateY(30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--border-color);
        margin-bottom: 16px;
    }
    
    .modal-header .modal-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .modal-header .modal-title i {
        color: var(--primary);
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--text-secondary);
        transition: all 0.3s ease;
    }
    
    .modal-close:hover {
        color: var(--danger);
        transform: rotate(90deg);
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    
    .form-grid .full-width {
        grid-column: 1 / -1;
    }
    
    .form-row {
        margin-bottom: 0;
    }
    
    .form-row .form-label {
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
        display: block;
    }
    
    .form-row .form-label .required {
        color: var(--danger);
        margin-left: 2px;
    }
    
    .form-row .form-control {
        width: 100%;
        padding: 8px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--bg-card);
        color: var(--text-primary);
    }
    
    .form-row .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .form-row .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.5;
    }
    
    .form-row select.form-control {
        appearance: auto;
        cursor: pointer;
    }
    
    .form-row .help-text {
        font-size: 0.65rem;
        color: var(--text-muted);
        margin-top: 2px;
    }
    
    .category-input-group {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    
    .category-input-group .form-control {
        flex: 1;
    }
    
    .category-input-group .btn-category-toggle {
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        padding: 8px 12px;
        font-size: 0.7rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
        height: 42px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .category-input-group .btn-category-toggle:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
    }
    
    .batch-input-group {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    
    .batch-input-group .form-control {
        flex: 1;
    }
    
    .batch-input-group .btn-generate-batch {
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        padding: 8px 14px;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        height: 42px;
    }
    
    .batch-input-group .btn-generate-batch:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .batch-help-text {
        font-size: 0.65rem;
        color: var(--text-muted);
        margin-top: 2px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 2px solid var(--border-color);
        flex-wrap: wrap;
    }
    
    .btn-save {
        background: var(--primary);
        color: white;
        padding: 10px 28px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.9rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-save:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .btn-cancel-modal {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
        padding: 10px 24px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-cancel-modal:hover {
        border-color: var(--danger);
        color: var(--danger);
    }
    
    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }
    
    .animate-fade-in-up:nth-child(1) { animation-delay: 0.05s; }
    .animate-fade-in-up:nth-child(2) { animation-delay: 0.1s; }
    .animate-fade-in-up:nth-child(3) { animation-delay: 0.15s; }
    .animate-fade-in-up:nth-child(4) { animation-delay: 0.2s; }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @media (max-width: 992px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .search-form {
            flex-direction: column;
            align-items: stretch;
        }
        .search-form input[type="text"],
        .search-form select {
            min-width: 100%;
        }
        .filter-group {
            justify-content: center;
        }
        .card {
            padding: 12px 14px;
        }
        .modal-content {
            padding: 16px 18px;
        }
        .form-grid {
            grid-template-columns: 1fr;
        }
        .form-grid .full-width {
            grid-column: 1;
        }
        .category-input-group {
            flex-direction: column;
        }
        .category-input-group .btn-category-toggle {
            width: 100%;
            justify-content: center;
        }
        .batch-input-group {
            flex-direction: column;
        }
        .batch-input-group .btn-generate-batch {
            width: 100%;
            justify-content: center;
        }
        .scroll-controls {
            flex-direction: column;
            align-items: stretch;
            gap: 6px;
        }
        .scroll-controls .scroll-info {
            justify-content: center;
        }
        .scroll-controls .btn-group {
            justify-content: center;
        }
        .scroll-btn {
            padding: 5px 10px;
            font-size: 0.7rem;
            min-width: 60px;
        }
        .data-table {
            font-size: 0.7rem;
            min-width: 850px;
        }
        .data-table th,
        .data-table td {
            padding: 5px 8px;
        }
        .view-grid {
            grid-template-columns: 1fr;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn-save,
        .form-actions .btn-cancel-modal {
            width: 100%;
            justify-content: center;
        }
        .stat-card .stat-number {
            font-size: 1.3rem;
        }
        .stat-card {
            padding: 12px 16px;
            min-height: 80px;
        }
        .stat-card .stat-icon {
            width: 36px;
            height: 36px;
            font-size: 1rem;
        }
        .header-actions {
            flex-direction: column;
            align-items: stretch;
            width: 100%;
        }
        .header-actions .btn-add,
        .header-actions .btn-outline {
            width: 100%;
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .stat-card .stat-number {
            font-size: 1.1rem;
        }
        .stat-card .stat-label {
            font-size: 0.6rem;
        }
        .stat-card .stat-icon {
            width: 30px;
            height: 30px;
            font-size: 0.8rem;
        }
        .stat-card {
            padding: 8px 12px;
            min-height: 70px;
        }
        .modal-content {
            padding: 12px 14px;
        }
        .data-table {
            min-width: 750px;
            font-size: 0.65rem;
        }
        .data-table th,
        .data-table td {
            padding: 4px 6px;
        }
        .scroll-btn {
            padding: 4px 8px;
            font-size: 0.65rem;
            min-width: 50px;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-warehouse mr-2" style="color: var(--primary);"></i> Medicine Inventory
            </h1>
            <p class="page-subtitle">
                View all medicines in stock (out of stock hidden)
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-pills mr-1"></i> <?= $total_medicines ?> medicines
                </span>
            </p>
        </div>
        <div class="header-actions">
            <button onclick="openAddModal()" class="btn-add">
                <i class="fas fa-plus-circle"></i> Add Medicine
            </button>
            <a href="dashboard.php" class="btn-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <a href="inventory.php" class="stat-card blue">
            <div>
                <p class="stat-label">Total Medicines</p>
                <p class="stat-number"><?= number_format($total_medicines) ?></p>
                <span class="stat-trend"><i class="fas fa-pills"></i> Click to view all</span>
            </div>
            <div class="stat-icon"><i class="fas fa-pills"></i></div>
        </a>
        
        <a href="inventory.php?stock=low" class="stat-card orange">
            <div>
                <p class="stat-label">Low Stock <span style="font-size:0.6rem;">(inc. out of stock)</span></p>
                <p class="stat-number"><?= number_format($low_stock_count + $out_of_stock) ?></p>
                <span class="stat-trend"><i class="fas fa-exclamation-triangle"></i> Click to filter</span>
            </div>
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        </a>
        
        <a href="inventory.php?expiry=expiring" class="stat-card red">
            <div>
                <p class="stat-label">Expiring Soon</p>
                <p class="stat-number"><?= number_format($expiring_soon) ?></p>
                <span class="stat-trend"><i class="fas fa-clock"></i> Click to filter</span>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </a>
        
        <?php 
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM medications_inventory WHERE branch_id = ? AND quantity > 0 AND status = 'active'");
            $stmt->execute([$user_branch_id]);
            $in_stock_count = $stmt->fetch()['count'] ?? 0;
        ?>
        <a href="inventory.php?status=active" class="stat-card purple">
            <div>
                <p class="stat-label">In Stock</p>
                <p class="stat-number"><?= number_format($in_stock_count) ?></p>
                <span class="stat-trend"><i class="fas fa-check-circle"></i> Click to view</span>
            </div>
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FILTERS & SEARCH -->
    <!-- ================================================================ -->
    <div class="card mb-5 animate-fade-in-up">
        <div class="filter-group">
            <a href="inventory.php" class="filter-btn <?= empty($status_filter) && empty($stock_filter) && empty($expiry_filter) ? 'active' : '' ?>">All (In Stock)</a>
            <a href="inventory.php?status=active" class="filter-btn <?= $status_filter === 'active' ? 'active' : '' ?>">Active</a>
            <a href="inventory.php?stock=low" class="filter-btn <?= $stock_filter === 'low' ? 'active' : '' ?>">Low Stock</a>
            <a href="inventory.php?stock=out" class="filter-btn <?= $stock_filter === 'out' ? 'active' : '' ?>">Out of Stock</a>
            <a href="inventory.php?status=inactive" class="filter-btn <?= $status_filter === 'inactive' ? 'active' : '' ?>">Inactive</a>
            <?php if (!empty($stock_filter) || !empty($expiry_filter) || !empty($status_filter)): ?>
                <a href="inventory.php" class="filter-btn clear-filter">
                    <i class="fas fa-times"></i> Clear Filter
                </a>
            <?php endif; ?>
        </div>
        
        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="🔍 Search medicine..." 
                   value="<?= htmlspecialchars($search) ?>">
            
            <select name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['category']) ?>" 
                        <?= $category_filter === $cat['category'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['category']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn-search">
                <i class="fas fa-search"></i> Filter
            </button>
            
            <a href="inventory.php" class="btn-reset">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- INVENTORY TABLE -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i>
                Medicine List
                <span class="result-count ml-2">(<strong><?= number_format(count($inventory)) ?></strong> record(s))</span>
                <?php if (!empty($stock_filter) || !empty($expiry_filter)): ?>
                    <span class="ml-2 inline-flex bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-xs border border-orange-200">
                        <i class="fas fa-filter mr-1"></i> Filtered
                    </span>
                <?php endif; ?>
                <?php if ($stock_filter === 'out'): ?>
                    <span class="ml-2 inline-flex bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs border border-red-200">
                        <i class="fas fa-exclamation-circle mr-1"></i> Out of Stock
                    </span>
                <?php endif; ?>
            </h3>
        </div>
        
        <?php if (count($inventory) > 0): ?>
            <div class="table-scroll-wrapper">
                
                <!-- Scroll Controls -->
                <div class="scroll-controls">
                    <div class="scroll-info">
                        <i class="fas fa-arrows-left-right"></i>
                        <span id="scrollPositionText">Scroll to view all columns</span>
                    </div>
                    <div class="btn-group">
                        <button class="scroll-btn left" onclick="scrollTable('left')" title="Scroll Left">
                            <i class="fas fa-chevron-left"></i> Left
                        </button>
                        <button class="scroll-btn right" onclick="scrollTable('right')" title="Scroll Right">
                            Right <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
                
                <div class="table-wrap" id="tableWrap" onscroll="updateScrollButtons()">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="col-sno" style="border-radius: 8px 0 0 0;">#</th>
                                <th class="col-name">Medicine Name</th>
                                <th class="col-category">Category</th>
                                <th class="col-qty">Qty</th>
                                <th class="col-reorder">Reorder Level</th>
                                <th class="col-stock">Stock Status</th>
                                <th class="col-price">Selling Price</th>
                                <th class="col-expiry">Expiry Date</th>
                                <th class="col-days">Days Left</th>
                                <th class="col-batch">Batch Number</th>
                                <th class="col-supplier">Supplier</th>
                                <th class="col-status">Status</th>
                                <th class="col-actions" style="border-radius: 0 8px 0 0;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($inventory as $item): ?>
                                <?php
                                    $stock_status = 'ok';
                                    $stock_label = 'In Stock';
                                    if ($item['quantity'] <= 0) {
                                        $stock_status = 'out';
                                        $stock_label = 'Out of Stock';
                                    } elseif ($item['quantity'] <= $item['reorder_level']) {
                                        $stock_status = 'low';
                                        $stock_label = 'Low Stock';
                                    }
                                    
                                    $expiry_status = 'valid';
                                    $days_remaining = '-';
                                    $days_class = 'good';
                                    if (!empty($item['expiry_date'])) {
                                        $days_remaining = $item['days_remaining'];
                                        if ($days_remaining < 0) {
                                            $expiry_status = 'expired';
                                            $days_class = 'danger';
                                        } elseif ($days_remaining <= 30) {
                                            $expiry_status = 'expiring';
                                            $days_class = 'warning';
                                        } else {
                                            $expiry_status = 'valid';
                                            $days_class = 'good';
                                        }
                                    }
                                ?>
                                <tr>
                                    <td class="col-sno"><?= $counter++ ?></td>
                                    <td class="col-name">
                                        <strong><?= htmlspecialchars($item['medication_name']) ?></strong>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></div>
                                    </td>
                                    <td class="col-category"><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                    <td class="col-qty"><strong><?= $item['quantity'] ?></strong></td>
                                    <td class="col-reorder"><?= $item['reorder_level'] ?></td>
                                    <td class="col-stock">
                                        <span class="stock-badge <?= $stock_status ?>">
                                            <i class="fas <?= $stock_status === 'ok' ? 'fa-check-circle' : ($stock_status === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                                            <?= $stock_label ?>
                                        </span>
                                        <?php if ($stock_status === 'low'): ?>
                                            <div class="text-xs text-gray-400">Reorder: <?= $item['reorder_level'] ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-price"><strong>TSh <?= number_format($item['selling_price'] ?? 0) ?></strong></td>
                                    <td class="col-expiry">
                                        <?php if (!empty($item['expiry_date'])): ?>
                                            <span class="expiry-badge <?= $expiry_status ?>">
                                                <?= date('M d, Y', strtotime($item['expiry_date'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-days">
                                        <?php if (!empty($item['expiry_date']) && $days_remaining !== '-'): ?>
                                            <span class="days-remaining <?= $days_class ?>">
                                                <?php if ($days_remaining < 0): ?>
                                                    <i class="fas fa-skull"></i> EXPIRED
                                                <?php elseif ($days_remaining <= 30): ?>
                                                    <i class="fas fa-clock"></i> <?= $days_remaining ?> days
                                                <?php else: ?>
                                                    <i class="fas fa-check"></i> <?= $days_remaining ?> days
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-batch">
                                        <?php if (!empty($item['batch_number'])): ?>
                                            <span class="batch-number"><?= htmlspecialchars($item['batch_number']) ?></span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-supplier"><?= htmlspecialchars($item['supplier'] ?? 'N/A') ?></td>
                                    <td class="col-status">
                                        <span class="status-badge <?= $item['status'] ?? 'active' ?>">
                                            <?= ucfirst($item['status'] ?? 'Active') ?>
                                        </span>
                                    </td>
                                    <td class="col-actions">
                                        <div class="flex gap-1 justify-center">
                                            <a href="inventory.php?view=<?= $item['id'] ?>" 
                                               class="action-btn view" title="View Details">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-prescription"></i>
                <p>No medicines found</p>
                <?php if (!empty($search) || !empty($category_filter) || !empty($stock_filter) || !empty($expiry_filter)): ?>
                    <p class="sub">Try adjusting your filters</p>
                <?php else: ?>
                    <p class="sub">Click "Add Medicine" to get started</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- VIEW MEDICINE MODAL -->
    <!-- ================================================================ -->
    <?php if ($view_data): ?>
    <div class="modal-overlay show" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-eye"></i> Medicine Details
                </div>
                <a href="inventory.php" class="modal-close">&times;</a>
            </div>
            
            <div class="view-grid">
                <div class="view-item full-width">
                    <div class="view-label">Medicine Name</div>
                    <div class="view-value"><?= htmlspecialchars($view_data['medication_name']) ?></div>
                </div>
                
                <div class="view-item">
                    <div class="view-label">Category</div>
                    <div class="view-value"><?= htmlspecialchars($view_data['category'] ?? 'N/A') ?></div>
                </div>
                
                <div class="view-item">
                    <div class="view-label">Unit</div>
                    <div class="view-value"><?= htmlspecialchars($view_data['unit'] ?? 'pcs') ?></div>
                </div>
                
                <div class="view-item">
                    <div class="view-label">Current Quantity</div>
                    <div class="view-value">
                        <strong><?= $view_data['quantity'] ?></strong>
                        <?php if ($view_data['quantity'] <= 0): ?>
                            <span class="stock-badge out">Out of Stock</span>
                        <?php elseif ($view_data['quantity'] <= $view_data['reorder_level']): ?>
                            <span class="stock-badge low">Low Stock</span>
                        <?php else: ?>
                            <span class="stock-badge ok">In Stock</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="view-item">
                    <div class="view-label">Reorder Level</div>
                    <div class="view-value"><?= $view_data['reorder_level'] ?></div>
                </div>
                
                <div class="view-item">
                    <div class="view-label">Buying Price</div>
                    <div class="view-value">TSh <?= number_format($view_data['unit_cost'] ?? 0) ?></div>
                </div>
                
                <div class="view-item">
                    <div class="view-label">Selling Price</div>
                    <div class="view-value">TSh <?= number_format($view_data['selling_price'] ?? 0) ?></div>
                </div>
                
                <div class="view-item">
                    <div class="view-label">Supplier</div>
                    <div class="view-value"><?= htmlspecialchars($view_data['supplier'] ?? 'N/A') ?></div>
                </div>
                
                <div class="view-item">
                    <div class="view-label">Batch Number</div>
                    <div class="view-value">
                        <?php if (!empty($view_data['batch_number'])): ?>
                            <span class="batch-number"><?= htmlspecialchars($view_data['batch_number']) ?></span>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="view-item">
                    <div class="view-label">Expiry Date</div>
                    <div class="view-value">
                        <?php if (!empty($view_data['expiry_date'])): ?>
                            <?php 
                                $days_until_expiry = (strtotime($view_data['expiry_date']) - time()) / 86400;
                            ?>
                            <span class="expiry-badge <?= $days_until_expiry < 0 ? 'expired' : ($days_until_expiry <= 30 ? 'expiring' : 'valid') ?>">
                                <?= date('M d, Y', strtotime($view_data['expiry_date'])) ?>
                            </span>
                            <?php if ($days_until_expiry >= 0): ?>
                                <div class="days-remaining <?= $days_until_expiry <= 30 ? 'warning' : 'good' ?>">
                                    <?= round($days_until_expiry) ?> days remaining
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="view-item">
                    <div class="view-label">Status</div>
                    <div class="view-value">
                        <span class="status-badge <?= $view_data['status'] ?? 'active' ?>">
                            <?= ucfirst($view_data['status'] ?? 'Active') ?>
                        </span>
                    </div>
                </div>
                
                <div class="view-item full-width">
                    <div class="view-label">Branch</div>
                    <div class="view-value">
                        <span class="branch-tag" style="background:var(--primary-light);color:var(--primary);padding:2px 12px;border-radius:12px;font-size:0.75rem;">
                            <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="inventory.php" class="btn-cancel-modal">
                    <i class="fas fa-times"></i> Close
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ADD MEDICINE MODAL - FIXED: Price from 1 TSh -->
    <!-- ================================================================ -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-plus-circle"></i> Add New Medicine
                </div>
                <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_medicine">
                
                <div class="form-grid">
                    <div class="full-width form-row">
                        <label class="form-label">Medicine Name <span class="required">*</span></label>
                        <input type="text" name="medication_name" class="form-control" 
                               placeholder="Enter medicine name" required>
                        <div class="help-text">e.g. Paracetamol 500mg, Amoxicillin 250mg</div>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Category</label>
                        <div class="category-input-group">
                            <select name="category" id="categorySelect" class="form-control">
                                <option value="">Select or type manually</option>
                                <?php foreach ($predefined_categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                                <option value="__other__">+ Other (Type manually)</option>
                            </select>
                            <input type="text" name="category_manual" id="categoryManual" class="form-control" 
                                   placeholder="Enter custom category..." style="display:none;">
                            <button type="button" class="btn-category-toggle" onclick="toggleCategoryInput()">
                                <i class="fas fa-edit"></i> Manual
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Unit</label>
                        <select name="unit" class="form-control">
                            <option value="pcs">Pieces (pcs)</option>
                            <option value="tablets">Tablets</option>
                            <option value="capsules">Capsules</option>
                            <option value="ml">Milliliters (ml)</option>
                            <option value="mg">Milligrams (mg)</option>
                            <option value="g">Grams (g)</option>
                            <option value="bottle">Bottle</option>
                            <option value="box">Box</option>
                            <option value="strip">Strip</option>
                            <option value="vial">Vial</option>
                            <option value="sachet">Sachet</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Current Quantity <span class="required">*</span></label>
                        <input type="number" name="quantity" class="form-control" 
                               placeholder="Current stock" min="0" required>
                        <div class="help-text">Current available stock</div>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Reorder Level <span class="required">*</span></label>
                        <input type="number" name="reorder_level" class="form-control" 
                               placeholder="Alert when stock reaches" value="10" min="0" required>
                        <div class="help-text">When stock reaches this level, system will show LOW STOCK alert</div>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Buying Price (TSh)</label>
                        <input type="number" name="unit_cost" class="form-control" 
                               placeholder="0" step="1" min="0">
                        <div class="help-text">Cost price per unit (minimum TSh 1)</div>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Selling Price (TSh) <span class="required">*</span></label>
                        <input type="number" name="selling_price" class="form-control" 
                               placeholder="1" step="1" min="1" value="1" required>
                        <div class="help-text">Selling price per unit (minimum TSh 1)</div>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Supplier</label>
                        <input type="text" name="supplier" class="form-control" 
                               placeholder="Supplier name">
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control">
                        <div class="help-text">System will show days remaining</div>
                    </div>
                    
                    <div class="full-width form-row">
                        <label class="form-label">Batch Number</label>
                        <div class="batch-input-group">
                            <input type="text" name="batch_number" id="batchNumberInput" class="form-control" 
                                   placeholder="BATCH-YYYYMMDD-XXXX" 
                                   value="<?= 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)) ?>">
                            <button type="button" class="btn-generate-batch" onclick="generateBatchNumber()">
                                <i class="fas fa-sync-alt"></i> Generate
                            </button>
                        </div>
                        <div class="batch-help-text">
                            <i class="fas fa-info-circle"></i> Auto-generated. Click "Generate" for a new batch number.
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> Save Medicine
                    </button>
                    <button type="button" class="btn-cancel-modal" onclick="closeModal('addModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer mt-5">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Inventory Management
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    function toggleCategoryInput() {
        var select = document.getElementById('categorySelect');
        var manual = document.getElementById('categoryManual');
        var btn = document.querySelector('#addModal .category-input-group .btn-category-toggle');
        
        if (manual.style.display === 'none') {
            manual.style.display = 'block';
            select.style.display = 'none';
            btn.innerHTML = '<i class="fas fa-list"></i> Select';
            manual.focus();
        } else {
            manual.style.display = 'none';
            select.style.display = 'block';
            btn.innerHTML = '<i class="fas fa-edit"></i> Manual';
        }
    }
    
    document.getElementById('categorySelect')?.addEventListener('change', function() {
        if (this.value === '__other__') {
            document.getElementById('categoryManual').style.display = 'block';
            document.getElementById('categoryManual').focus();
        }
    });
    
    function generateBatchNumber() {
        var now = new Date();
        var dateStr = now.getFullYear() + 
                      String(now.getMonth() + 1).padStart(2, '0') + 
                      String(now.getDate()).padStart(2, '0');
        var random = Math.random().toString(36).substring(2, 8).toUpperCase();
        var batch = 'BATCH-' + dateStr + '-' + random;
        document.getElementById('batchNumberInput').value = batch;
    }
    
    function updateScrollButtons() {
        var tableWrap = document.getElementById('tableWrap');
        if (!tableWrap) return;
        
        var scrollLeft = tableWrap.scrollLeft;
        var maxScroll = tableWrap.scrollWidth - tableWrap.clientWidth;
        
        var leftBtn = document.querySelector('.scroll-btn.left');
        var rightBtn = document.querySelector('.scroll-btn.right');
        
        if (leftBtn) leftBtn.disabled = scrollLeft <= 10;
        if (rightBtn) rightBtn.disabled = scrollLeft >= maxScroll - 10;
    }
    
    function scrollTable(direction) {
        var tableWrap = document.getElementById('tableWrap');
        if (!tableWrap) return;
        var scrollAmount = tableWrap.clientWidth * 0.7;
        if (direction === 'left') {
            tableWrap.scrollLeft = Math.max(0, tableWrap.scrollLeft - scrollAmount);
        } else {
            tableWrap.scrollLeft = Math.min(tableWrap.scrollWidth - tableWrap.clientWidth, tableWrap.scrollLeft + scrollAmount);
        }
        setTimeout(updateScrollButtons, 100);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(updateScrollButtons, 500);
    });
    
    window.addEventListener('resize', updateScrollButtons);
    
    function openAddModal() {
        document.getElementById('addModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        generateBatchNumber();
    }
    
    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
        document.body.style.overflow = 'auto';
    }
    
    document.querySelectorAll('.modal-overlay').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        });
    });
    
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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }
    
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
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });
    
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && sidebarToggle) {
                if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                    sidebar.classList.remove('open');
                }
            }
        }
    });
    
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
    
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') {
            return;
        }
        
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var searchInput = document.querySelector('.search-form input[type="text"]');
            searchInput?.focus();
            searchInput?.select();
        }
        
        if (e.key === 'ArrowLeft' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            scrollTable('left');
        }
        if (e.key === 'ArrowRight' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            scrollTable('right');
        }
        
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.show').forEach(function(modal) {
                modal.classList.remove('show');
                document.body.style.overflow = 'auto';
            });
        }
    });
    
    console.log('%c💊 Braick - Inventory (Out of Stock Hidden)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📦 Total: <?= $total_medicines ?> | Low Stock: <?= $low_stock_count + $out_of_stock ?>', 'font-size:13px; color:#059669;');
    console.log('%c📦 In Stock: <?= $in_stock_count ?>', 'font-size:13px; color:#34D399;');
    console.log('%c📦 Out of Stock: <?= $out_of_stock ?> (hidden from main list)', 'font-size:13px; color:#DC2626;');
    console.log('%c📅 Expiring Soon: <?= $expiring_soon ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c✅ Price from TSh 1 (not 100)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Auto-assigned to branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>