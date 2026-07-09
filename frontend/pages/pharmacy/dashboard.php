<?php
// ================================================================
// FILE: frontend/pages/pharmacy/dashboard.php
// BRAICK DISPENSARY - PHARMACY DASHBOARD
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Force session for direct access (Pharmacy role)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 104;
    $_SESSION['full_name'] = 'Pharm. James Kijana';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
}

// ================================================================
// GET BRANCH FROM URL PARAMETER
// ================================================================
$selected_branch_id = $_GET['branch'] ?? $_SESSION['branch_id'] ?? 1;

// If branch is passed via URL, update session
if (isset($_GET['branch']) && is_numeric($_GET['branch'])) {
    $_SESSION['branch_id'] = (int)$_GET['branch'];
    $selected_branch_id = (int)$_GET['branch'];
}

// ================================================================
// BRANCH CHECK
// ================================================================
$user_branch_id = $selected_branch_id;

// Include database and helpers
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/';
require_once $root_path . 'backend/config/database.php';
require_once $root_path . 'backend/helpers/functions.php';

// Get database connection
$db = Database::getInstance()->getConnection();

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_fallback = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='48' height='48'%3E%3Crect width='48' height='48' fill='%230B5ED7' rx='12'/%3E%3Ctext x='24' y='32' text-anchor='middle' fill='white' font-size='20' font-weight='bold'%3EB%3C/text%3E%3C/svg%3E";
$avatar_fallback = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='38' height='38'%3E%3Crect width='38' height='38' fill='%230B5ED7' rx='50%25'/%3E%3Ctext x='19' y='25' text-anchor='middle' fill='white' font-size='18' font-weight='bold'%3EP%3C/text%3E%3C/svg%3E";

// ================================================================
// GET BRANCH NAME
// ================================================================
$branch_name = 'Default Branch';
$stmt = $db->prepare("SELECT name, location FROM branches WHERE id = ? AND status = 'active'");
$stmt->execute([$user_branch_id]);
$branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
if ($branch_data) {
    $branch_name = $branch_data['name'];
} else {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' LIMIT 1");
    $default = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($default) {
        $branch_name = $default['name'];
        $_SESSION['branch_id'] = $default['id'];
        $user_branch_id = $default['id'];
    }
}

// ================================================================
// GET PHARMACY USER ID
// ================================================================
$pharmacy_id = $_SESSION['user_id'] ?? 0;

