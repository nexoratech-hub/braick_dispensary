<?php
// ================================================================
// FILE: frontend/pages/cashier/dashboard.php
// CASHIER DASHBOARD - ALLOWS RECEPTION AND ADMIN
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
// GET CASHIER STATISTICS
// ================================================================
$today = date('Y-m-d');
$unread_notifications = 0;

try {
    // 1. Unread Notifications
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$cashier_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // 2. Pending Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM patient_bills 
        WHERE branch_id = ? AND status IN ('pending', 'partial')
    ");
    $stmt->execute([$cashier_branch_id]);
    $pending_bills = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 3. Today's Payments
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM payments 
        WHERE branch_id = ? AND DATE(received_at) = ?
    ");
    $stmt->execute([$cashier_branch_id, $today]);
    $today_payments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 4. Total Bills
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM patient_bills WHERE branch_id = ?");
    $stmt->execute([$cashier_branch_id]);
    $total_bills = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 5. Paid Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM patient_bills 
        WHERE branch_id = ? AND status = 'paid'
    ");
    $stmt->execute([$cashier_branch_id]);
    $paid_bills = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 6. Today's Receipts
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM receipts 
        WHERE DATE(printed_at) = ?
    ");
    $stmt->execute([$today]);
    $today_receipts = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // 7. Recent Payments
    $stmt = $db->prepare("
        SELECT p.*, pb.bill_number, pb.total_amount,
               pat.full_name as patient_name, pat.patient_id,
               u.full_name as cashier_name
        FROM payments p
        JOIN patient_bills pb ON p.bill_id = pb.id
        JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN users u ON p.received_by = u.id
        WHERE p.branch_id = ?
        ORDER BY p.received_at DESC
        LIMIT 10
    ");
    $stmt->execute([$cashier_branch_id]);
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 8. Payment Methods Today
    $stmt = $db->prepare("
        SELECT payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
        FROM payments 
        WHERE branch_id = ? AND DATE(received_at) = ?
        GROUP BY payment_method
    ");
    $stmt->execute([$cashier_branch_id, $today]);
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 9. Patients with Bills
    $stmt = $db->prepare("
        SELECT DISTINCT p.id, p.full_name, p.patient_id,
            (SELECT COUNT(*) FROM patient_bills WHERE patient_id = p.id AND branch_id = ? AND status IN ('pending', 'partial')) as pending_bills_count,
            (SELECT COUNT(*) FROM patient_bills WHERE patient_id = p.id AND branch_id = ? AND status = 'paid') as paid_bills_count
        FROM patients p
        WHERE p.branch_id = ?
        AND EXISTS (SELECT 1 FROM patient_bills WHERE patient_id = p.id AND branch_id = ?)
        ORDER BY p.full_name
        LIMIT 10
    ");
    $stmt->execute([$cashier_branch_id, $cashier_branch_id, $cashier_branch_id, $cashier_branch_id]);
    $patients_with_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $pending_bills = 0;
    $today_payments = 0;
    $total_bills = 0;
    $paid_bills = 0;
    $today_receipts = 0;
    $recent_payments = [];
    $payment_methods = [];
    $patients_with_bills = [];
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// TIME AGO FUNCTION
// ================================================================
function time_ago($timestamp) {
    if (empty($timestamp)) return 'N/A';
    $now = new DateTime();
    $past = new DateTime($timestamp);
    $diff = $now->diff($past);
    
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
    <!-- PAGE HEADER - WITH GREEN BACKGROUND -->
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
                <?php if ($is_reception): ?>
                    <span style="background:rgba(251,191,36,0.2);color:#FCD34D;padding:2px 12px;border-radius:12px;font-size:0.6rem;font-weight:500;">👀 Viewing as Receptionist</span>
                <?php endif; ?>
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
            <!-- Go to Reception Dashboard Button -->
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
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:16px;margin-bottom:28px;">
        
        <!-- Card 1: Pending Bills -->
        <div class="stat-card-modern" onclick="window.location.href='pending_bills.php'" style="background:linear-gradient(135deg, #DC2626, #B91C1C);border-radius:16px;padding:20px 22px;color:white;position:relative;overflow:hidden;cursor:pointer;transition:all 0.3s ease;box-shadow:0 4px 20px rgba(220,38,38,0.25);">
            <div style="position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,0.06);"></div>
            <div style="position:absolute;bottom:-40px;left:-20px;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,0.04);"></div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;position:relative;z-index:1;">
                <div>
                    <div class="stat-number" id="statPending" style="font-size:2.2rem;font-weight:700;line-height:1.2;letter-spacing:-0.02em;"><?= $pending_bills ?></div>
                    <div class="stat-label" style="font-size:0.75rem;color:rgba(255,255,255,0.85);font-weight:500;margin-top:2px;">Pending Bills</div>
                </div>
                <div style="width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.2rem;backdrop-filter:blur(4px);">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div style="margin-top:14px;font-size:0.6rem;color:rgba(255,255,255,0.6);display:flex;align-items:center;gap:6px;">
                <span class="live-dot" style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#34D399;animation:pulse-dot 1.5s infinite;"></span>
                Live Update
                <span style="margin-left:auto;opacity:0.5;"><i class="fas fa-arrow-right"></i></span>
            </div>
        </div>
        
        <!-- Card 2: Today's Payments -->
        <div class="stat-card-modern" onclick="window.location.href='payment_history.php'" style="background:linear-gradient(135deg, #0B5ED7, #0A4CA8);border-radius:16px;padding:20px 22px;color:white;position:relative;overflow:hidden;cursor:pointer;transition:all 0.3s ease;box-shadow:0 4px 20px rgba(11,94,215,0.25);">
            <div style="position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,0.06);"></div>
            <div style="position:absolute;bottom:-40px;left:-20px;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,0.04);"></div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;position:relative;z-index:1;">
                <div>
                    <div class="stat-number" id="statPayments" style="font-size:2.2rem;font-weight:700;line-height:1.2;letter-spacing:-0.02em;"><?= $today_payments ?></div>
                    <div class="stat-label" style="font-size:0.75rem;color:rgba(255,255,255,0.85);font-weight:500;margin-top:2px;">Today's Payments</div>
                </div>
                <div style="width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.2rem;backdrop-filter:blur(4px);">
                    <i class="fas fa-credit-card"></i>
                </div>
            </div>
            <div style="margin-top:14px;font-size:0.6rem;color:rgba(255,255,255,0.6);display:flex;align-items:center;gap:6px;">
                <span class="live-dot" style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#34D399;animation:pulse-dot 1.5s infinite;"></span>
                Live Update
                <span style="margin-left:auto;opacity:0.5;"><i class="fas fa-arrow-right"></i></span>
            </div>
        </div>
        
        <!-- Card 3: Total Bills -->
        <div class="stat-card-modern" onclick="window.location.href='pending_bills.php'" style="background:linear-gradient(135deg, #D97706, #B45309);border-radius:16px;padding:20px 22px;color:white;position:relative;overflow:hidden;cursor:pointer;transition:all 0.3s ease;box-shadow:0 4px 20px rgba(217,119,6,0.25);">
            <div style="position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,0.06);"></div>
            <div style="position:absolute;bottom:-40px;left:-20px;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,0.04);"></div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;position:relative;z-index:1;">
                <div>
                    <div class="stat-number" id="statTotal" style="font-size:2.2rem;font-weight:700;line-height:1.2;letter-spacing:-0.02em;"><?= number_format($total_bills) ?></div>
                    <div class="stat-label" style="font-size:0.75rem;color:rgba(255,255,255,0.85);font-weight:500;margin-top:2px;">Total Bills</div>
                </div>
                <div style="width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.2rem;backdrop-filter:blur(4px);">
                    <i class="fas fa-file-invoice"></i>
                </div>
            </div>
            <div style="margin-top:14px;font-size:0.6rem;color:rgba(255,255,255,0.6);display:flex;align-items:center;gap:6px;">
                <span class="live-dot" style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#34D399;animation:pulse-dot 1.5s infinite;"></span>
                Live Update
                <span style="margin-left:auto;opacity:0.5;"><i class="fas fa-arrow-right"></i></span>
            </div>
        </div>
        
        <!-- Card 4: Paid Bills -->
        <div class="stat-card-modern" onclick="window.location.href='paid_bills.php'" style="background:linear-gradient(135deg, #059669, #047857);border-radius:16px;padding:20px 22px;color:white;position:relative;overflow:hidden;cursor:pointer;transition:all 0.3s ease;box-shadow:0 4px 20px rgba(5,150,105,0.25);">
            <div style="position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,0.06);"></div>
            <div style="position:absolute;bottom:-40px;left:-20px;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,0.04);"></div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;position:relative;z-index:1;">
                <div>
                    <div class="stat-number" id="statPaid" style="font-size:2.2rem;font-weight:700;line-height:1.2;letter-spacing:-0.02em;"><?= number_format($paid_bills) ?></div>
                    <div class="stat-label" style="font-size:0.75rem;color:rgba(255,255,255,0.85);font-weight:500;margin-top:2px;">Paid Bills</div>
                </div>
                <div style="width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.2rem;backdrop-filter:blur(4px);">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <div style="margin-top:14px;font-size:0.6rem;color:rgba(255,255,255,0.6);display:flex;align-items:center;gap:6px;">
                <span class="live-dot" style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#34D399;animation:pulse-dot 1.5s infinite;"></span>
                Live Update
                <span style="margin-left:auto;opacity:0.5;"><i class="fas fa-arrow-right"></i></span>
            </div>
        </div>
        
        <!-- Card 5: Today's Receipts -->
        <div class="stat-card-modern" onclick="window.location.href='receipt_history.php'" style="background:linear-gradient(135deg, #0D9488, #0F766E);border-radius:16px;padding:20px 22px;color:white;position:relative;overflow:hidden;cursor:pointer;transition:all 0.3s ease;box-shadow:0 4px 20px rgba(13,148,136,0.25);">
            <div style="position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,0.06);"></div>
            <div style="position:absolute;bottom:-40px;left:-20px;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,0.04);"></div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;position:relative;z-index:1;">
                <div>
                    <div class="stat-number" id="statReceipts" style="font-size:2.2rem;font-weight:700;line-height:1.2;letter-spacing:-0.02em;"><?= $today_receipts ?></div>
                    <div class="stat-label" style="font-size:0.75rem;color:rgba(255,255,255,0.85);font-weight:500;margin-top:2px;">Today's Receipts</div>
                </div>
                <div style="width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.2rem;backdrop-filter:blur(4px);">
                    <i class="fas fa-receipt"></i>
                </div>
            </div>
            <div style="margin-top:14px;font-size:0.6rem;color:rgba(255,255,255,0.6);display:flex;align-items:center;gap:6px;">
                <span class="live-dot" style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#34D399;animation:pulse-dot 1.5s infinite;"></span>
                Live Update
                <span style="margin-left:auto;opacity:0.5;"><i class="fas fa-arrow-right"></i></span>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- PATIENTS WITH BILLS -->
    <!-- ================================================================ -->
    <div class="card-modern" style="background:var(--bg-card);border-radius:16px;padding:22px 24px;border:2px solid var(--border-color);margin-bottom:24px;transition:all 0.3s;box-shadow:0 2px 10px rgba(0,0,0,0.04);">
        <div class="card-header-modern" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
            <h3 class="card-title" style="font-size:0.95rem;font-weight:600;color:var(--text-primary);display:flex;align-items:center;gap:10px;">
                <i class="fas fa-users" style="color:var(--primary);"></i>
                Patients with Bills
                <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);background:var(--bg-body);padding:2px 12px;border-radius:20px;">Click to view history</span>
            </h3>
            <a href="payment_history.php" style="color:var(--primary);font-size:0.85rem;text-decoration:none;font-weight:500;transition:all 0.3s;display:flex;align-items:center;gap:4px;">
                View All <i class="fas fa-arrow-right" style="font-size:0.7rem;"></i>
            </a>
        </div>
        
        <div class="patients-grid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:12px;" id="patientsWithBills">
            <?php if (count($patients_with_bills) > 0): ?>
                <?php foreach ($patients_with_bills as $patient): ?>
                    <a href="payment_history.php?patient_id=<?= $patient['id'] ?>" 
                       class="patient-item-modern" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-radius:12px;border:2px solid var(--border-color);transition:all 0.3s;text-decoration:none;cursor:pointer;background:var(--bg-card);">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div style="width:40px;height:40px;border-radius:50%;background:var(--primary-bg);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.9rem;color:var(--primary);flex-shrink:0;">
                                <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <p style="font-weight:600;font-size:0.85rem;color:var(--text-primary);"><?= htmlspecialchars($patient['full_name']) ?></p>
                                <p style="font-size:0.65rem;color:var(--text-secondary);"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></p>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="display:flex;gap:4px;">
                                <?php if (($patient['pending_bills_count'] ?? 0) > 0): ?>
                                    <span class="badge-pending-modern" style="padding:3px 10px;border-radius:20px;font-size:0.6rem;font-weight:600;background:#FEF3C7;color:#D97706;border:1px solid #FDE68A;">⏳ <?= $patient['pending_bills_count'] ?></span>
                                <?php endif; ?>
                                <?php if (($patient['paid_bills_count'] ?? 0) > 0): ?>
                                    <span class="badge-paid-modern" style="padding:3px 10px;border-radius:20px;font-size:0.6rem;font-weight:600;background:#D1FAE5;color:#059669;border:1px solid #A7F3D0;">✅ <?= $patient['paid_bills_count'] ?></span>
                                <?php endif; ?>
                                <?php if (($patient['pending_bills_count'] ?? 0) == 0 && ($patient['paid_bills_count'] ?? 0) == 0): ?>
                                    <span class="badge-empty" style="padding:3px 10px;border-radius:20px;font-size:0.6rem;font-weight:600;background:var(--bg-body);color:var(--text-secondary);border:1px solid var(--border-color);">No bills</span>
                                <?php endif; ?>
                            </div>
                            <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--text-secondary);opacity:0.3;transition:all 0.3s;"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-full" style="text-align:center;padding:40px 20px;color:var(--text-secondary);">
                    <i class="fas fa-users" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:0.2;"></i>
                    <p style="font-size:0.95rem;font-weight:500;">No patients with bills found</p>
                    <p style="font-size:0.8rem;opacity:0.6;margin-top:4px;">Patients with bills will appear here</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PAYMENTS & PAYMENT METHODS -->
    <!-- ================================================================ -->
    <div class="two-col-grid" style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px;">
        
        <!-- Recent Payments -->
        <div class="card-modern" style="background:var(--bg-card);border-radius:16px;padding:22px 24px;border:2px solid var(--border-color);box-shadow:0 2px 10px rgba(0,0,0,0.04);">
            <div class="card-header-modern" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
                <h3 class="card-title" style="font-size:0.95rem;font-weight:600;color:var(--text-primary);display:flex;align-items:center;gap:10px;">
                    <i class="fas fa-history" style="color:var(--primary);"></i>
                    Recent Payments
                </h3>
                <span style="font-size:0.65rem;color:var(--text-secondary);background:var(--bg-body);padding:2px 12px;border-radius:20px;">Last 10</span>
            </div>
            <div class="scroll-container" style="max-height:300px;overflow-y:auto;padding-right:4px;" id="recentPaymentsList">
                <?php if (count($recent_payments) > 0): ?>
                    <?php foreach ($recent_payments as $payment): ?>
                        <div class="payment-item" style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--border-color);transition:all 0.3s;border-radius:8px;margin-bottom:2px;">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div style="width:36px;height:36px;border-radius:50%;background:var(--success-bg);display:flex;align-items:center;justify-content:center;font-size:0.8rem;color:var(--success);flex-shrink:0;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <p style="font-weight:500;font-size:0.85rem;color:var(--text-primary);"><?= htmlspecialchars($payment['patient_name']) ?></p>
                                    <p style="font-size:0.65rem;color:var(--text-secondary);"><?= htmlspecialchars($payment['bill_number']) ?></p>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <p style="font-weight:700;font-size:0.95rem;color:var(--success);">TSh <?= number_format($payment['amount'] ?? 0) ?></p>
                                <p style="font-size:0.6rem;color:var(--text-secondary);display:flex;align-items:center;gap:4px;justify-content:flex-end;">
                                    <?php 
                                        $method = $payment['payment_method'] ?? 'cash';
                                        $methodIcon = $method === 'cash' ? '💵' : ($method === 'm-pesa' ? '📱' : '💳');
                                        echo $methodIcon . ' ' . strtoupper($method);
                                    ?>
                                    <span style="opacity:0.3;">•</span>
                                    <?= time_ago($payment['received_at'] ?? '') ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center;padding:30px 20px;color:var(--text-secondary);">
                        <i class="fas fa-clock" style="font-size:2rem;display:block;margin-bottom:12px;opacity:0.2;"></i>
                        <p style="font-size:0.95rem;font-weight:500;">No recent payments</p>
                        <p style="font-size:0.8rem;opacity:0.6;margin-top:4px;">Payments will appear here</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Payment Methods -->
        <div class="card-modern" style="background:var(--bg-card);border-radius:16px;padding:22px 24px;border:2px solid var(--border-color);box-shadow:0 2px 10px rgba(0,0,0,0.04);">
            <div class="card-header-modern" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
                <h3 class="card-title" style="font-size:0.95rem;font-weight:600;color:var(--text-primary);display:flex;align-items:center;gap:10px;">
                    <i class="fas fa-chart-pie" style="color:var(--success);"></i>
                    Payment Methods
                </h3>
                <span style="font-size:0.65rem;color:var(--text-secondary);background:var(--bg-body);padding:2px 12px;border-radius:20px;">Today</span>
            </div>
            <div class="scroll-container" style="max-height:300px;overflow-y:auto;padding-right:4px;" id="paymentMethods">
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
                        <div class="method-item" style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--border-color);">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.9rem;background:<?= $methodColors[$method['payment_method']] ?? '#6B7280' ?>20;color:<?= $methodColors[$method['payment_method']] ?? '#6B7280' ?>;">
                                    <?= $methodIcons[$method['payment_method']] ?? '💵' ?>
                                </div>
                                <span style="font-size:0.85rem;font-weight:500;color:var(--text-primary);"><?= strtoupper($method['payment_method'] ?? 'CASH') ?></span>
                            </div>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <span style="font-size:0.7rem;color:var(--text-secondary);"><?= $method['count'] ?> payments</span>
                                <span style="font-weight:700;font-size:0.85rem;color:var(--success);">TSh <?= number_format($method['total'] ?? 0) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center;padding:30px 20px;color:var(--text-secondary);">
                        <i class="fas fa-chart-pie" style="font-size:2rem;display:block;margin-bottom:12px;opacity:0.2;"></i>
                        <p style="font-size:0.95rem;font-weight:500;">No payments today</p>
                        <p style="font-size:0.8rem;opacity:0.6;margin-top:4px;">Payment methods will appear here</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="quick-actions-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:12px;margin-top:4px;">
        <a href="pending_bills.php" class="quick-action-btn" style="padding:16px 12px;border-radius:14px;text-align:center;transition:all 0.3s;cursor:pointer;text-decoration:none;display:block;border:2px solid var(--border-color);background:var(--bg-card);">
            <span style="font-size:1.8rem;display:block;margin-bottom:6px;">⏳</span>
            <span style="font-size:0.75rem;font-weight:600;color:var(--text-primary);">Pending Bills</span>
            <span style="font-size:0.6rem;color:var(--text-secondary);display:block;margin-top:3px;opacity:0.6;">View all pending</span>
        </a>
        
        <a href="paid_bills.php" class="quick-action-btn" style="padding:16px 12px;border-radius:14px;text-align:center;transition:all 0.3s;cursor:pointer;text-decoration:none;display:block;border:2px solid var(--border-color);background:var(--bg-card);">
            <span style="font-size:1.8rem;display:block;margin-bottom:6px;">✅</span>
            <span style="font-size:0.75rem;font-weight:600;color:var(--text-primary);">Paid Bills</span>
            <span style="font-size:0.6rem;color:var(--text-secondary);display:block;margin-top:3px;opacity:0.6;">View all paid</span>
        </a>
        
        <a href="print_receipt.php" class="quick-action-btn" style="padding:16px 12px;border-radius:14px;text-align:center;transition:all 0.3s;cursor:pointer;text-decoration:none;display:block;border:2px solid var(--border-color);background:var(--bg-card);">
            <span style="font-size:1.8rem;display:block;margin-bottom:6px;">🧾</span>
            <span style="font-size:0.75rem;font-weight:600;color:var(--text-primary);">Print Receipt</span>
            <span style="font-size:0.6rem;color:var(--text-secondary);display:block;margin-top:3px;opacity:0.6;">Print new receipt</span>
        </a>
        
        <a href="payment_history.php" class="quick-action-btn" style="padding:16px 12px;border-radius:14px;text-align:center;transition:all 0.3s;cursor:pointer;text-decoration:none;display:block;border:2px solid var(--border-color);background:var(--bg-card);">
            <span style="font-size:1.8rem;display:block;margin-bottom:6px;">📜</span>
            <span style="font-size:0.75rem;font-weight:600;color:var(--text-primary);">Payment History</span>
            <span style="font-size:0.6rem;color:var(--text-secondary);display:block;margin-top:3px;opacity:0.6;">View history</span>
        </a>
        
        <!-- Reception Dashboard Quick Action -->
        <a href="../reception/dashboard.php" class="quick-action-btn" style="padding:16px 12px;border-radius:14px;text-align:center;transition:all 0.3s;cursor:pointer;text-decoration:none;display:block;border:2px solid var(--border-color);background:var(--bg-card);">
            <span style="font-size:1.8rem;display:block;margin-bottom:6px;">🏥</span>
            <span style="font-size:0.75rem;font-weight:600;color:var(--text-primary);">Reception</span>
            <span style="font-size:0.6rem;color:var(--text-secondary);display:block;margin-top:3px;opacity:0.6;">Go to Reception</span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer" style="padding:16px 0;border-top:2px solid var(--border-color);margin-top:28px;text-align:center;font-size:0.7rem;color:var(--text-secondary);">
        <p>
            <span class="footer-brand" style="color:var(--success);font-weight:600;">Braick Dispensary</span> Management System
            <span style="color:var(--text-secondary);opacity:0.3;margin:0 8px;">|</span>
            Cashier Dashboard
            <span style="color:var(--text-secondary);opacity:0.3;margin:0 8px;">|</span>
            <span style="color:#FFD700;font-weight:600;">👤 <?= htmlspecialchars($cashier_name) ?></span>
            <?php if ($is_reception): ?>
                <span style="color:#FCD34D;font-weight:500;font-size:0.6rem;background:rgba(251,191,36,0.15);padding:2px 10px;border-radius:10px;margin-left:4px;">👀 View Only (Reception)</span>
            <?php endif; ?>
            <span style="color:var(--text-secondary);opacity:0.3;margin:0 8px;">|</span>
            <span id="footerTimestamp">● Live</span>
            <span style="color:var(--text-secondary);opacity:0.3;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;position:fixed;bottom:24px;right:24px;padding:14px 20px;border-radius:12px;z-index:999;max-width:380px;transform:translateY(100px);opacity:0;transition:all 0.4s cubic-bezier(0.4,0,0.2,1);display:flex;align-items:center;gap:12px;color:white;box-shadow:0 10px 40px rgba(0,0,0,0.15);">
    <i class="fas fa-info-circle" style="font-size:1.2rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.9rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.8rem;opacity:0.9;margin:0;" id="toastMessage"></p>
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
        var pendingEl = document.getElementById('statPending');
        var paymentsEl = document.getElementById('statPayments');
        var totalEl = document.getElementById('statTotal');
        var paidEl = document.getElementById('statPaid');
        var receiptsEl = document.getElementById('statReceipts');
        var pendingCountEl = document.getElementById('pendingCount');
        
        if (pendingEl) pendingEl.textContent = data.pending_bills || 0;
        if (paymentsEl) paymentsEl.textContent = data.today_payments || 0;
        if (totalEl) totalEl.textContent = (data.total_bills || 0).toLocaleString();
        if (paidEl) paidEl.textContent = (data.paid_bills || 0).toLocaleString();
        if (receiptsEl) receiptsEl.textContent = data.today_receipts || 0;
        if (pendingCountEl) pendingCountEl.textContent = data.pending_bills || 0;
        
        // Update recent payments
        var paymentsList = document.getElementById('recentPaymentsList');
        if (paymentsList && data.recent_payments) {
            var html = '';
            if (data.recent_payments.length > 0) {
                data.recent_payments.forEach(function(payment) {
                    var methodIcon = payment.payment_method === 'cash' ? '💵' : 
                                    (payment.payment_method === 'm-pesa' ? '📱' : '💳');
                    html += `
                        <div class="payment-item" style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--border-color);transition:all 0.3s;border-radius:8px;margin-bottom:2px;">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div style="width:36px;height:36px;border-radius:50%;background:var(--success-bg);display:flex;align-items:center;justify-content:center;font-size:0.8rem;color:var(--success);flex-shrink:0;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <p style="font-weight:500;font-size:0.85rem;color:var(--text-primary);">${payment.patient_name}</p>
                                    <p style="font-size:0.65rem;color:var(--text-secondary);">${payment.bill_number}</p>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <p style="font-weight:700;font-size:0.95rem;color:var(--success);">TSh ${Number(payment.amount).toLocaleString()}</p>
                                <p style="font-size:0.6rem;color:var(--text-secondary);display:flex;align-items:center;gap:4px;justify-content:flex-end;">
                                    ${methodIcon} ${payment.payment_method.toUpperCase()}
                                    <span style="opacity:0.3;">•</span>
                                    ${payment.time_ago}
                                </p>
                            </div>
                        </div>
                    `;
                });
            } else {
                html = `
                    <div style="text-align:center;padding:30px 20px;color:var(--text-secondary);">
                        <i class="fas fa-clock" style="font-size:2rem;display:block;margin-bottom:12px;opacity:0.2;"></i>
                        <p style="font-size:0.95rem;font-weight:500;">No recent payments</p>
                        <p style="font-size:0.8rem;opacity:0.6;margin-top:4px;">Payments will appear here</p>
                    </div>
                `;
            }
            paymentsList.innerHTML = html;
        }
        
        // Update payment methods
        var methodsEl = document.getElementById('paymentMethods');
        if (methodsEl && data.payment_methods) {
            var methodIcons = {
                'cash': '💵',
                'm-pesa': '📱',
                'airtel_money': '📱',
                'tigo_pesa': '📱',
                'halopesa': '📱',
                'card': '💳',
                'bank': '🏦',
                'insurance': '🏥',
                'other': '📦'
            };
            var methodColors = {
                'cash': '#059669',
                'm-pesa': '#0B5ED7',
                'airtel_money': '#DC2626',
                'tigo_pesa': '#D97706',
                'halopesa': '#7C3AED',
                'card': '#0D9488',
                'bank': '#4B5563',
                'insurance': '#0891B2',
                'other': '#6B7280'
            };
            
            var html = '';
            if (data.payment_methods.length > 0) {
                data.payment_methods.forEach(function(method) {
                    var icon = methodIcons[method.payment_method] || '💵';
                    var color = methodColors[method.payment_method] || '#6B7280';
                    html += `
                        <div class="method-item" style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--border-color);">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.9rem;background:${color}20;color:${color};">
                                    ${icon}
                                </div>
                                <span style="font-size:0.85rem;font-weight:500;color:var(--text-primary);">${method.payment_method.toUpperCase()}</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <span style="font-size:0.7rem;color:var(--text-secondary);">${method.count} payments</span>
                                <span style="font-weight:700;font-size:0.85rem;color:var(--success);">TSh ${Number(method.total).toLocaleString()}</span>
                            </div>
                        </div>
                    `;
                });
            } else {
                html = `
                    <div style="text-align:center;padding:30px 20px;color:var(--text-secondary);">
                        <i class="fas fa-chart-pie" style="font-size:2rem;display:block;margin-bottom:12px;opacity:0.2;"></i>
                        <p style="font-size:0.95rem;font-weight:500;">No payments today</p>
                        <p style="font-size:0.8rem;opacity:0.6;margin-top:4px;">Payment methods will appear here</p>
                    </div>
                `;
            }
            methodsEl.innerHTML = html;
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
        // Initial fetch
        fetchDashboardData();
        // Set interval
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
        .patient-item-modern:hover { border-color: var(--success); transform: translateY(-2px); box-shadow: 0 4px 15px rgba(5,150,105,0.08); }
        .patient-item-modern:hover .fa-chevron-right { opacity: 0.8 !important; transform: translateX(2px); }
        .quick-action-btn:hover { border-color: var(--success); transform: translateY(-3px); box-shadow: 0 4px 15px rgba(5,150,105,0.08); }
        .btn-primary-custom:hover { transform: translateY(-2px); box-shadow: 0 6px 25px rgba(0,0,0,0.2) !important; }
        .btn-refresh:hover { background: rgba(255,255,255,0.25) !important; }
        .btn-reception-custom:hover { background: rgba(255,255,255,0.3) !important; transform: translateY(-2px); }
        .payment-item:hover { background: var(--bg-body); border-radius: 8px; }
        .method-item:hover { background: var(--bg-body); border-radius: 8px; }
        .scroll-container::-webkit-scrollbar { width: 4px; }
        .scroll-container::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
        .scroll-container::-webkit-scrollbar-thumb { background: var(--success); border-radius: 4px; }
        @media (max-width: 768px) { .two-col-grid { grid-template-columns: 1fr !important; } }
        .stat-number { transition: all 0.3s ease; }
        .stat-number.updated { transform: scale(1.1); color: #FCD34D; }
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
    console.log('%c💳 Today\'s Payments: <?= $today_payments ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Receptionists can now access Cashier Dashboard', 'font-size:13px; color:#34D399;');
    console.log('%c🟢 Green Header Applied', 'font-size:13px; color:#059669;');
    console.log('%c🔄 Auto-update every 3 seconds (NO PAGE REFRESH)', 'font-size:13px; color:#34D399;');
    console.log('%c🔒 Login protection: Active (Cashier, Reception, Admin)', 'font-size:13px; color:#059669;');
</script>

</body>
</html>