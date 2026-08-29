<?php
// ================================================================
// FILE: frontend/pages/cashier/dashboard.php
// CASHIER DASHBOARD - FULL FIXED
// Uses: bills, bill_items, prescriptions, lab_tests
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// ALLOWED ROLES: Cashier, Reception, Admin
// ================================================================
$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$cashier_id = $_SESSION['user_id'];
$cashier_name = $_SESSION['full_name'] ?? 'User';
$cashier_username = $_SESSION['username'] ?? '';
$cashier_role = $_SESSION['role'] ?? 'cashier';
$cashier_branch_id = $_SESSION['branch_id'] ?? 1;
$cashier_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$cashier_email = $_SESSION['email'] ?? '';
$cashier_phone = $_SESSION['phone'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// CHECK IF USER IS RECEPTIONIST (for display message)
// ================================================================
$is_reception = ($cashier_role === 'reception');

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET CASHIER STATISTICS - USING CORRECT TABLES
// ================================================================
$today = date('Y-m-d');
$unread_notifications = 0;

try {
    // 1. Unread Notifications
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$cashier_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // ================================================================
    // 1. TODAY PAYMENTS (from bills with paid_amount > 0 today)
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? 
        AND DATE(updated_at) = ?
        AND paid_amount > 0
        AND status IN ('paid', 'partial')
    ");
    $stmt->execute([$cashier_branch_id, $today]);
    $today_payments_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_payments_count = $today_payments_data['count'] ?? 0;
    $today_payments_total = $today_payments_data['total'] ?? 0;
    
    // ================================================================
    // 2. PENDING BILLS (status = 'pending')
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? 
        AND status = 'pending'
    ");
    $stmt->execute([$cashier_branch_id]);
    $pending_bills_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_bills = $pending_bills_data['count'] ?? 0;
    $pending_bills_total = $pending_bills_data['total'] ?? 0;
    
    // ================================================================
    // 3. CANCELLED BILLS (status = 'cancelled')
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? 
        AND status = 'cancelled'
    ");
    $stmt->execute([$cashier_branch_id]);
    $cancelled_bills_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $cancelled_bills = $cancelled_bills_data['count'] ?? 0;
    $cancelled_bills_total = $cancelled_bills_data['total'] ?? 0;
    
    // ================================================================
    // 4. TOTAL BILLS (all bills)
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
        FROM bills 
        WHERE branch_id = ?
    ");
    $stmt->execute([$cashier_branch_id]);
    $total_bills_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_bills = $total_bills_data['count'] ?? 0;
    $total_bills_amount = $total_bills_data['total'] ?? 0;
    
    // ================================================================
    // 5. PAID BILLS (status = 'paid')
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? 
        AND status = 'paid'
    ");
    $stmt->execute([$cashier_branch_id]);
    $paid_bills_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $paid_bills = $paid_bills_data['count'] ?? 0;
    $paid_bills_total = $paid_bills_data['total'] ?? 0;
    
    // ================================================================
    // 6. PARTIAL PAYMENTS (status = 'partial')
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total_paid, COALESCE(SUM(balance), 0) as total_balance
        FROM bills 
        WHERE branch_id = ? 
        AND status = 'partial'
    ");
    $stmt->execute([$cashier_branch_id]);
    $partial_bills_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $partial_bills = $partial_bills_data['count'] ?? 0;
    $partial_bills_paid = $partial_bills_data['total_paid'] ?? 0;
    $partial_bills_balance = $partial_bills_data['total_balance'] ?? 0;
    
    // ================================================================
    // 7. EXPENSES (from expenses table)
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
        FROM expenses 
        WHERE branch_id = ? 
        AND status = 'paid'
    ");
    $stmt->execute([$cashier_branch_id]);
    $expenses_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $expenses_count = $expenses_data['count'] ?? 0;
    $expenses_total = $expenses_data['total'] ?? 0;
    
    // Today's expenses
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
        FROM expenses 
        WHERE branch_id = ? 
        AND DATE(payment_date) = ?
        AND status = 'paid'
    ");
    $stmt->execute([$cashier_branch_id, $today]);
    $today_expenses_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_expenses = $today_expenses_data['total'] ?? 0;
    
    // ================================================================
    // 8. PAYMENT HISTORY (Recent payments from bills)
    // ================================================================
    $stmt = $db->prepare("
        SELECT 
            b.id as bill_id,
            b.bill_number,
            b.patient_id,
            b.total_amount,
            b.paid_amount,
            b.balance,
            b.status,
            b.payment_method,
            b.updated_at,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            u.full_name as cashier_name
        FROM bills b
        JOIN patients p ON b.patient_id = p.id
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.branch_id = ?
        AND b.paid_amount > 0
        ORDER BY b.updated_at DESC
        LIMIT 15
    ");
    $stmt->execute([$cashier_branch_id]);
    $payment_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // ADDITIONAL: Payment Methods Today
    // ================================================================
    $stmt = $db->prepare("
        SELECT 
            payment_method,
            COUNT(*) as count,
            COALESCE(SUM(paid_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? 
        AND DATE(updated_at) = ?
        AND paid_amount > 0
        AND status IN ('paid', 'partial')
        GROUP BY payment_method
    ");
    $stmt->execute([$cashier_branch_id, $today]);
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // ADDITIONAL: Today's Bill Items Summary
    // ================================================================
    $stmt = $db->prepare("
        SELECT 
            bi.item_type,
            COUNT(DISTINCT bi.bill_id) as bill_count,
            COUNT(bi.id) as item_count,
            COALESCE(SUM(bi.final_price), 0) as total_amount
        FROM bill_items bi
        JOIN bills b ON bi.bill_id = b.id
        WHERE b.branch_id = ?
        AND DATE(b.created_at) = ?
        GROUP BY bi.item_type
        ORDER BY total_amount DESC
    ");
    $stmt->execute([$cashier_branch_id, $today]);
    $today_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // ADDITIONAL: Monthly Revenue
    // ================================================================
    $month_start = date('Y-m-01');
    $stmt = $db->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as bill_count,
            COALESCE(SUM(total_amount), 0) as total_amount,
            COALESCE(SUM(paid_amount), 0) as paid_amount
        FROM bills 
        WHERE branch_id = ?
        AND created_at >= ?
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at) ASC
    ");
    $stmt->execute([$cashier_branch_id, $month_start]);
    $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Cashier dashboard error: " . $e->getMessage());
    $pending_bills = 0;
    $pending_bills_total = 0;
    $today_payments_count = 0;
    $today_payments_total = 0;
    $total_bills = 0;
    $total_bills_amount = 0;
    $paid_bills = 0;
    $paid_bills_total = 0;
    $cancelled_bills = 0;
    $cancelled_bills_total = 0;
    $partial_bills = 0;
    $partial_bills_paid = 0;
    $partial_bills_balance = 0;
    $expenses_count = 0;
    $expenses_total = 0;
    $today_expenses = 0;
    $payment_history = [];
    $payment_methods = [];
    $today_items = [];
    $monthly_data = [];
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// TIME AGO FUNCTION
// ================================================================
function time_ago($timestamp) {
    if (empty($timestamp)) return 'N/A';
    $now = new DateTime();
    $past = new DateTime($timestamp);
    $diff = $now->diff($past);
    
    if ($diff->days > 7) return date('M d, Y', strtotime($timestamp));
    if ($diff->days > 0) return $diff->days . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'Just now';
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER - GREEN BACKGROUND -->
    <!-- ================================================================ -->
    <div class="page-header-green" style="background:linear-gradient(135deg, #059669, #047857);border-radius:16px;padding:24px 32px;margin-bottom:28px;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:16px;box-shadow:0 4px 25px rgba(5,150,105,0.3);">
        <div>
            <h1 class="page-title" style="font-size:1.8rem;font-weight:700;color:white;display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:0;">
                <i class="fas fa-cash-register" style="color:rgba(255,255,255,0.9);"></i>
                Cashier Dashboard
                <span class="branch-tag" style="background:rgba(255,255,255,0.2);color:white;padding:4px 16px;border-radius:20px;font-size:0.7rem;font-weight:600;border:1px solid rgba(255,255,255,0.15);">
                    <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($cashier_branch_name) ?>
                </span>
                <span class="role-tag" style="background:rgba(255,255,255,0.2);color:white;padding:4px 16px;border-radius:20px;font-size:0.7rem;font-weight:600;border:1px solid rgba(255,255,255,0.15);">
                    <i class="fas fa-user mr-1"></i> <?= strtoupper($cashier_role) ?>
                </span>
                <?php if ($is_reception): ?>
                    <span class="reception-view-badge" style="background:rgba(251,191,36,0.3);color:#FCD34D;padding:4px 16px;border-radius:20px;font-size:0.6rem;font-weight:600;border:1px solid rgba(251,191,36,0.3);">
                        <i class="fas fa-eye"></i> View Only (Reception)
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle" style="color:rgba(255,255,255,0.85);font-size:0.95rem;margin-top:6px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <i class="fas fa-user-check" style="color:rgba(255,255,255,0.7);"></i>
                Welcome back, <strong style="color:white;font-weight:600;"><?= htmlspecialchars($cashier_name) ?></strong>
                <span style="color:rgba(255,255,255,0.3);margin:0 4px;">|</span>
                <span style="color:rgba(255,255,255,0.8);"><i class="far fa-calendar-alt"></i> <?= date('F d, Y') ?></span>
                <span style="color:rgba(255,255,255,0.3);margin:0 4px;">|</span>
                <span style="color:#FCD34D;font-weight:600;"><i class="fas fa-clock"></i> <span id="pendingCount"><?= $pending_bills ?></span> Pending Bills</span>
                <span style="color:rgba(255,255,255,0.3);margin:0 4px;">|</span>
                <span style="color:#34D399;font-size:0.8rem;" id="liveIndicator">
                    <i class="fas fa-circle" style="color:#34D399;font-size:0.5rem;display:inline-block;animation:pulse-dot 1.5s infinite;"></i>
                    Live
                </span>
            </p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="../reception/dashboard.php" class="btn-reception-custom" style="display:inline-flex;align-items:center;gap:8px;padding:10px 22px;background:rgba(255,255,255,0.2);color:white;border:1px solid rgba(255,255,255,0.3);border-radius:12px;font-weight:600;font-size:0.82rem;text-decoration:none;transition:all 0.3s;cursor:pointer;">
                <i class="fas fa-arrow-left"></i> Reception Dashboard
            </a>
            <a href="pending_bills.php" class="btn-primary-custom" style="display:inline-flex;align-items:center;gap:8px;padding:10px 22px;background:white;color:#059669;border-radius:12px;font-weight:600;font-size:0.82rem;text-decoration:none;transition:all 0.3s;border:none;cursor:pointer;box-shadow:0 4px 15px rgba(0,0,0,0.1);">
                <i class="fas fa-receipt"></i> Pending Bills
            </a>
            <button onclick="manualRefresh()" class="btn-refresh" id="refreshBtn" style="display:inline-flex;align-items:center;gap:8px;padding:10px 22px;background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.2);border-radius:12px;font-weight:600;font-size:0.82rem;cursor:pointer;transition:all 0.3s;">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 8 STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:14px;margin-bottom:28px;">
        
        <!-- Card 1: Today Payments -->
        <div class="stat-card-modern" onclick="window.location.href='payment_history.php'" style="background:linear-gradient(135deg, #0B5ED7, #0A4CA8);border-radius:16px;padding:18px 20px;color:white;position:relative;overflow:hidden;cursor:pointer;transition:all 0.3s ease;box-shadow:0 4px 20px rgba(11,94,215,0.25);">
            <div style="position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,0.06);"></div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;position:relative;z-index:1;">
                <div>
                    <div class="stat-number" id="statTodayPayments" style="font-size:2rem;font-weight:700;line-height:1.2;letter-spacing:-0.02em;"><?= number_format($today_payments_count) ?></div>
                    <div class="stat-label" style="font-size:0.7rem;color:rgba(255,255,255,0.85);font-weight:500;margin-top:2px;">Today Payments</div>
                    <div style="font-size:0.6rem;color:rgba(255,255,255,0.6);margin-top:2px;">TSh <?= number_format($today_payments_total) ?></div>
                </div>
                <div style="width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.1rem;backdrop-filter:blur(4px);">
                    <i class="fas fa-credit-card"></i>
                </div>
            </div>
            <div style="margin-top:10px;font-size:0.55rem;color:rgba(255,255,255,0.5);display:flex;align-items:center;gap:4px;">
                <span class="live-dot" style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#34D399;animation:pulse-dot 1.5s infinite;"></span>
                Live
            </div>
        </div>
        
        <!-- Card 2: Pending Bills -->
        <div class="stat-card-modern" onclick="window.location.href='pending_bills.php'" style="background:linear-gradient(135deg, #DC2626, #B91C1C);border-radius:16px;padding:18px 20px;color:white;position:relative;overflow:hidden;cursor:pointer;transition:all 0.3s ease;box-shadow:0 4px 20px rgba(220,38,38,0.25);">
            <div style="position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,0.06);"></div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;position:relative;z-index:1;">
                <div>
                    <div class="stat-number" id="statPending" style="font-size:2rem;font-weight:700;line-height:1.2;letter-spacing:-0.02em;"><?= number_format($pending_bills) ?></div>
                    <div class="stat-label" style="font-size:0.7rem;color:rgba(255,255,255,0.85);font-weight:500;margin-top:2px;">Pending Bills</div>
                    <div style="font-size:0.6rem;color:rgba(255,255,255,0.6);margin-top:2px;">TSh <?= number_format($pending_bills_total) ?></div>
                </div>
                <div style="width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.1rem;backdrop-filter:blur(4px);">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div style="margin-top:10px;font-size:0.55rem;color:rgba(255,255,255,0.5);display:flex;align-items:center;gap:4px;">
                <span class="live-dot" style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#34D399;animation:pulse-dot 1.5s infinite;"></span>
                Live
            </div>
        </div>
        
        <!-- Card 3: Cancelled Bills -->
        <div class="stat-card-modern" onclick="window.location.href='cancelled_bills.php'" style="background:linear-gradient(135deg, #6B7280, #4B5563);border-radius:16px;padding:18px 20px;color:white;position:relative;overflow:hidden;cursor:pointer;transition:all 0.3s ease;box-shadow:0 4px 20px rgba(107,114,128,0.25);">
            <div style="position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,0.06);"></div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;position:relative;z-index:1;">
                <div>
                    <div class="stat-number" id="statCancelled" style="font-size:2rem;font-weight:700;line-height:1.2;letter-spacing:-0.02em;"><?= number_format($cancelled_bills) ?></div>
                    <div class="stat-label" style="font-size:0.7rem;color:rgba(255,255,255,0.85);font-weight:500;margin-top:2px;">Cancelled Bills</div>
                    <div style="font-size:0.6rem;color:rgba(255,255,255,0.6);margin-top:2px;">TSh <?= number_format($cancelled_bills_total) ?></div>
                </div>
                <div style="width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.1rem;backdrop-filter:blur(4px);">
                    <i class="fas fa-ban"></i>
                </div>
            </div>
            <div style="margin-top:10px;font-size:0.55rem;color:rgba(255,255,255,0.5);display:flex;align-items:center;gap:4px;">
                <span class="live-dot" style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#34D399;animation:pulse-dot 1.5s infinite;"></span>
                Live
            </div>
        </div>
        
        <!-- Card 4: Total Bills -->
        <div class="stat-card-modern" onclick="window.location.href='all_bills.php'" style="background:linear-gradient(135deg, #D97706, #B45309);border-radius:16px;padding:18px 20px;color:white;position:relative;overflow:hidden;cursor:pointer;transition:all 0.3s ease;box-shadow:0 4px 20px rgba(217,119,6,0.25);">
            <div style="position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,0.06);"></div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;position:relative;z-index:1;">
                <div>
                    <div class="stat-number" id="statTotal" style="font-size:2rem;font-weight:700;line-height:1.2;letter-spacing:-0.02em;"><?= number_format($total_bills) ?></div>
                    <div class="stat-label" style="font-size:0.7rem;color:rgba(255,255,255,0.85);font-weight:500;margin-top:2px;">Total Bills</div>
                    <div style="font-size:0.6rem;color:rgba(255,255,255,0.6);margin-top:2px;">TSh <?= number_format($total_bills_amount) ?></div>
                </div>
                <div style="width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.1rem;backdrop-filter:blur(4px);">
                    <i class="fas fa-file-invoice"></i>
                </div>
            </div>
            <div style="margin-top:10px;font-size:0.55rem;color:rgba(255,255,255,0.5);display:flex;align-items:center;gap:4px;">
                <span class="live-dot" style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#34D399;animation:pulse-dot 1.5s infinite;"></span>
                Live
            </div>
        </div>
        
        <!-- Card 5: Paid Bills -->
        <div class="stat-card-modern" onclick="window.location.href='paid_bills.php'" style="background:linear-gradient(135deg, #059669, #047857);border-radius:16px;padding:18px 20px;color:white;position:relative;overflow:hidden;cursor:pointer;transition:all 0.3s ease;box-shadow:0 4px 20px rgba(5,150,105,0.25);">
            <div style="position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,0.06);"></div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;position:relative;z-index:1;">
                <div>
                    <div class="stat-number" id="statPaid" style="font-size:2rem;font-weight:700;line-height:1.2;letter-spacing:-0.02em;"><?= number_format($paid_bills) ?></div>
                    <div class="stat-label" style="font-size:0.7rem;color:rgba(255,255,255,0.85);font-weight:500;margin-top:2px;">Paid Bills</div>
                    <div style="font-size:0.6rem;color:rgba(255,255,255,0.6);margin-top:2px;">TSh <?= number_format($paid_bills_total) ?></div>
                </div>
                <div style="width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.1rem;backdrop-filter:blur(4px);">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <div style="margin-top:10px;font-size:0.55rem;color:rgba(255,255,255,0.5);display:flex;align-items:center;gap:4px;">
                <span class="live-dot" style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#34D399;animation:pulse-dot 1.5s infinite;"></span>
                Live
            </div>
        </div>
        
        <!-- Card 6: Partial Payments -->
        <div class="stat-card-modern" onclick="window.location.href='partial_payments.php'" style="background:linear-gradient(135deg, #7C3AED, #6D28D9);border-radius:16px;padding:18px 20px;color:white;position:relative;overflow:hidden;cursor:pointer;transition:all 0.3s ease;box-shadow:0 4px 20px rgba(124,58,237,0.25);">
            <div style="position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,0.06);"></div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;position:relative;z-index:1;">
                <div>
                    <div class="stat-number" id="statPartial" style="font-size:2rem;font-weight:700;line-height:1.2;letter-spacing:-0.02em;"><?= number_format($partial_bills) ?></div>
                    <div class="stat-label" style="font-size:0.7rem;color:rgba(255,255,255,0.85);font-weight:500;margin-top:2px;">Partial Payments</div>
                    <div style="font-size:0.6rem;color:rgba(255,255,255,0.6);margin-top:2px;">Paid: TSh <?= number_format($partial_bills_paid) ?> | Bal: TSh <?= number_format($partial_bills_balance) ?></div>
                </div>
                <div style="width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.1rem;backdrop-filter:blur(4px);">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
            </div>
            <div style="margin-top:10px;font-size:0.55rem;color:rgba(255,255,255,0.5);display:flex;align-items:center;gap:4px;">
                <span class="live-dot" style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#34D399;animation:pulse-dot 1.5s infinite;"></span>
                Live
            </div>
        </div>
        
        <!-- Card 7: Expenses -->
        <div class="stat-card-modern" onclick="window.location.href='expenses.php'" style="background:linear-gradient(135deg, #E11D48, #BE123C);border-radius:16px;padding:18px 20px;color:white;position:relative;overflow:hidden;cursor:pointer;transition:all 0.3s ease;box-shadow:0 4px 20px rgba(225,29,72,0.25);">
            <div style="position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,0.06);"></div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;position:relative;z-index:1;">
                <div>
                    <div class="stat-number" id="statExpenses" style="font-size:2rem;font-weight:700;line-height:1.2;letter-spacing:-0.02em;"><?= number_format($expenses_count) ?></div>
                    <div class="stat-label" style="font-size:0.7rem;color:rgba(255,255,255,0.85);font-weight:500;margin-top:2px;">Total Expenses</div>
                    <div style="font-size:0.6rem;color:rgba(255,255,255,0.6);margin-top:2px;">TSh <?= number_format($expenses_total) ?> | Today: TSh <?= number_format($today_expenses) ?></div>
                </div>
                <div style="width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.1rem;backdrop-filter:blur(4px);">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
            <div style="margin-top:10px;font-size:0.55rem;color:rgba(255,255,255,0.5);display:flex;align-items:center;gap:4px;">
                <span class="live-dot" style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#34D399;animation:pulse-dot 1.5s infinite;"></span>
                Live
            </div>
        </div>
        
        <!-- Card 8: Payment History -->
        <div class="stat-card-modern" onclick="window.location.href='payment_history.php'" style="background:linear-gradient(135deg, #0D9488, #0F766E);border-radius:16px;padding:18px 20px;color:white;position:relative;overflow:hidden;cursor:pointer;transition:all 0.3s ease;box-shadow:0 4px 20px rgba(13,148,136,0.25);">
            <div style="position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,0.06);"></div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;position:relative;z-index:1;">
                <div>
                    <div class="stat-number" id="statHistory" style="font-size:2rem;font-weight:700;line-height:1.2;letter-spacing:-0.02em;"><?= number_format(count($payment_history)) ?></div>
                    <div class="stat-label" style="font-size:0.7rem;color:rgba(255,255,255,0.85);font-weight:500;margin-top:2px;">Recent Payments</div>
                    <div style="font-size:0.6rem;color:rgba(255,255,255,0.6);margin-top:2px;">Last 15 transactions</div>
                </div>
                <div style="width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.1rem;backdrop-filter:blur(4px);">
                    <i class="fas fa-history"></i>
                </div>
            </div>
            <div style="margin-top:10px;font-size:0.55rem;color:rgba(255,255,255,0.5);display:flex;align-items:center;gap:4px;">
                <span class="live-dot" style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#34D399;animation:pulse-dot 1.5s infinite;"></span>
                Live
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- PAYMENT HISTORY TABLE -->
    <!-- ================================================================ -->
    <div class="card-modern" style="background:var(--bg-card);border-radius:16px;padding:22px 24px;border:2px solid var(--border-color);margin-bottom:24px;transition:all 0.3s;box-shadow:0 2px 10px rgba(0,0,0,0.04);">
        <div class="card-header-modern" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
            <h3 class="card-title" style="font-size:0.95rem;font-weight:600;color:var(--text-primary);display:flex;align-items:center;gap:10px;">
                <i class="fas fa-history" style="color:var(--success);"></i>
                Payment History
                <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);background:var(--bg-body);padding:2px 12px;border-radius:20px;">Last 15 transactions</span>
            </h3>
            <a href="payment_history.php" style="color:var(--success);font-size:0.85rem;text-decoration:none;font-weight:500;transition:all 0.3s;display:flex;align-items:center;gap:4px;">
                View All <i class="fas fa-arrow-right" style="font-size:0.7rem;"></i>
            </a>
        </div>
        
        <div class="scroll-container" style="max-height:350px;overflow-y:auto;padding-right:4px;" id="paymentHistoryList">
            <?php if (count($payment_history) > 0): ?>
                <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                    <thead style="background:var(--gray-50);border-radius:8px;border-bottom:2px solid var(--border-color);">
                        <tr>
                            <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;letter-spacing:0.03em;">Bill #</th>
                            <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;letter-spacing:0.03em;">Patient</th>
                            <th style="padding:8px 12px;text-align:right;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;letter-spacing:0.03em;">Amount</th>
                            <th style="padding:8px 12px;text-align:right;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;letter-spacing:0.03em;">Paid</th>
                            <th style="padding:8px 12px;text-align:right;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;letter-spacing:0.03em;">Balance</th>
                            <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;letter-spacing:0.03em;">Status</th>
                            <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;letter-spacing:0.03em;">Method</th>
                            <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;letter-spacing:0.03em;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payment_history as $payment): 
                            $status = $payment['status'] ?? 'pending';
                            $status_color = $status === 'paid' ? '#059669' : ($status === 'partial' ? '#D97706' : '#DC2626');
                            $status_bg = $status === 'paid' ? '#D1FAE5' : ($status === 'partial' ? '#FEF3C7' : '#FEE2E2');
                            $method_icon = ($payment['payment_method'] ?? 'cash') === 'cash' ? '💵' : '📱';
                        ?>
                            <tr style="border-bottom:1px solid var(--border-color);transition:all 0.2s;">
                                <td style="padding:8px 12px;font-weight:500;color:var(--text-primary);">
                                    <a href="view_bill.php?id=<?= $payment['bill_id'] ?>" style="color:var(--success);text-decoration:none;"><?= htmlspecialchars($payment['bill_number']) ?></a>
                                </td>
                                <td style="padding:8px 12px;color:var(--text-primary);">
                                    <div style="font-weight:500;"><?= htmlspecialchars($payment['patient_name'] ?? 'N/A') ?></div>
                                    <div style="font-size:0.6rem;color:var(--text-secondary);"><?= htmlspecialchars($payment['patient_code'] ?? 'N/A') ?></div>
                                </td>
                                <td style="padding:8px 12px;text-align:right;font-weight:500;color:var(--text-secondary);">TSh <?= number_format($payment['total_amount'] ?? 0) ?></td>
                                <td style="padding:8px 12px;text-align:right;font-weight:600;color:var(--success);">TSh <?= number_format($payment['paid_amount'] ?? 0) ?></td>
                                <td style="padding:8px 12px;text-align:right;font-weight:500;color:<?= ($payment['balance'] ?? 0) > 0 ? '#DC2626' : 'var(--text-secondary)' ?>;">
                                    TSh <?= number_format($payment['balance'] ?? 0) ?>
                                </td>
                                <td style="padding:8px 12px;text-align:center;">
                                    <span style="display:inline-block;padding:2px 12px;border-radius:12px;font-size:0.6rem;font-weight:600;background:<?= $status_bg ?>;color:<?= $status_color ?>;border:1px solid <?= $status_color ?>20;">
                                        <?= strtoupper($status) ?>
                                    </span>
                                </td>
                                <td style="padding:8px 12px;text-align:center;font-size:0.8rem;"><?= $method_icon ?></td>
                                <td style="padding:8px 12px;text-align:center;font-size:0.65rem;color:var(--text-secondary);"><?= time_ago($payment['updated_at'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align:center;padding:40px 20px;color:var(--text-secondary);">
                    <i class="fas fa-receipt" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:0.2;"></i>
                    <p style="font-size:0.95rem;font-weight:500;">No payment history found</p>
                    <p style="font-size:0.8rem;opacity:0.6;margin-top:4px;">Payments will appear here</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TWO COLUMN GRID: Payment Methods + Today's Items -->
    <!-- ================================================================ -->
    <div class="two-col-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
        
        <!-- Payment Methods Today -->
        <div class="card-modern" style="background:var(--bg-card);border-radius:16px;padding:20px 22px;border:2px solid var(--border-color);box-shadow:0 2px 10px rgba(0,0,0,0.04);">
            <div class="card-header-modern" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                <h3 class="card-title" style="font-size:0.9rem;font-weight:600;color:var(--text-primary);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-chart-pie" style="color:var(--success);"></i>
                    Payment Methods Today
                </h3>
                <span style="font-size:0.6rem;color:var(--text-secondary);background:var(--bg-body);padding:2px 10px;border-radius:20px;"><?= date('M d, Y') ?></span>
            </div>
            <div id="paymentMethods">
                <?php if (count($payment_methods) > 0): ?>
                    <?php 
                        $methodIcons = [
                            'cash' => '💵',
                            'm-pesa' => '📱',
                            'airtel_money' => '📱',
                            'tigo_pesa' => '📱',
                            'halopesa' => '📱',
                            'card' => '💳',
                            'bank' => '🏦',
                            'insurance' => '🏥',
                            'other' => '📦'
                        ];
                        $methodColors = [
                            'cash' => '#059669',
                            'm-pesa' => '#0B5ED7',
                            'airtel_money' => '#DC2626',
                            'tigo_pesa' => '#D97706',
                            'halopesa' => '#7C3AED',
                            'card' => '#0D9488',
                            'bank' => '#4B5563',
                            'insurance' => '#0891B2',
                            'other' => '#6B7280'
                        ];
                    ?>
                    <?php foreach ($payment_methods as $method): ?>
                        <div class="method-item" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border-bottom:1px solid var(--border-color);">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.8rem;background:<?= $methodColors[$method['payment_method']] ?? '#6B7280' ?>20;color:<?= $methodColors[$method['payment_method']] ?? '#6B7280' ?>;">
                                    <?= $methodIcons[$method['payment_method']] ?? '💵' ?>
                                </div>
                                <span style="font-size:0.8rem;font-weight:500;color:var(--text-primary);text-transform:uppercase;"><?= htmlspecialchars($method['payment_method'] ?? 'CASH') ?></span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-size:0.65rem;color:var(--text-secondary);"><?= $method['count'] ?> payments</span>
                                <span style="font-weight:600;font-size:0.85rem;color:var(--success);">TSh <?= number_format($method['total'] ?? 0) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center;padding:20px;color:var(--text-secondary);">
                        <i class="fas fa-chart-pie" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:0.2;"></i>
                        <p style="font-size:0.8rem;">No payments today</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Today's Items Summary -->
        <div class="card-modern" style="background:var(--bg-card);border-radius:16px;padding:20px 22px;border:2px solid var(--border-color);box-shadow:0 2px 10px rgba(0,0,0,0.04);">
            <div class="card-header-modern" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                <h3 class="card-title" style="font-size:0.9rem;font-weight:600;color:var(--text-primary);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-boxes" style="color:var(--primary);"></i>
                    Today's Items Summary
                </h3>
                <span style="font-size:0.6rem;color:var(--text-secondary);background:var(--bg-body);padding:2px 10px;border-radius:20px;"><?= date('M d, Y') ?></span>
            </div>
            <div id="todayItems">
                <?php if (count($today_items) > 0): ?>
                    <?php 
                        $itemIcons = [
                            'consultation' => '🩺',
                            'medication' => '💊',
                            'lab_test' => '🔬',
                            'procedure' => '⚕️',
                            'registration' => '📋',
                            'equipment' => '🛠️',
                            'tool' => '🔧',
                            'other' => '📦'
                        ];
                    ?>
                    <?php foreach ($today_items as $item): ?>
                        <div class="item-summary" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border-bottom:1px solid var(--border-color);">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-size:1rem;"><?= $itemIcons[$item['item_type']] ?? '📦' ?></span>
                                <div>
                                    <span style="font-size:0.8rem;font-weight:500;color:var(--text-primary);text-transform:capitalize;"><?= htmlspecialchars($item['item_type'] ?? 'Other') ?></span>
                                    <span style="font-size:0.6rem;color:var(--text-secondary);display:block;margin-top:1px;"><?= $item['item_count'] ?> items | <?= $item['bill_count'] ?> bills</span>
                                </div>
                            </div>
                            <span style="font-weight:600;font-size:0.85rem;color:var(--text-primary);">TSh <?= number_format($item['total_amount'] ?? 0) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center;padding:20px;color:var(--text-secondary);">
                        <i class="fas fa-boxes" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:0.2;"></i>
                        <p style="font-size:0.8rem;">No items today</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="quick-actions-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(140px, 1fr));gap:12px;margin-top:4px;">
        <a href="pending_bills.php" class="quick-action-btn" style="padding:14px 12px;border-radius:14px;text-align:center;transition:all 0.3s;cursor:pointer;text-decoration:none;display:block;border:2px solid var(--border-color);background:var(--bg-card);">
            <span style="font-size:1.6rem;display:block;margin-bottom:4px;">⏳</span>
            <span style="font-size:0.7rem;font-weight:600;color:var(--text-primary);">Pending Bills</span>
            <span style="font-size:0.55rem;color:var(--text-secondary);display:block;margin-top:2px;opacity:0.6;"><?= $pending_bills ?> bills</span>
        </a>
        
        <a href="paid_bills.php" class="quick-action-btn" style="padding:14px 12px;border-radius:14px;text-align:center;transition:all 0.3s;cursor:pointer;text-decoration:none;display:block;border:2px solid var(--border-color);background:var(--bg-card);">
            <span style="font-size:1.6rem;display:block;margin-bottom:4px;">✅</span>
            <span style="font-size:0.7rem;font-weight:600;color:var(--text-primary);">Paid Bills</span>
            <span style="font-size:0.55rem;color:var(--text-secondary);display:block;margin-top:2px;opacity:0.6;"><?= $paid_bills ?> paid</span>
        </a>
        
        <a href="partial_payments.php" class="quick-action-btn" style="padding:14px 12px;border-radius:14px;text-align:center;transition:all 0.3s;cursor:pointer;text-decoration:none;display:block;border:2px solid var(--border-color);background:var(--bg-card);">
            <span style="font-size:1.6rem;display:block;margin-bottom:4px;">💰</span>
            <span style="font-size:0.7rem;font-weight:600;color:var(--text-primary);">Partial</span>
            <span style="font-size:0.55rem;color:var(--text-secondary);display:block;margin-top:2px;opacity:0.6;"><?= $partial_bills ?> bills</span>
        </a>
        
        <a href="payment_history.php" class="quick-action-btn" style="padding:14px 12px;border-radius:14px;text-align:center;transition:all 0.3s;cursor:pointer;text-decoration:none;display:block;border:2px solid var(--border-color);background:var(--bg-card);">
            <span style="font-size:1.6rem;display:block;margin-bottom:4px;">📜</span>
            <span style="font-size:0.7rem;font-weight:600;color:var(--text-primary);">History</span>
            <span style="font-size:0.55rem;color:var(--text-secondary);display:block;margin-top:2px;opacity:0.6;">View all</span>
        </a>
        
        <a href="expenses.php" class="quick-action-btn" style="padding:14px 12px;border-radius:14px;text-align:center;transition:all 0.3s;cursor:pointer;text-decoration:none;display:block;border:2px solid var(--border-color);background:var(--bg-card);">
            <span style="font-size:1.6rem;display:block;margin-bottom:4px;">💸</span>
            <span style="font-size:0.7rem;font-weight:600;color:var(--text-primary);">Expenses</span>
            <span style="font-size:0.55rem;color:var(--text-secondary);display:block;margin-top:2px;opacity:0.6;"><?= $expenses_count ?> records</span>
        </a>
        
        <a href="../reception/dashboard.php" class="quick-action-btn" style="padding:14px 12px;border-radius:14px;text-align:center;transition:all 0.3s;cursor:pointer;text-decoration:none;display:block;border:2px solid var(--border-color);background:var(--bg-card);">
            <span style="font-size:1.6rem;display:block;margin-bottom:4px;">🏥</span>
            <span style="font-size:0.7rem;font-weight:600;color:var(--text-primary);">Reception</span>
            <span style="font-size:0.55rem;color:var(--text-secondary);display:block;margin-top:2px;opacity:0.6;">Go to</span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer" style="padding:14px 0;border-top:2px solid var(--border-color);margin-top:24px;text-align:center;font-size:0.65rem;color:var(--text-secondary);">
        <p>
            <span class="footer-brand" style="color:var(--success);font-weight:600;">Braick Dispensary</span> Management System
            <span style="color:var(--text-secondary);opacity:0.3;margin:0 6px;">|</span>
            Cashier Dashboard
            <span style="color:var(--text-secondary);opacity:0.3;margin:0 6px;">|</span>
            <span style="color:#FFD700;font-weight:600;">👤 <?= htmlspecialchars($cashier_name) ?></span>
            <?php if ($is_reception): ?>
                <span style="color:#FCD34D;font-weight:500;font-size:0.55rem;background:rgba(251,191,36,0.15);padding:2px 8px;border-radius:10px;margin-left:4px;">👀 View Only</span>
            <?php endif; ?>
            <span style="color:var(--text-secondary);opacity:0.3;margin:0 6px;">|</span>
            <span id="footerTimestamp">● Live</span>
            <span style="color:var(--text-secondary);opacity:0.3;margin:0 6px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;position:fixed;bottom:24px;right:24px;padding:12px 18px;border-radius:12px;z-index:999;max-width:360px;transform:translateY(100px);opacity:0;transition:all 0.4s cubic-bezier(0.4,0,0.2,1);display:flex;align-items:center;gap:10px;color:white;box-shadow:0 10px 40px rgba(0,0,0,0.15);">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT - AUTO UPDATE EVERY 3 SECONDS -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        toast.className = 'toast-custom ' + (type || 'info');
        toastTitle.textContent = title || 'Notification';
        toastMessage.textContent = message || '';
        toast.style.display = 'flex';
        
        setTimeout(function() {
            toast.classList.add('show');
        }, 50);
        
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    // ================================================================
    // MANUAL REFRESH
    // ================================================================
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        btn.innerHTML = '<span class="spinner" style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.3);border-top-color:white;border-radius:50%;animation:spin 0.6s linear infinite;"></span> Loading...';
        btn.disabled = true;
        
        fetchDashboardData();
        
        setTimeout(function() {
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            btn.disabled = false;
            showToast('✅ Refreshed', 'Dashboard data updated', 'success');
        }, 1500);
    }

    // ================================================================
    // FETCH DASHBOARD DATA (AJAX)
    // ================================================================
    function fetchDashboardData() {
        var url = 'get_dashboard_data.php?t=' + Date.now();
        
        fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    updateDashboard(data);
                } else {
                    console.error('Failed to fetch dashboard data:', data.message);
                }
            })
            .catch(function(error) {
                console.error('Fetch error:', error);
            });
    }

    // ================================================================
    // UPDATE DASHBOARD UI
    // ================================================================
    function updateDashboard(data) {
        // Update stats
        var elements = {
            statTodayPayments: data.today_payments_count || 0,
            statPending: data.pending_bills || 0,
            statCancelled: data.cancelled_bills || 0,
            statTotal: data.total_bills || 0,
            statPaid: data.paid_bills || 0,
            statPartial: data.partial_bills || 0,
            statExpenses: data.expenses_count || 0,
            statHistory: data.history_count || 0,
            pendingCount: data.pending_bills || 0
        };
        
        for (var key in elements) {
            var el = document.getElementById(key);
            if (el) {
                if (key === 'statTotal' || key === 'statPaid' || key === 'statExpenses' || key === 'statCancelled') {
                    el.textContent = Number(elements[key]).toLocaleString();
                } else {
                    el.textContent = elements[key];
                }
            }
        }
        
        // Update payment history table
        var historyList = document.getElementById('paymentHistoryList');
        if (historyList && data.payment_history) {
            var html = '';
            if (data.payment_history.length > 0) {
                html = `
                    <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                        <thead style="background:var(--gray-50);border-radius:8px;border-bottom:2px solid var(--border-color);">
                            <tr>
                                <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;letter-spacing:0.03em;">Bill #</th>
                                <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;letter-spacing:0.03em;">Patient</th>
                                <th style="padding:8px 12px;text-align:right;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;letter-spacing:0.03em;">Amount</th>
                                <th style="padding:8px 12px;text-align:right;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;letter-spacing:0.03em;">Paid</th>
                                <th style="padding:8px 12px;text-align:right;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;letter-spacing:0.03em;">Balance</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;letter-spacing:0.03em;">Status</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;letter-spacing:0.03em;">Method</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--text-secondary);font-size:0.65rem;text-transform:uppercase;letter-spacing:0.03em;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                data.payment_history.forEach(function(payment) {
                    var status = payment.status || 'pending';
                    var statusColor = status === 'paid' ? '#059669' : (status === 'partial' ? '#D97706' : '#DC2626');
                    var statusBg = status === 'paid' ? '#D1FAE5' : (status === 'partial' ? '#FEF3C7' : '#FEE2E2');
                    var methodIcon = (payment.payment_method || 'cash') === 'cash' ? '💵' : '📱';
                    html += `
                        <tr style="border-bottom:1px solid var(--border-color);transition:all 0.2s;">
                            <td style="padding:8px 12px;font-weight:500;color:var(--text-primary);">
                                <a href="view_bill.php?id=${payment.bill_id}" style="color:var(--success);text-decoration:none;">${payment.bill_number}</a>
                            </td>
                            <td style="padding:8px 12px;color:var(--text-primary);">
                                <div style="font-weight:500;">${payment.patient_name || 'N/A'}</div>
                                <div style="font-size:0.6rem;color:var(--text-secondary);">${payment.patient_code || 'N/A'}</div>
                            </td>
                            <td style="padding:8px 12px;text-align:right;font-weight:500;color:var(--text-secondary);">TSh ${Number(payment.total_amount || 0).toLocaleString()}</td>
                            <td style="padding:8px 12px;text-align:right;font-weight:600;color:var(--success);">TSh ${Number(payment.paid_amount || 0).toLocaleString()}</td>
                            <td style="padding:8px 12px;text-align:right;font-weight:500;color:${Number(payment.balance || 0) > 0 ? '#DC2626' : 'var(--text-secondary)'};">TSh ${Number(payment.balance || 0).toLocaleString()}</td>
                            <td style="padding:8px 12px;text-align:center;">
                                <span style="display:inline-block;padding:2px 12px;border-radius:12px;font-size:0.6rem;font-weight:600;background:${statusBg};color:${statusColor};border:1px solid ${statusColor}20;">
                                    ${status.toUpperCase()}
                                </span>
                            </td>
                            <td style="padding:8px 12px;text-align:center;font-size:0.8rem;">${methodIcon}</td>
                            <td style="padding:8px 12px;text-align:center;font-size:0.65rem;color:var(--text-secondary);">${payment.time_ago || 'Just now'}</td>
                        </tr>
                    `;
                });
                html += `</tbody></table>`;
            } else {
                html = `
                    <div style="text-align:center;padding:40px 20px;color:var(--text-secondary);">
                        <i class="fas fa-receipt" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:0.2;"></i>
                        <p style="font-size:0.95rem;font-weight:500;">No payment history found</p>
                        <p style="font-size:0.8rem;opacity:0.6;margin-top:4px;">Payments will appear here</p>
                    </div>
                `;
            }
            historyList.innerHTML = html;
        }
        
        // Update payment methods
        var methodsEl = document.getElementById('paymentMethods');
        if (methodsEl && data.payment_methods) {
            var methodIcons = {
                'cash': '💵', 'm-pesa': '📱', 'airtel_money': '📱',
                'tigo_pesa': '📱', 'halopesa': '📱', 'card': '💳',
                'bank': '🏦', 'insurance': '🏥', 'other': '📦'
            };
            var methodColors = {
                'cash': '#059669', 'm-pesa': '#0B5ED7', 'airtel_money': '#DC2626',
                'tigo_pesa': '#D97706', 'halopesa': '#7C3AED', 'card': '#0D9488',
                'bank': '#4B5563', 'insurance': '#0891B2', 'other': '#6B7280'
            };
            
            var html = '';
            if (data.payment_methods && data.payment_methods.length > 0) {
                data.payment_methods.forEach(function(method) {
                    var icon = methodIcons[method.payment_method] || '💵';
                    var color = methodColors[method.payment_method] || '#6B7280';
                    html += `
                        <div class="method-item" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border-bottom:1px solid var(--border-color);">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.8rem;background:${color}20;color:${color};">
                                    ${icon}
                                </div>
                                <span style="font-size:0.8rem;font-weight:500;color:var(--text-primary);text-transform:uppercase;">${method.payment_method.toUpperCase()}</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-size:0.65rem;color:var(--text-secondary);">${method.count} payments</span>
                                <span style="font-weight:600;font-size:0.85rem;color:var(--success);">TSh ${Number(method.total || 0).toLocaleString()}</span>
                            </div>
                        </div>
                    `;
                });
            } else {
                html = `
                    <div style="text-align:center;padding:20px;color:var(--text-secondary);">
                        <i class="fas fa-chart-pie" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:0.2;"></i>
                        <p style="font-size:0.8rem;">No payments today</p>
                    </div>
                `;
            }
            methodsEl.innerHTML = html;
        }
        
        // Update today's items
        var itemsEl = document.getElementById('todayItems');
        if (itemsEl && data.today_items) {
            var itemIcons = {
                'consultation': '🩺', 'medication': '💊', 'lab_test': '🔬',
                'procedure': '⚕️', 'registration': '📋', 'equipment': '🛠️',
                'tool': '🔧', 'other': '📦'
            };
            
            var html = '';
            if (data.today_items && data.today_items.length > 0) {
                data.today_items.forEach(function(item) {
                    var icon = itemIcons[item.item_type] || '📦';
                    html += `
                        <div class="item-summary" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border-bottom:1px solid var(--border-color);">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-size:1rem;">${icon}</span>
                                <div>
                                    <span style="font-size:0.8rem;font-weight:500;color:var(--text-primary);text-transform:capitalize;">${item.item_type || 'Other'}</span>
                                    <span style="font-size:0.6rem;color:var(--text-secondary);display:block;margin-top:1px;">${item.item_count} items | ${item.bill_count} bills</span>
                                </div>
                            </div>
                            <span style="font-weight:600;font-size:0.85rem;color:var(--text-primary);">TSh ${Number(item.total_amount || 0).toLocaleString()}</span>
                        </div>
                    `;
                });
            } else {
                html = `
                    <div style="text-align:center;padding:20px;color:var(--text-secondary);">
                        <i class="fas fa-boxes" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:0.2;"></i>
                        <p style="font-size:0.8rem;">No items today</p>
                    </div>
                `;
            }
            itemsEl.innerHTML = html;
        }
        
        // Update footer timestamp
        var footerEl = document.getElementById('footerTimestamp');
        if (footerEl) {
            var now = new Date();
            var timeStr = now.toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
            });
            footerEl.textContent = '● ' + timeStr;
        }
    }

    // ================================================================
    // AUTO UPDATE - EVERY 3 SECONDS
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    
    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        fetchDashboardData();
        updateInterval = setInterval(function() {
            if (!isUpdating) {
                isUpdating = true;
                fetchDashboardData();
                setTimeout(function() {
                    isUpdating = false;
                }, 1000);
            }
        }, 3000);
        console.log('%c🔄 Auto-update started (every 3s)', 'font-size:12px; color:#34D399;');
    }
    
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
            console.log('%c⏹️ Auto-update stopped', 'font-size:12px; color:#DC2626;');
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebarToggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
        
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                    sidebar.classList.remove('open');
                }
            }
        });
    }

    // ================================================================
    // ADD CSS ANIMATIONS
    // ================================================================
    var style = document.createElement('style');
    style.textContent = `
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes pulse-dot { 
            0%, 100% { opacity: 1; transform: scale(1); } 
            50% { opacity: 0.5; transform: scale(0.8); } 
        }
        .stat-card-modern:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.15) !important; }
        .quick-action-btn:hover { border-color: var(--success); transform: translateY(-3px); box-shadow: 0 4px 15px rgba(5,150,105,0.08); }
        .btn-primary-custom:hover { transform: translateY(-2px); box-shadow: 0 6px 25px rgba(0,0,0,0.2) !important; }
        .btn-refresh:hover { background: rgba(255,255,255,0.25) !important; }
        .btn-reception-custom:hover { background: rgba(255,255,255,0.3) !important; transform: translateY(-2px); }
        .scroll-container::-webkit-scrollbar { width: 4px; }
        .scroll-container::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
        .scroll-container::-webkit-scrollbar-thumb { background: var(--success); border-radius: 4px; }
        @media (max-width: 768px) { .two-col-grid { grid-template-columns: 1fr !important; } }
        .stat-number.updated { transform: scale(1.1); color: #FCD34D; }
        .method-item:hover { background: var(--bg-body); border-radius: 8px; }
        .item-summary:hover { background: var(--bg-body); border-radius: 8px; }
    `;
    document.head.appendChild(style);

    // ================================================================
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startAutoUpdate();
        }, 1000);
    });

    // ================================================================
    // CONSOLE
    // ================================================================
    console.log('%c🟢 Braick - Cashier Dashboard (Auto-Update 3s)', 'font-size:20px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($cashier_name) ?>', 'font-size:16px; font-weight:bold; color:#FFD700;');
    console.log('%c👤 Role: <?= strtoupper($cashier_role) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($cashier_branch_name) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📊 Pending Bills: <?= $pending_bills ?>', 'font-size:13px; color:#D97706;');
    console.log('%c💳 Today\'s Payments: <?= $today_payments_count ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ 8 Cards: Today Payments, Pending, Cancelled, Total, Paid, Partial, Expenses, History', 'font-size:13px; color:#34D399;');
    console.log('%c🟢 Green Header Applied', 'font-size:13px; color:#059669;');
    console.log('%c🔄 Auto-update every 3 seconds (NO PAGE REFRESH)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>