// ================================================================
// HANDLE ADD MEDICINE FORM
// ================================================================
$add_medicine_error = '';
$add_medicine_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_medicine'])) {
    $medication_name = trim($_POST['medication_name'] ?? '');
    $generic_name = trim($_POST['generic_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $batch_number = trim($_POST['batch_number'] ?? '');
    $buying_price = (float)($_POST['buying_price'] ?? 0);
    $selling_price = (float)($_POST['selling_price'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $reorder_level = (int)($_POST['reorder_level'] ?? 10);
    $expiry_date = $_POST['expiry_date'] ?? '';
    $storage_location = trim($_POST['storage_location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($medication_name) || empty($quantity) || empty($expiry_date)) {
        $add_medicine_error = 'Please fill in all required fields (Medicine Name, Quantity, Expiry Date)';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO medications_inventory (
                    medication_name, generic_name, category, batch_number,
                    buying_price, selling_price, quantity, reorder_level,
                    expiry_date, storage_location, description, branch_id, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([
                $medication_name, $generic_name, $category, $batch_number,
                $buying_price, $selling_price, $quantity, $reorder_level,
                $expiry_date, $storage_location, $description, $user_branch_id
            ]);
            $add_medicine_success = '✅ Medicine added successfully!';
        } catch (Exception $e) {
            $add_medicine_error = '❌ Error: ' . $e->getMessage();
        }
    }
}

// ================================================================
// HANDLE SELL MEDICINE (OTC SALE)
// ================================================================
$sell_error = '';
$sell_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sell_medicine'])) {
    $medicine_id = (int)($_POST['medicine_id'] ?? 0);
    $quantity_sold = (int)($_POST['quantity_sold'] ?? 0);
    $customer_name = trim($_POST['customer_name'] ?? 'Walk-in');
    $payment_method = $_POST['payment_method'] ?? 'cash';
    
    if ($medicine_id <= 0 || $quantity_sold <= 0) {
        $sell_error = 'Please select a medicine and enter quantity';
    } else {
        try {
            // Check stock
            $stmt = $db->prepare("SELECT * FROM medications_inventory WHERE id = ? AND branch_id = ?");
            $stmt->execute([$medicine_id, $user_branch_id]);
            $medicine = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$medicine) {
                $sell_error = 'Medicine not found';
            } elseif ($medicine['quantity'] < $quantity_sold) {
                $sell_error = 'Insufficient stock. Available: ' . $medicine['quantity'];
            } else {
                // Start transaction
                $db->beginTransaction();
                
                // Update stock
                $new_quantity = $medicine['quantity'] - $quantity_sold;
                $stmt = $db->prepare("UPDATE medications_inventory SET quantity = ? WHERE id = ?");
                $stmt->execute([$new_quantity, $medicine_id]);
                
                // Generate sale number
                $year = date('Y');
                $stmt = $db->query("SELECT COUNT(*) as count FROM pharmacy_sales WHERE YEAR(sale_date) = YEAR(CURDATE())");
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] + 1;
                $sale_number = "OTC-{$year}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
                
                // Insert sale
                $total_amount = $medicine['selling_price'] * $quantity_sold;
                $stmt = $db->prepare("
                    INSERT INTO pharmacy_sales (
                        sale_number, patient_id, branch_id, sale_type,
                        subtotal, total, payment_method, payment_status,
                        cashier_id, sale_date
                    ) VALUES (?, NULL, ?, 'outdoor', ?, ?, ?, 'paid', ?, NOW())
                ");
                $stmt->execute([
                    $sale_number, $user_branch_id,
                    $total_amount, $total_amount, $payment_method, $pharmacy_id
                ]);
                $sale_id = $db->lastInsertId();
                
                // Insert sale item
                $stmt = $db->prepare("
                    INSERT INTO sale_items (
                        sale_id, medication_name, quantity, unit_price, total_price
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $sale_id, $medicine['medication_name'],
                    $quantity_sold, $medicine['selling_price'], $total_amount
                ]);
                
                $db->commit();
                $sell_success = '✅ Sale completed! Receipt #' . $sale_number;
            }
        } catch (Exception $e) {
            $db->rollBack();
            $sell_error = '❌ Error: ' . $e->getMessage();
        }
    }
}

// ================================================================
// FETCH STATISTICS
// ================================================================

$today = date('Y-m-d');

// Prescription Sales
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_prescriptions,
        SUM(CASE WHEN p.status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN p.status = 'dispensed' THEN 1 ELSE 0 END) as dispensed,
        COALESCE(SUM(ps.total), 0) as total_revenue
    FROM prescriptions p
    LEFT JOIN pharmacy_sales ps ON p.id = ps.prescription_id
    WHERE DATE(p.created_at) = ? 
    AND p.branch_id = ?
");
$stmt->execute([$today, $user_branch_id]);
$prescription_data = $stmt->fetch(PDO::FETCH_ASSOC);
$prescription_total = $prescription_data['total_prescriptions'] ?? 0;
$prescription_pending = $prescription_data['pending'] ?? 0;
$prescription_dispensed = $prescription_data['dispensed'] ?? 0;
$prescription_revenue = $prescription_data['total_revenue'] ?? 0;

// OTC Sales
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        COALESCE(SUM(total), 0) as total_revenue,
        COALESCE(SUM(si.quantity), 0) as total_items
    FROM pharmacy_sales ps
    LEFT JOIN sale_items si ON ps.id = si.sale_id
    WHERE DATE(ps.sale_date) = ? 
    AND ps.branch_id = ?
    AND ps.sale_type = 'outdoor'
    AND ps.payment_status = 'paid'
");
$stmt->execute([$today, $user_branch_id]);
$otc_data = $stmt->fetch(PDO::FETCH_ASSOC);
$otc_transactions = $otc_data['total_transactions'] ?? 0;
$otc_revenue = $otc_data['total_revenue'] ?? 0;
$otc_items = $otc_data['total_items'] ?? 0;

// Available Medicines
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_items,
        SUM(quantity) as total_quantity
    FROM medications_inventory 
    WHERE status = 'active' 
    AND branch_id = ?
");
$stmt->execute([$user_branch_id]);
$inventory_data = $stmt->fetch(PDO::FETCH_ASSOC);
$available_items = $inventory_data['total_items'] ?? 0;
$total_quantity = $inventory_data['total_quantity'] ?? 0;

// Low Stock
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE quantity <= reorder_level 
    AND status = 'active' 
    AND branch_id = ?
");
$stmt->execute([$user_branch_id]);
$low_stock_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Near Expiry (within 30 days)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND status = 'active' 
    AND branch_id = ?
");
$stmt->execute([$user_branch_id]);
$near_expiry_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Expired
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE expiry_date < CURDATE() 
    AND status = 'active' 
    AND branch_id = ?
");
$stmt->execute([$user_branch_id]);
$expired_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// INVENTORY LIST
// ================================================================
$inventory_list = [];
$stmt = $db->prepare("
    SELECT * FROM medications_inventory 
    WHERE branch_id = ?
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([$user_branch_id]);
$inventory_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// LOW STOCK LIST
// ================================================================
$low_stock_list = [];
$stmt = $db->prepare("
    SELECT * FROM medications_inventory 
    WHERE quantity <= reorder_level 
    AND status = 'active' 
    AND branch_id = ?
    ORDER BY quantity ASC
");
$stmt->execute([$user_branch_id]);
$low_stock_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// NEAR EXPIRY LIST
// ================================================================
$near_expiry_list = [];
$stmt = $db->prepare("
    SELECT *, 
           DATEDIFF(expiry_date, CURDATE()) as days_remaining
    FROM medications_inventory 
    WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND status = 'active' 
    AND branch_id = ?
    ORDER BY expiry_date ASC
");
$stmt->execute([$user_branch_id]);
$near_expiry_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// RECENT PRESCRIPTIONS
// ================================================================
$recent_prescriptions = [];
$stmt = $db->prepare("
    SELECT p.*, pat.full_name as patient_name, u.full_name as doctor_name,
           COUNT(pi.id) as medicine_count
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.doctor_id = u.id
    LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
    WHERE p.branch_id = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 5
");
$stmt->execute([$user_branch_id]);
$recent_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// RECENT OTC SALES
// ================================================================
$recent_otc_sales = [];
$stmt = $db->prepare("
    SELECT ps.*, 
           GROUP_CONCAT(si.medication_name SEPARATOR ', ') as medicines
    FROM pharmacy_sales ps
    LEFT JOIN sale_items si ON ps.id = si.sale_id
    WHERE ps.branch_id = ?
    AND ps.sale_type = 'outdoor'
    AND ps.payment_status = 'paid'
    GROUP BY ps.id
    ORDER BY ps.sale_date DESC
    LIMIT 5
");
$stmt->execute([$user_branch_id]);
$recent_otc_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// MEDICINES FOR SELL DROPDOWN
// ================================================================
$medicines_for_sell = [];
$stmt = $db->prepare("
    SELECT id, medication_name, selling_price, quantity 
    FROM medications_inventory 
    WHERE status = 'active' 
    AND branch_id = ?
    AND quantity > 0
    ORDER BY medication_name ASC
");
$stmt->execute([$user_branch_id]);
$medicines_for_sell = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// RECENT ACTIVITIES
// ================================================================
$recent_activities = [
    ['action' => 'Prescription Dispensed', 'details' => 'Prescription #RX-2026-0042 - Patient: John Doe', 'created_at' => date('Y-m-d H:i:s', strtotime('-5 minutes'))],
    ['action' => 'OTC Sale Completed', 'details' => 'Customer: Walk-in - Paracetamol 500mg x 10', 'created_at' => date('Y-m-d H:i:s', strtotime('-15 minutes'))],
    ['action' => 'Medicine Added', 'details' => 'Added 100 units of Amoxicillin 250mg', 'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes'))],
    ['action' => 'Low Stock Alert', 'details' => 'Ciprofloxacin 500mg - Only 5 units remaining', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))],
];
?>
<!DOCTYPE html>
<html lang="en" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Dashboard - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: rgba(11, 94, 215, 0.10);
            --secondary: #0AA84F;
            --secondary-light: rgba(10, 168, 79, 0.10);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.08);
            --radius: 18px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: var(--transition);
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        /* ===== SIDEBAR - BLUE WITH GREEN HOVER ===== */
        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: 270px; background: #0B5ED7; 
            color: white;
            z-index: 50; overflow-y: auto;
            transition: transform 0.3s ease;
        }
        .sidebar-brand { padding: 22px 20px 16px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand .logo { width: 48px; height: 48px; border-radius: 12px; object-fit: cover; background: white; padding: 4px; }
        .sidebar-brand .branch-badge {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            margin-top: 4px;
        }
        .sidebar-nav { padding: 14px 10px; }
        .sidebar-nav .nav-label { font-size: 0.55rem; text-transform: uppercase; letter-spacing: 0.1em; color: rgba(255,255,255,0.4); padding: 0 12px; margin: 12px 0 6px; font-weight: 700; }
        .sidebar-link {
            display: flex; align-items: center; gap: 12px;
            padding: 9px 14px; border-radius: 10px;
            color: rgba(255,255,255,0.75); text-decoration: none;
            transition: var(--transition); font-size: 0.85rem; font-weight: 500;
            margin: 2px 0;
        }
        .sidebar-link:hover { background: rgba(10, 168, 79, 0.25); color: #FFFFFF; }
        .sidebar-link.active { background: #0AA84F; color: white; box-shadow: 0 4px 12px rgba(10, 168, 79, 0.4); }
        .sidebar-link.active i { color: white; }
        .sidebar-link i { width: 20px; text-align: center; font-size: 1rem; }
        .sidebar-link .badge { margin-left: auto; background: rgba(255,255,255,0.15); padding: 1px 9px; border-radius: 20px; font-size: 0.65rem; font-weight: 600; }
        .sidebar-link.active .badge { background: rgba(255,255,255,0.25); color: white; }
        
        /* ===== TOP NAV ===== */
        .top-nav {
            position: fixed; top: 0; left: 270px; right: 0;
            height: 68px; background: var(--bg-card); z-index: 40;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .top-nav .search-wrapper {
            display: flex; align-items: center;
            background: var(--bg-body); border-radius: 10px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .top-nav .search-wrapper:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15); }
        .top-nav .search-wrapper input {
            border: none; background: transparent; padding: 8px 14px;
            width: 280px; font-size: 0.85rem; outline: none;
            color: var(--text-primary);
        }
        .top-nav .search-wrapper input::placeholder { color: var(--text-secondary); }
        .top-nav .search-wrapper .search-btn {
            background: var(--primary); color: white;
            border: none; padding: 8px 16px; border-radius: 0 10px 10px 0;
            cursor: pointer; font-size: 0.85rem;
            transition: var(--transition);
        }
        .top-nav .search-wrapper .search-btn:hover { background: var(--primary-dark); }
        .top-nav .branch-badge {
            background: rgba(11, 94, 215, 0.08);
            color: var(--primary);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid rgba(11, 94, 215, 0.12);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .top-nav .avatar {
            width: 38px; height: 38px; border-radius: 50%;
            object-fit: cover; border: 2px solid var(--border-color);
            cursor: pointer; transition: var(--transition);
        }
        .top-nav .avatar:hover { border-color: var(--primary); }
        .top-nav .icon-btn {
            width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-secondary); transition: var(--transition);
            background: transparent; border: none; cursor: pointer;
            position: relative;
        }
        .top-nav .icon-btn:hover { background: var(--bg-body); color: var(--primary); }
        .notif-dot {
            position: absolute; top: 6px; right: 6px;
            width: 8px; height: 8px; background: #EF4444;
            border-radius: 50%; border: 2px solid var(--bg-card);
        }
        .dark-toggle {
            background: var(--bg-body); border: 1px solid var(--border-color);
            border-radius: 10px; padding: 6px 12px;
            cursor: pointer; font-size: 0.85rem;
            color: var(--text-primary);
            transition: var(--transition);
            display: flex; align-items: center; gap: 6px;
        }
        .dark-toggle:hover { border-color: var(--primary); }
        
        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 270px; margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            transition: var(--transition);
        }
        
        /* ===== SUMMARY CARDS - 4 CARDS (Blue & Green Only) ===== */
        .summary-card {
            border-radius: var(--radius);
            padding: 22px 24px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        .summary-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        /* Hover effect - GREEN */
        .summary-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: #0AA84F;
        }
        /* Card 1 - Blue */
        .summary-card.blue { 
            background: rgba(11, 94, 215, 0.08); 
            border-left: 4px solid #0B5ED7;
        }
        .summary-card.blue::after { background: linear-gradient(90deg, #0B5ED7, #1E88E5); }
        .summary-card.blue .sc-icon { background: rgba(11, 94, 215, 0.15); color: #0B5ED7; }
        .summary-card.blue .sc-number { color: #0B5ED7; }
        
        /* Card 2 - Green */
        .summary-card.green { 
            background: rgba(10, 168, 79, 0.08); 
            border-left: 4px solid #0AA84F;
        }
        .summary-card.green::after { background: linear-gradient(90deg, #0AA84F, #34D399); }
        .summary-card.green .sc-icon { background: rgba(10, 168, 79, 0.15); color: #0AA84F; }
        .summary-card.green .sc-number { color: #0AA84F; }
        
        /* Card 3 - Blue */
        .summary-card.blue2 { 
            background: rgba(11, 94, 215, 0.08); 
            border-left: 4px solid #0B5ED7;
        }
        .summary-card.blue2::after { background: linear-gradient(90deg, #0B5ED7, #1E88E5); }
        .summary-card.blue2 .sc-icon { background: rgba(11, 94, 215, 0.15); color: #0B5ED7; }
        .summary-card.blue2 .sc-number { color: #0B5ED7; }
        
        /* Card 4 - Green */
        .summary-card.green2 { 
            background: rgba(10, 168, 79, 0.08); 
            border-left: 4px solid #0AA84F;
        }
        .summary-card.green2::after { background: linear-gradient(90deg, #0AA84F, #34D399); }
        .summary-card.green2 .sc-icon { background: rgba(10, 168, 79, 0.15); color: #0AA84F; }
        .summary-card.green2 .sc-number { color: #0AA84F; }
        
        .summary-card .sc-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        .summary-card .sc-number {
            font-size: 2.2rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .summary-card .sc-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .summary-card .sc-revenue {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .summary-card .sc-stats {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        .summary-card .sc-stats span { font-weight: 600; }
        .summary-card .sc-stats .pending { color: #F59E0B; }
        .summary-card .sc-stats .dispensed { color: #0AA84F; }
        .summary-card .sc-stats .low { color: #EF4444; }
        .summary-card .sc-stats .expiry { color: #F59E0B; }
        
        .summary-card .sc-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        .summary-card .sc-actions .btn {
            padding: 5px 14px;
            font-size: 0.7rem;
            border-radius: 8px;
            font-weight: 600;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .summary-card .sc-actions .btn-primary { background: var(--primary); color: white; }
        .summary-card .sc-actions .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .summary-card .sc-actions .btn-outline { background: transparent; color: var(--text-secondary); border: 1px solid var(--border-color); }
        .summary-card .sc-actions .btn-outline:hover { background: var(--bg-body); border-color: var(--primary); color: var(--primary); }
        .summary-card .sc-actions .btn-success { background: var(--secondary); color: white; }
        .summary-card .sc-actions .btn-success:hover { background: #08944A; transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .summary-card .sc-actions .btn-danger { background: #EF4444; color: white; }
        .summary-card .sc-actions .btn-danger:hover { background: #DC2626; transform: translateY(-2px); box-shadow: var(--shadow-md); }
        
        .alert-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600;
        }
        .alert-badge.low { background: rgba(239, 68, 68, 0.12); color: #EF4444; }
        .alert-badge.expiry { background: rgba(245, 158, 11, 0.12); color: #F59E0B; }
        
        /* ===== CARDS ===== */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 18px 20px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .card:hover { box-shadow: var(--shadow-md); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .card-title { font-size: 0.9rem; font-weight: 600; color: var(--text-primary); }
        .card-title i { color: var(--primary); }
        
        /* ===== FORMS ===== */
        .form-input {
            width: 100%; padding: 8px 12px; border: 1px solid var(--border-color);
            border-radius: 8px; font-size: 0.8rem; outline: none;
            transition: var(--transition); background: var(--bg-card); color: var(--text-primary);
        }
        .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1); }
        .form-label { font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 3px; display: block; }
        
        /* ===== TABLES ===== */
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
        .data-table th {
            text-align: left; padding: 6px 10px;
            font-weight: 600; color: var(--text-secondary);
            font-size: 0.6rem; text-transform: uppercase;
            border-bottom: 2px solid var(--border-color);
        }
        .data-table td { padding: 6px 10px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); }
        .data-table tr:hover td { background: var(--bg-body); }
        .status-badge {
            padding: 2px 10px; border-radius: 20px; font-size: 0.6rem; font-weight: 600;
        }
        .status-badge.pending { background: rgba(245, 158, 11, 0.12); color: #F59E0B; }
        .status-badge.dispensed { background: rgba(10, 168, 79, 0.12); color: #0AA84F; }
        .status-badge.paid { background: rgba(10, 168, 79, 0.12); color: #0AA84F; }
        .status-badge.low { background: rgba(239, 68, 68, 0.12); color: #EF4444; }
        .status-badge.expiry { background: rgba(245, 158, 11, 0.12); color: #F59E0B; }
        .status-badge.critical { background: rgba(239, 68, 68, 0.15); color: #EF4444; animation: pulse 2s infinite; }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        /* ===== FOOTER ===== */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper input { width: 160px; }
        }
        @media (max-width: 768px) {
            .summary-card .sc-number { font-size: 1.6rem; }
            .summary-card .sc-actions { flex-direction: column; }
            .summary-card .sc-actions .btn { width: 100%; justify-content: center; }
        }
        @media (max-width: 640px) {
            .top-nav .search-wrapper input { width: 100px; }
            .top-nav .branch-badge { font-size: 0.6rem; padding: 2px 8px; }
            .top-nav .datetime { display: none; }
            .main-content { padding: 10px; }
            .summary-card .sc-number { font-size: 1.4rem; }
            .top-nav .dark-toggle span { display: none; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        .animate-fade-in-up:nth-child(1) { animation-delay: 0.03s; }
        .animate-fade-in-up:nth-child(2) { animation-delay: 0.06s; }
        .animate-fade-in-up:nth-child(3) { animation-delay: 0.09s; }
        .animate-fade-in-up:nth-child(4) { animation-delay: 0.12s; }
        
        .spinner {
            display: inline-block; width: 14px; height: 14px;
            border: 2px solid var(--border-color); border-top-color: var(--primary);
            border-radius: 50%; animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .toast-custom {
            position: fixed; bottom: 24px; right: 24px;
            padding: 12px 18px; border-radius: 12px;
            z-index: 999; max-width: 360px;
            transform: translateY(100px); opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Inter', sans-serif;
            display: flex; align-items: center; gap: 10px;
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        
        /* Quick Reports Styles */
        .quick-report-btn {
            padding: 10px 18px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            font-size: 0.75rem;
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .quick-report-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .quick-report-btn.blue { background: rgba(11, 94, 215, 0.08); color: #0B5ED7; border: 1px solid rgba(11, 94, 215, 0.12); }
        .quick-report-btn.blue:hover { background: #0B5ED7; color: white; }
        .quick-report-btn.green { background: rgba(10, 168, 79, 0.08); color: #0AA84F; border: 1px solid rgba(10, 168, 79, 0.12); }
        .quick-report-btn.green:hover { background: #0AA84F; color: white; }
        .quick-report-btn.purple { background: rgba(139, 92, 246, 0.08); color: #8B5CF6; border: 1px solid rgba(139, 92, 246, 0.12); }
        .quick-report-btn.purple:hover { background: #8B5CF6; color: white; }
        .quick-report-btn.orange { background: rgba(245, 158, 11, 0.08); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.12); }
        .quick-report-btn.orange:hover { background: #F59E0B; color: white; }
        .quick-report-btn.red { background: rgba(239, 68, 68, 0.08); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.12); }
        .quick-report-btn.red:hover { background: #EF4444; color: white; }
        .quick-report-btn.teal { background: rgba(13, 148, 136, 0.08); color: #0D9488; border: 1px solid rgba(13, 148, 136, 0.12); }
        .quick-report-btn.teal:hover { background: #0D9488; color: white; }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SIDEBAR - BLUE WITH GREEN HOVER -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='<?= $logo_fallback ?>'">
            <div>
                <p class="font-bold text-base leading-tight">Braick Dispensary</p>
                <p class="text-xs opacity-80">Pharmacy</p>
                <span class="branch-badge"><i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?></span>
            </div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Main Menu</div>
        <a href="dashboard.php?branch=<?= $user_branch_id ?>" class="sidebar-link active"><i class="fas fa-home"></i> Dashboard</a>
        <a href="#add-medicine" class="sidebar-link"><i class="fas fa-plus-circle"></i> Add Medicine</a>
        <a href="#sell-medicine" class="sidebar-link"><i class="fas fa-cart-plus"></i> Sell Medicine</a>
        <a href="#inventory" class="sidebar-link"><i class="fas fa-cubes"></i> Inventory <span class="badge"><?= $available_items ?></span></a>
        <a href="#low-stock" class="sidebar-link"><i class="fas fa-exclamation-triangle"></i> Low Stock <span class="badge"><?= $low_stock_count ?></span></a>
        <a href="#near-expiry" class="sidebar-link"><i class="fas fa-clock"></i> Near Expiry <span class="badge"><?= $near_expiry_count ?></span></a>
        <a href="#prescriptions" class="sidebar-link"><i class="fas fa-prescription"></i> Prescriptions</a>
        <a href="#otc-sales" class="sidebar-link"><i class="fas fa-shopping-cart"></i> OTC Sales</a>
        <a href="reports.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="<?= $root_path ?>logout.php" class="sidebar-link" style="margin-top:8px;border-top:1px solid rgba(255,255,255,0.1);padding-top:12px;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</aside>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <div class="search-wrapper">
            <input type="text" id="searchInput" placeholder="Search medicine, prescription..." class="search-input">
            <button id="searchBtn" class="search-btn"><i class="fas fa-search"></i></button>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <span class="branch-badge">
            <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        <span class="datetime" id="currentDateTime"></span>
        <button id="darkModeToggle" class="dark-toggle">
            <i class="fas fa-moon" id="darkIcon"></i>
            <span id="darkText">Dark</span>
        </button>
        <button class="icon-btn" id="notifToggle">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        <button class="icon-btn">
            <i class="fas fa-envelope text-lg"></i>
        </button>
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='<?= $avatar_fallback ?>'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-primary">Pharmacy Dashboard</h1>
            <p class="text-sm text-secondary">Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Pharmacist') ?>! 
                <span class="inline-flex ml-2" style="background: rgba(11, 94, 215, 0.08); color: #0B5ED7; padding: 3px 14px; border-radius: 20px; font-size: 0.7rem; border: 1px solid rgba(11, 94, 215, 0.15);">
                    <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="#add-medicine" class="btn btn-primary btn-sm"><i class="fas fa-plus-circle"></i> Add Medicine</a>
            <a href="#sell-medicine" class="btn btn-secondary btn-sm"><i class="fas fa-cart-plus"></i> Sell</a>
            <button onclick="refreshData()" class="btn btn-outline btn-sm" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 4 SUMMARY CARDS (Blue, Green, Blue, Green) -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        
        <!-- CARD 1: Prescription Sales - BLUE -->
        <div class="summary-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Prescription Sales</p>
                    <p class="sc-number"><?= $prescription_total ?></p>
                    <div class="sc-revenue">TSh <?= number_format($prescription_revenue) ?></div>
                    <div class="sc-stats">
                        <span class="pending"><?= $prescription_pending ?></span> pending · 
                        <span class="dispensed"><?= $prescription_dispensed ?></span> dispensed
                    </div>
                </div>
                <div class="sc-icon"><i class="fas fa-prescription"></i></div>
            </div>
            <div class="sc-actions">
                <a href="#prescriptions" class="btn btn-primary">View Sales</a>
                <a href="#prescriptions" class="btn btn-outline">Dispensing Queue</a>
            </div>
        </div>
        
        <!-- CARD 2: OTC Sales - GREEN -->
        <div class="summary-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">OTC Sales</p>
                    <p class="sc-number"><?= $otc_transactions ?></p>
                    <div class="sc-revenue">TSh <?= number_format($otc_revenue) ?></div>
                    <div class="sc-stats">
                        <span><?= $otc_items ?></span> items sold
                    </div>
                </div>
                <div class="sc-icon"><i class="fas fa-shopping-cart"></i></div>
            </div>
            <div class="sc-actions">
                <a href="#otc-sales" class="btn btn-success">View OTC Sales</a>
                <a href="#sell-medicine" class="btn btn-outline">Open POS Sale</a>
            </div>
        </div>
        
        <!-- CARD 3: Available Medicines - BLUE -->
        <div class="summary-card blue2 animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Available Medicines</p>
                    <p class="sc-number"><?= $available_items ?></p>
                    <div class="sc-stats">
                        <span><?= number_format($total_quantity) ?></span> units in stock
                    </div>
                </div>
                <div class="sc-icon"><i class="fas fa-cubes"></i></div>
            </div>
            <div class="sc-actions">
                <a href="#inventory" class="btn btn-primary">View All Medicines</a>
            </div>
        </div>
        
        <!-- CARD 4: Stock Alerts - GREEN -->
        <div class="summary-card green2 animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="sc-label">Stock Alerts</p>
                    <p class="sc-number"><?= $low_stock_count + $near_expiry_count + $expired_count ?></p>
                    <div class="sc-stats">
                        <span class="low"><?= $low_stock_count ?></span> low stock · 
                        <span class="expiry"><?= $near_expiry_count ?></span> near expiry ·
                        <span class="low"><?= $expired_count ?></span> expired
                    </div>
                    <div class="mt-1 flex gap-2">
                        <span class="alert-badge low"><i class="fas fa-exclamation-circle"></i> Low Stock</span>
                        <span class="alert-badge expiry"><i class="fas fa-clock"></i> Near Expiry</span>
                    </div>
                </div>
                <div class="sc-icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
            <div class="sc-actions">
                <a href="#low-stock" class="btn btn-danger">View Alerts</a>
                <a href="#add-medicine" class="btn btn-outline">Add New Stock</a>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- ADD MEDICINE SECTION -->
    <!-- ================================================================ -->
    <div id="add-medicine" class="card mb-5">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-plus-circle mr-2"></i> Add New Medicine</h3>
            <span class="text-xs text-secondary">Add medicine to inventory</span>
        </div>
        
        <?php if ($add_medicine_error): ?>
            <div class="p-3 mb-3 bg-red-50 text-red-700 rounded-lg text-sm"><?= $add_medicine_error ?></div>
        <?php endif; ?>
        <?php if ($add_medicine_success): ?>
            <div class="p-3 mb-3 bg-green-50 text-green-700 rounded-lg text-sm"><?= $add_medicine_success ?></div>
        <?php endif; ?>
        
        <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="form-label">Medicine Name *</label>
                <input type="text" name="medication_name" class="form-input" placeholder="e.g. Paracetamol 500mg" required>
            </div>
            <div>
                <label class="form-label">Generic Name</label>
                <input type="text" name="generic_name" class="form-input" placeholder="e.g. Paracetamol">
            </div>
            <div>
                <label class="form-label">Category</label>
                <input type="text" name="category" class="form-input" placeholder="e.g. Pain Relief">
            </div>
            <div>
                <label class="form-label">Batch Number</label>
                <input type="text" name="batch_number" class="form-input" placeholder="e.g. BATCH-2026-001">
            </div>
            <div>
                <label class="form-label">Buying Price (TSh)</label>
                <input type="number" name="buying_price" class="form-input" placeholder="0" step="0.01">
            </div>
            <div>
                <label class="form-label">Selling Price (TSh)</label>
                <input type="number" name="selling_price" class="form-input" placeholder="0" step="0.01" required>
            </div>
            <div>
                <label class="form-label">Quantity *</label>
                <input type="number" name="quantity" class="form-input" placeholder="0" required>
            </div>
            <div>
                <label class="form-label">Reorder Level</label>
                <input type="number" name="reorder_level" class="form-input" placeholder="10" value="10">
            </div>
            <div>
                <label class="form-label">Expiry Date *</label>
                <input type="date" name="expiry_date" class="form-input" required>
            </div>
            <div>
                <label class="form-label">Storage Location</label>
                <input type="text" name="storage_location" class="form-input" placeholder="e.g. Shelf A, Rack 3">
            </div>
            <div class="sm:col-span-2">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-input" placeholder="Additional notes about this medicine">
            </div>
            <div class="flex items-end">
                <button type="submit" name="add_medicine" class="btn btn-primary w-full">
                    <i class="fas fa-save"></i> Save Medicine
                </button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- SELL MEDICINE SECTION -->
    <!-- ================================================================ -->
    <div id="sell-medicine" class="card mb-5">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-cart-plus mr-2"></i> Sell Medicine (OTC)</h3>
            <span class="text-xs text-secondary">Direct sale without prescription</span>
        </div>
        
        <?php if ($sell_error): ?>
            <div class="p-3 mb-3 bg-red-50 text-red-700 rounded-lg text-sm"><?= $sell_error ?></div>
        <?php endif; ?>
        <?php if ($sell_success): ?>
            <div class="p-3 mb-3 bg-green-50 text-green-700 rounded-lg text-sm"><?= $sell_success ?></div>
        <?php endif; ?>
        
        <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="form-label">Select Medicine *</label>
                <select name="medicine_id" class="form-input" required>
                    <option value="">-- Select Medicine --</option>
                    <?php foreach ($medicines_for_sell as $med): ?>
                        <option value="<?= $med['id'] ?>">
                            <?= htmlspecialchars($med['medication_name']) ?> (<?= $med['quantity'] ?> in stock) - TSh <?= number_format($med['selling_price']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Quantity *</label>
                <input type="number" name="quantity_sold" class="form-input" placeholder="0" min="1" required>
            </div>
            <div>
                <label class="form-label">Customer Name</label>
                <input type="text" name="customer_name" class="form-input" placeholder="Walk-in">
            </div>
            <div>
                <label class="form-label">Payment Method</label>
                <select name="payment_method" class="form-input">
                    <option value="cash">Cash</option>
                    <option value="mobile_money">Mobile Money</option>
                    <option value="bank">Bank</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" name="sell_medicine" class="btn btn-success w-full">
                    <i class="fas fa-check"></i> Complete Sale
                </button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- INVENTORY SECTION -->
    <!-- ================================================================ -->
    <div id="inventory" class="card mb-5">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-cubes mr-2"></i> Medicine Inventory</h3>
            <span class="text-xs text-secondary"><?= $available_items ?> items</span>
        </div>
        <div class="overflow-x-auto max-h-64 overflow-y-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Batch</th>
                        <th>Price (TSh)</th>
                        <th>Stock</th>
                        <th>Expiry</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($inventory_list) > 0): ?>
                        <?php foreach ($inventory_list as $item): ?>
                            <?php 
                                $expiry_status = '';
                                $days_left = (strtotime($item['expiry_date']) - time()) / (60 * 60 * 24);
                                if ($days_left < 0) $expiry_status = 'Expired';
                                elseif ($days_left < 30) $expiry_status = 'Near Expiry';
                                else $expiry_status = 'Good';
                                
                                $stock_status = $item['quantity'] <= $item['reorder_level'] ? 'Low Stock' : 'In Stock';
                            ?>
                            <tr>
                                <td class="font-medium text-sm"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= htmlspecialchars($item['batch_number'] ?? 'N/A') ?></td>
                                <td class="text-sm"><?= number_format($item['selling_price'] ?? 0) ?></td>
                                <td class="font-semibold <?= $item['quantity'] <= $item['reorder_level'] ? 'text-red-600' : '' ?>">
                                    <?= $item['quantity'] ?? 0 ?>
                                </td>
                                <td class="text-xs <?= $days_left < 30 ? 'text-red-600' : '' ?>">
                                    <?= date('M d, Y', strtotime($item['expiry_date'])) ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $stock_status == 'Low Stock' ? 'low' : 'paid' ?>">
                                        <?= $stock_status ?>
                                    </span>
                                    <?php if ($expiry_status == 'Near Expiry'): ?>
                                        <span class="status-badge expiry ml-1">Expiring</span>
                                    <?php elseif ($expiry_status == 'Expired'): ?>
                                        <span class="status-badge critical ml-1">Expired</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-secondary text-sm py-3">No medicines in inventory</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- LOW STOCK SECTION -->
    <!-- ================================================================ -->
    <div id="low-stock" class="card mb-5">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-exclamation-triangle text-danger mr-2"></i> Low Stock Medicines</h3>
            <span class="text-xs text-secondary"><?= $low_stock_count ?> alerts</span>
        </div>
        <div class="overflow-x-auto max-h-48 overflow-y-auto">
            <table class="data-table">
                <thead>
                    <tr><th>Medicine</th><th>Batch</th><th>Current Stock</th><th>Min Level</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if (count($low_stock_list) > 0): ?>
                        <?php foreach ($low_stock_list as $item): ?>
                            <tr>
                                <td class="font-medium text-sm"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= htmlspecialchars($item['batch_number'] ?? 'N/A') ?></td>
                                <td class="font-semibold text-red-600"><?= $item['quantity'] ?? 0 ?></td>
                                <td><?= $item['reorder_level'] ?? 0 ?></td>
                                <td><span class="status-badge critical">Critical</span></td>
                                <td>
                                    <a href="#add-medicine" class="btn btn-success btn-sm">
                                        <i class="fas fa-plus"></i> Restock
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-secondary text-sm py-3">All medicines are well stocked ✅</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- NEAR EXPIRY SECTION -->
    <!-- ================================================================ -->
    <div id="near-expiry" class="card mb-5">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-clock text-warning mr-2"></i> Near Expiry Medicines</h3>
            <span class="text-xs text-secondary"><?= $near_expiry_count ?> medicines</span>
        </div>
        <div class="overflow-x-auto max-h-48 overflow-y-auto">
            <table class="data-table">
                <thead>
                    <tr><th>Medicine</th><th>Batch</th><th>Expiry Date</th><th>Days Left</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php if (count($near_expiry_list) > 0): ?>
                        <?php foreach ($near_expiry_list as $item): ?>
                            <tr>
                                <td class="font-medium text-sm"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
                                <td class="text-xs"><?= htmlspecialchars($item['batch_number'] ?? 'N/A') ?></td>
                                <td class="text-xs font-semibold <?= ($item['days_remaining'] ?? 0) < 7 ? 'text-red-600' : 'text-yellow-600' ?>">
                                    <?= date('M d, Y', strtotime($item['expiry_date'])) ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= ($item['days_remaining'] ?? 0) < 7 ? 'critical' : 'expiry' ?>">
                                        <?= $item['days_remaining'] ?? 0 ?> days
                                    </span>
                                </td>
                                <td>
                                    <?php if (($item['days_remaining'] ?? 0) < 7): ?>
                                        <span class="status-badge critical">Expiring Soon</span>
                                    <?php else: ?>
                                        <span class="status-badge expiry">Warning</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-secondary text-sm py-3">No medicines near expiry ✅</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PRESCRIPTIONS & OTC SALES -->
    <!-- ================================================================ -->
    <div id="prescriptions" class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        
        <!-- Recent Prescriptions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-prescription mr-2"></i> Recent Prescriptions</h3>
                <a href="#" class="text-xs text-primary font-medium">View All →</a>
            </div>
            <div class="overflow-x-auto max-h-48 overflow-y-auto">
                <table class="data-table">
                    <thead>
                        <tr><th>Patient</th><th>Doctor</th><th>Items</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_prescriptions) > 0): ?>
                            <?php foreach ($recent_prescriptions as $prescription): ?>
                                <tr>
                                    <td class="text-sm"><?= htmlspecialchars($prescription['patient_name'] ?? 'Unknown') ?></td>
                                    <td class="text-xs"><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></td>
                                    <td class="text-xs"><?= $prescription['medicine_count'] ?? 0 ?></td>
                                    <td><span class="status-badge <?= $prescription['status'] ?>"><?= $prescription['status'] ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center text-secondary text-sm py-3">No prescriptions today</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent OTC Sales -->
        <div id="otc-sales" class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-shopping-cart mr-2"></i> Recent OTC Sales</h3>
                <a href="#" class="text-xs text-primary font-medium">View All →</a>
            </div>
            <div class="overflow-x-auto max-h-48 overflow-y-auto">
                <table class="data-table">
                    <thead>
                        <tr><th>Receipt #</th><th>Medicine</th><th>Amount</th><th>Method</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_otc_sales) > 0): ?>
                            <?php foreach ($recent_otc_sales as $sale): ?>
                                <tr>
                                    <td class="font-mono text-xs">#<?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></td>
                                    <td class="text-xs"><?= htmlspecialchars(substr($sale['medicines'] ?? '', 0, 20)) ?>...</td>
                                    <td class="font-semibold text-sm text-green-600">TSh <?= number_format($sale['total'] ?? 0) ?></td>
                                    <td class="text-xs"><?= htmlspecialchars($sale['payment_method'] ?? 'cash') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center text-secondary text-sm py-3">No OTC sales today</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- RECENT ACTIVITY -->
    <!-- ================================================================ -->
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-clock mr-2"></i> Recent Activity</h3>
            <a href="#" class="text-xs text-primary font-medium">View All →</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-1">
            <?php foreach ($recent_activities as $activity): ?>
                <div class="flex items-start gap-3 p-2 rounded-lg hover:bg-body transition">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5" style="background: rgba(11, 94, 215, 0.08); color: #0B5ED7;">
                        <i class="fas fa-circle text-[8px]"></i>
                    </div>
                    <div>
                        <p class="font-medium text-sm text-primary"><?= htmlspecialchars($activity['action']) ?></p>
                        <p class="text-xs text-secondary"><?= htmlspecialchars($activity['details']) ?></p>
                        <p class="text-[10px] text-secondary mt-0.5"><?= time_ago($activity['created_at']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK REPORTS - STYLED -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-file-alt mr-2"></i> Quick Reports</h3>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="reports.php?type=daily_prescription&branch=<?= $user_branch_id ?>" class="quick-report-btn blue">
                <i class="fas fa-prescription"></i> Prescription Sales
            </a>
            <a href="reports.php?type=daily_otc&branch=<?= $user_branch_id ?>" class="quick-report-btn green">
                <i class="fas fa-shopping-cart"></i> OTC Sales
            </a>
            <a href="reports.php?type=inventory&branch=<?= $user_branch_id ?>" class="quick-report-btn blue">
                <i class="fas fa-cubes"></i> Inventory
            </a>
            <a href="reports.php?type=low_stock&branch=<?= $user_branch_id ?>" class="quick-report-btn green">
                <i class="fas fa-exclamation-triangle"></i> Low Stock
            </a>
            <a href="reports.php?type=expiry&branch=<?= $user_branch_id ?>" class="quick-report-btn blue">
                <i class="fas fa-clock"></i> Near Expiry
            </a>
            <a href="reports.php?type=revenue&branch=<?= $user_branch_id ?>" class="quick-report-btn green">
                <i class="fas fa-money-bill-wave"></i> Revenue
            </a>
            <div class="flex-1"></div>
            <button onclick="downloadPDF()" class="btn btn-primary btn-sm"><i class="fas fa-file-pdf"></i> PDF</button>
            <button onclick="exportExcel()" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Excel</button>
            <button onclick="window.print()" class="btn btn-outline btn-sm"><i class="fas fa-print"></i> Print</button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p class="font-medium text-sm">Braick Dispensary Management System</p>
        <p class="text-xs">Pharmacy Dashboard v2.0 &copy; <?= date('Y') ?> All rights reserved</p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const darkToggle = document.getElementById('darkModeToggle');
    const darkIcon = document.getElementById('darkIcon');
    const darkText = document.getElementById('darkText');
    const searchBtn = document.getElementById('searchBtn');
    const searchInput = document.getElementById('searchInput');
    const refreshBtn = document.getElementById('refreshBtn');

    sidebarToggle?.addEventListener('click', () => {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    let isDark = false;
    darkToggle?.addEventListener('click', () => {
        isDark = !isDark;
        const html = document.getElementById('htmlRoot');
        if (isDark) {
            html.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
        } else {
            html.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
        }
        localStorage.setItem('darkMode', isDark ? 'true' : 'false');
    });
    
    if (localStorage.getItem('darkMode') === 'true') {
        isDark = true;
        document.getElementById('htmlRoot').setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }

    function performSearch() {
        const query = searchInput.value.trim();
        if (query.length > 0) {
            showToast('Search', 'Searching for: "' + query + '"', 'info');
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
        }
    }
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') performSearch();
    });

    function refreshData() {
        if (refreshBtn) {
            refreshBtn.innerHTML = '<span class="spinner"></span> Refreshing...';
            refreshBtn.disabled = true;
        }
        setTimeout(() => { location.reload(); }, 800);
    }

    function showToast(title, message, type = 'info') {
        const existing = document.querySelector('.toast-custom');
        if (existing) existing.remove();
        const colors = {
            info: { bg: '#0B5ED7', icon: 'fa-info-circle' },
            success: { bg: '#0AA84F', icon: 'fa-check-circle' },
            error: { bg: '#EF4444', icon: 'fa-exclamation-circle' },
            warning: { bg: '#F59E0B', icon: 'fa-exclamation-triangle' }
        };
        const style = colors[type] || colors.info;
        const toast = document.createElement('div');
        toast.className = 'toast-custom';
        toast.style.cssText = `
            background: ${style.bg}; color: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        `;
        toast.innerHTML = `
            <i class="fas ${style.icon}" style="font-size:1.1rem;"></i>
            <div>
                <p style="font-weight:600;font-size:0.85rem;margin:0;">${title}</p>
                <p style="font-size:0.75rem;opacity:0.9;margin:0;">${message}</p>
            </div>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 50);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 400);
        }, 3500);
    }

    function downloadPDF() {
        showToast('Downloading PDF', 'Generating PDF report...', 'info');
        window.location.href = 'reports.php?export=pdf&branch=<?= $user_branch_id ?>';
    }
    function exportExcel() {
        showToast('Exporting Excel', 'Preparing Excel export...', 'info');
        window.location.href = 'reports.php?export=excel&branch=<?= $user_branch_id ?>';
    }

    function updateDateTime() {
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        const timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            showToast('Welcome', 'Pharmacy Dashboard loaded successfully', 'success');
        }, 300);
    });

    console.log('%c🏥 Braick Dispensary - Pharmacy Dashboard', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c💊 Pharmacist: <?= htmlspecialchars($_SESSION['full_name'] ?? 'Pharmacist') ?>', 'font-size:13px; color:#0AA84F;');
    console.log('%c🏛️ Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:12px; color:#64748B;');
</script>

</body>
</html>