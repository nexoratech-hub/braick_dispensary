<?php
// ================================================================
// FILE: frontend/pages/admin/delete_equipment.php
// ADMIN - DELETE EQUIPMENT
// ✅ Uses SHARED header & sidebar
// ✅ Beautiful danger page header + equipment details
// ✅ Full dark mode support
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
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
        default: header('Location: ../../auth/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// PARAMETERS
// ================================================================
$equipment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$return_branch = isset($_GET['branch']) ? $_GET['branch'] : 'all';
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $unread_notifications = 0; }

// ================================================================
// FETCH EQUIPMENT
// ================================================================
$equipment = null;
$error_message = '';
$linked_count = 0;

try {
    $stmt = $db->prepare("
        SELECT 
            e.*,
            b.name as branch_name,
            (SELECT COUNT(*) FROM lab_test_equipment WHERE equipment_id = e.id) as linked_count
        FROM medical_equipment e
        LEFT JOIN branches b ON e.branch_id = b.id
        WHERE e.id = ?
    ");
    $stmt->execute([$equipment_id]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$equipment) {
        $error_message = "Equipment not found. It may have been deleted.";
    } else {
        $linked_count = $equipment['linked_count'] ?? 0;
    }
} catch (Exception $e) {
    $error_message = "Error loading equipment: " . $e->getMessage();
    $equipment = null;
}

// ================================================================
// PROCESS DELETION
// ================================================================
$message = '';
$message_type = '';
$deleted = false;

if ($confirmed && $equipment) {
    try {
        $db->beginTransaction();

        // Delete links from lab_test_equipment
        if ($linked_count > 0) {
            $stmt = $db->prepare("DELETE FROM lab_test_equipment WHERE equipment_id = ?");
            $stmt->execute([$equipment_id]);
        }

        // Delete the equipment
        $stmt = $db->prepare("DELETE FROM medical_equipment WHERE id = ?");
        $stmt->execute([$equipment_id]);

        $db->commit();

        $deleted = true;
        $message = "✅ Equipment '<strong>" . htmlspecialchars($equipment['equipment_name']) . "</strong>' deleted successfully!";
        $message_type = 'success';

        // Redirect after 2 seconds
        echo '<script>
            setTimeout(function() {
                window.location.href = "equipment_inventory.php?branch=' . urlencode($return_branch) . '&success=1";
            }, 2000);
        </script>';

    } catch (Exception $e) {
        if (isset($db)) $db->rollBack();
        $message = "❌ Error deleting equipment: " . $e->getMessage();
        $message_type = 'error';
    }
}

// ================================================================
// BRANCHES
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $branches = []; }

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// HELPERS
// ================================================================
function getStockStatus($quantity, $reorder_level) {
    $quantity = (int)$quantity;
    $reorder_level = (int)$reorder_level;
    if ($quantity <= 0) return ['class' => 'out', 'label' => 'Out of Stock'];
    if ($quantity <= $reorder_level) return ['class' => 'low', 'label' => 'Low Stock'];
    return ['class' => 'ok', 'label' => 'In Stock'];
}

function getExpiryStatus($expiry_date) {
    if (empty($expiry_date)) return ['class' => 'no-expiry', 'label' => 'No Expiry'];
    $now = new DateTime();
    $expiry = new DateTime($expiry_date);
    $diff = $now->diff($expiry);

    if ($expiry < $now) return ['class' => 'expired', 'label' => 'Expired'];
    if ($diff->days <= 30) return ['class' => 'expiring', 'label' => 'Expires in ' . $diff->days . ' days'];
    return ['class' => 'valid', 'label' => 'Valid until ' . $expiry->format('M d, Y')];
}

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================
     PAGE-SPECIFIC CSS
     ================================================================ -->
<style>
    :root {
        --page-primary: #0B5ED7;
        --page-primary-dark: #0A4CA8;
        --page-primary-bg: #E8F0FE;
        --page-success: #059669;
        --page-success-bg: #D1FAE5;
        --page-danger: #DC2626;
        --page-danger-bg: #FEE2E2;
        --page-warning: #D97706;
        --page-warning-bg: #FEF3C7;
        --page-purple: #7C3AED;
        --page-purple-bg: #EDE9FE;
        --page-bg-body: #F0F4F8;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-border: #E2E8F0;
        --page-shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
        --page-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-border: #334155;
        --page-primary-bg: #1E3A5F;
        --page-success-bg: #1A3A2A;
        --page-danger-bg: #3A1A1A;
        --page-warning-bg: #3D2E0A;
        --page-purple-bg: #2D1B5F;
    }

    body { background: var(--page-bg-body); }
    html[data-theme="dark"] body { background: #0F172A !important; }

    .main-content { background: var(--page-bg-body); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; }

    /* ================================================================
       RED DANGER PAGE HEADER CARD
       ================================================================ */
    .page-header-card {
        background: linear-gradient(135deg, #DC2626 0%, #B91C1C 50%, #991B1B 100%);
        border-radius: 20px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        color: white;
        box-shadow: 0 8px 32px rgba(220, 38, 38, 0.35), 0 4px 12px rgba(220, 38, 38, 0.2);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .page-header-card::before {
        content: '';
        position: absolute;
        top: -50%; right: -10%;
        width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-title {
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0 0 8px 0;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        color: white;
        position: relative;
        z-index: 2;
    }

    .page-header-title i {
        width: 44px; height: 44px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-subtitle {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.95);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .page-header-subtitle strong { color: white; font-weight: 700; }

    .role-badge-display {
        background: rgba(255,255,255,0.25);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .page-header-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
        backdrop-filter: blur(10px);
    }

    .page-header-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .btn-outline-light {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: rgba(255,255,255,0.15);
        border: 1.5px solid rgba(255,255,255,0.3);
        border-radius: 12px;
        color: white;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        white-space: nowrap;
        cursor: pointer;
    }

    .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    /* ================================================================
       CARD
       ================================================================ */
    .card-modern {
        background: var(--page-bg-card);
        border-radius: 18px;
        border: 1px solid var(--page-border);
        box-shadow: var(--page-shadow-md);
        padding: 24px 28px;
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }

    html[data-theme="dark"] .card-modern {
        background: #1E293B;
        border-color: #334155;
    }

    .card-modern.danger {
        border: 2px solid var(--page-danger);
    }

    .card-modern-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 14px;
        border-bottom: 2px solid var(--page-border);
    }

    html[data-theme="dark"] .card-modern-header {
        border-bottom-color: #334155;
    }

    .card-modern-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--page-text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }

    html[data-theme="dark"] .card-modern-title { color: #F1F5F9; }

    .card-modern-title i { color: var(--page-primary); }

    /* ================================================================
       STATUS BADGES
       ================================================================ */
    .status-badge, .stock-badge, .expiry-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
    }

    .status-badge.active { background: var(--page-success-bg); color: var(--page-success); }
    .status-badge.inactive { background: var(--page-danger-bg); color: var(--page-danger); }

    .stock-badge.ok { background: var(--page-success-bg); color: var(--page-success); }
    .stock-badge.low { background: var(--page-warning-bg); color: var(--page-warning); }
    .stock-badge.out { background: var(--page-danger-bg); color: var(--page-danger); }

    .expiry-badge.valid { background: var(--page-success-bg); color: var(--page-success); }
    .expiry-badge.expiring { background: var(--page-warning-bg); color: var(--page-warning); }
    .expiry-badge.expired { background: var(--page-danger-bg); color: var(--page-danger); }
    .expiry-badge.no-expiry { background: var(--page-bg-body); color: var(--page-text-secondary); }

    html[data-theme="dark"] .expiry-badge.no-expiry { background: #0F172A; }

    /* ================================================================
       DELETE ICON
       ================================================================ */
    .delete-icon {
        font-size: 4.5rem;
        color: var(--page-danger);
        display: block;
        margin-bottom: 14px;
        animation: shakePulse 2s ease-in-out infinite;
    }

    @keyframes shakePulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    .delete-title {
        font-size: 1.4rem;
        color: var(--page-danger);
        margin-bottom: 10px;
        font-weight: 800;
    }

    .delete-subtitle {
        color: var(--page-text-secondary);
        font-size: 0.95rem;
        line-height: 1.5;
    }

    .delete-subtitle strong { color: var(--page-danger); font-weight: 700; }

    /* ================================================================
       EQUIPMENT DETAILS BOX
       ================================================================ */
    .details-box {
        background: var(--page-bg-body);
        border-radius: 14px;
        padding: 20px 24px;
        margin: 20px 0;
        border: 1px solid var(--page-border);
    }

    html[data-theme="dark"] .details-box {
        background: #0F172A;
        border-color: #334155;
    }

    .details-box-title {
        font-size: 0.72rem;
        color: var(--page-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 700;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .equipment-detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid var(--page-border);
        gap: 12px;
    }

    html[data-theme="dark"] .equipment-detail-row {
        border-bottom-color: #334155;
    }

    .equipment-detail-row:last-child { border-bottom: none; }

    .equipment-detail-label {
        color: var(--page-text-secondary);
        font-size: 0.8rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .equipment-detail-label i {
        color: var(--page-primary);
        width: 16px;
        text-align: center;
    }

    .equipment-detail-value {
        color: var(--page-text-primary);
        font-size: 0.9rem;
        font-weight: 600;
        text-align: right;
    }

    .batch-number {
        font-family: 'Courier New', monospace;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 3px 12px;
        border-radius: 8px;
        background: var(--page-primary-bg);
        color: var(--page-primary);
        border: 1px solid var(--page-primary);
    }

    html[data-theme="dark"] .batch-number {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: #3B82F6;
    }

    /* ================================================================
       WARNING BOX
       ================================================================ */
    .warning-box {
        background: var(--page-warning-bg);
        border: 2px solid var(--page-warning);
        border-radius: 14px;
        padding: 16px 20px;
        margin: 20px 0;
        display: flex;
        gap: 14px;
        align-items: flex-start;
    }

    html[data-theme="dark"] .warning-box {
        background: #3D2E0A;
        border-color: #D97706;
    }

    .warning-box > i {
        font-size: 1.5rem;
        color: var(--page-warning);
        flex-shrink: 0;
        margin-top: 2px;
    }

    .warning-box-content strong {
        display: block;
        color: #92400E;
        font-size: 0.95rem;
        font-weight: 800;
        margin-bottom: 6px;
    }

    html[data-theme="dark"] .warning-box-content strong { color: #FBBF24; }

    .warning-box-content p {
        color: #92400E;
        font-size: 0.85rem;
        line-height: 1.5;
        margin: 0;
    }

    html[data-theme="dark"] .warning-box-content p { color: #FCD34D; }

    /* ================================================================
       BRANCH TAG
       ================================================================ */
    .branch-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: var(--page-primary-bg);
        color: var(--page-primary);
        padding: 3px 12px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 700;
    }

    html[data-theme="dark"] .branch-tag {
        background: #1E3A5F;
        color: #6EA8FE;
    }

    .branch-tag.all-branches {
        background: var(--page-warning-bg);
        color: var(--page-warning);
    }

    html[data-theme="dark"] .branch-tag.all-branches {
        background: #3D2E0A;
        color: #FBBF24;
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        font-family: inherit;
        min-height: 46px;
    }

    .btn-danger {
        background: linear-gradient(135deg, #DC2626, #B91C1C);
        color: white;
        box-shadow: 0 4px 14px rgba(220, 38, 38, 0.3);
        flex: 1;
    }

    .btn-danger:hover {
        background: linear-gradient(135deg, #B91C1C, #991B1B);
        transform: translateY(-2px);
        color: white;
        box-shadow: 0 8px 25px rgba(220, 38, 38, 0.4);
    }

    .btn-outline {
        background: transparent;
        color: var(--page-text-secondary);
        border: 2px solid var(--page-border);
    }

    html[data-theme="dark"] .btn-outline {
        color: #94A3B8;
        border-color: #334155;
    }

    .btn-outline:hover {
        border-color: var(--page-primary);
        color: var(--page-primary);
        transform: translateY(-2px);
    }

    .btn-primary {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #0A4CA8, #083C8A);
        transform: translateY(-2px);
        color: white;
    }

    .btn-group {
        display: flex;
        gap: 12px;
        margin-top: 24px;
        flex-wrap: wrap;
    }

    /* ================================================================
       ALERT
       ================================================================ */
    .alert-modern {
        padding: 16px 20px;
        border-radius: 14px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        animation: slideDown 0.4s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .alert-modern-success { background: #D1FAE5; color: #047857; border: 2px solid #059669; }
    .alert-modern-error { background: #FEE2E2; color: #B91C1C; border: 2px solid #DC2626; }

    html[data-theme="dark"] .alert-modern-success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
    html[data-theme="dark"] .alert-modern-error { background: #3A1A1A; color: #F87171; border-color: #F87171; }

    .alert-modern i { font-size: 1.2rem; margin-top: 2px; flex-shrink: 0; }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--page-text-secondary);
    }

    .empty-state i {
        font-size: 3.5rem;
        color: var(--page-border);
        margin-bottom: 14px;
        display: block;
    }

    .empty-state h3 {
        font-size: 1.2rem;
        color: var(--page-text-primary);
        margin-bottom: 8px;
    }

    .empty-state p { font-size: 0.9rem; margin-bottom: 6px; }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .page-header-card { padding: 20px; }
        .page-header-title { font-size: 1.2rem; }
        .page-header-title i { width: 36px; height: 36px; font-size: 1rem; }
        .card-modern { padding: 18px 16px; }
        .card-modern-header { flex-direction: column; align-items: flex-start; }
        .btn-group { flex-direction: column; }
        .btn-group .btn { width: 100%; }
        .equipment-detail-row { flex-direction: column; align-items: flex-start; gap: 4px; }
        .equipment-detail-value { text-align: left; }
        .delete-icon { font-size: 3rem; }
        .delete-title { font-size: 1.15rem; }
    }

    /* ================================================================
       PRINT
       ================================================================ */
    @media print {
        .page-header-actions, .btn, .btn-outline-light, .btn-group { display: none !important; }
        .page-header-card { background: #DC2626 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .card-modern { box-shadow: none !important; border: 1px solid #ddd !important; }
        .delete-icon, .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

<?php if ($equipment && empty($error_message)): 
    $stock = getStockStatus($equipment['quantity'], $equipment['reorder_level']);
    $expiry = getExpiryStatus($equipment['expiry_date']);
?>

    <!-- Red Danger Page Header Card -->
    <div class="page-header-card animate-fade-in-up">
        <div>
            <h1 class="page-header-title">
                <i class="fas fa-trash"></i>
                Delete Equipment
                <span class="role-badge-display">ADMIN</span>
                <span class="page-header-badge" style="background:rgba(252,165,165,0.35);">
                    <i class="fas fa-hashtag"></i> ID #<?= $equipment['id'] ?>
                </span>
            </h1>
            <p class="page-header-subtitle">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Warning:</strong> This action cannot be undone!
                <span class="page-header-badge" style="background:rgba(251,191,36,0.35);">
                    <i class="fas fa-link"></i> <?= $linked_count ?> linked lab test<?= $linked_count != 1 ? 's' : '' ?>
                </span>
            </p>
        </div>
        <div class="page-header-actions">
            <a href="view_equipment.php?id=<?= $equipment['id'] ?>&branch=<?= urlencode($return_branch) ?>" class="btn-outline-light">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="edit_equipment.php?id=<?= $equipment['id'] ?>&branch=<?= urlencode($return_branch) ?>" class="btn-outline-light">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="equipment_inventory.php?branch=<?= urlencode($return_branch) ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert-modern alert-modern-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- Delete Confirmation Card -->
    <div class="card-modern danger animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="card-modern-header" style="border-bottom-color:var(--page-danger);">
            <h3 class="card-modern-title" style="color:var(--page-danger);">
                <i class="fas fa-exclamation-triangle" style="color:var(--page-danger);"></i>
                Confirm Deletion
            </h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <span class="status-badge <?= $equipment['status'] === 'active' ? 'active' : 'inactive' ?>">
                    <?= ucfirst($equipment['status'] ?? 'active') ?>
                </span>
                <span class="stock-badge <?= $stock['class'] ?>">
                    <i class="fas <?= $stock['class'] === 'ok' ? 'fa-check-circle' : ($stock['class'] === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                    <?= $stock['label'] ?>
                </span>
            </div>
        </div>

        <!-- Delete Icon + Message -->
        <div style="text-align:center;padding:16px 0;">
            <i class="fas fa-trash delete-icon"></i>
            <h2 class="delete-title">Are you sure you want to delete this equipment?</h2>
            <p class="delete-subtitle">
                This action <strong>cannot be undone</strong>. All data associated with this equipment will be permanently removed.
            </p>
        </div>

        <!-- Equipment Details -->
        <div class="details-box">
            <div class="details-box-title">
                <i class="fas fa-info-circle"></i> Equipment Details
            </div>

            <div class="equipment-detail-row">
                <span class="equipment-detail-label"><i class="fas fa-tag"></i> Equipment Name</span>
                <span class="equipment-detail-value" style="color:var(--page-danger);font-weight:800;">
                    <?= htmlspecialchars($equipment['equipment_name']) ?>
                </span>
            </div>

            <div class="equipment-detail-row">
                <span class="equipment-detail-label"><i class="fas fa-th-list"></i> Category</span>
                <span class="equipment-detail-value"><?= htmlspecialchars($equipment['category'] ?? 'N/A') ?></span>
            </div>

            <div class="equipment-detail-row">
                <span class="equipment-detail-label"><i class="fas fa-cube"></i> Unit</span>
                <span class="equipment-detail-value"><?= htmlspecialchars($equipment['unit'] ?? 'pcs') ?></span>
            </div>

            <div class="equipment-detail-row">
                <span class="equipment-detail-label"><i class="fas fa-store-alt"></i> Branch</span>
                <span class="equipment-detail-value">
                    <?php if (empty($equipment['branch_id'])): ?>
                        <span class="branch-tag all-branches">🌐 All Branches</span>
                    <?php else: ?>
                        <span class="branch-tag"><?= htmlspecialchars($equipment['branch_name'] ?? 'N/A') ?></span>
                    <?php endif; ?>
                </span>
            </div>

            <div class="equipment-detail-row">
                <span class="equipment-detail-label"><i class="fas fa-boxes"></i> Quantity</span>
                <span class="equipment-detail-value"><?= number_format($equipment['quantity']) ?></span>
            </div>

            <div class="equipment-detail-row">
                <span class="equipment-detail-label"><i class="fas fa-barcode"></i> Batch Number</span>
                <span class="equipment-detail-value">
                    <span class="batch-number"><?= htmlspecialchars($equipment['batch_number']) ?></span>
                </span>
            </div>

            <div class="equipment-detail-row">
                <span class="equipment-detail-label"><i class="fas fa-calendar"></i> Expiry Date</span>
                <span class="equipment-detail-value">
                    <span class="expiry-badge <?= $expiry['class'] ?>">
                        <i class="fas <?= $expiry['class'] === 'valid' ? 'fa-check' : ($expiry['class'] === 'expiring' ? 'fa-clock' : ($expiry['class'] === 'expired' ? 'fa-skull' : 'fa-infinity')) ?>"></i>
                        <?= $expiry['label'] ?>
                    </span>
                </span>
            </div>

            <div class="equipment-detail-row">
                <span class="equipment-detail-label"><i class="fas fa-link"></i> Linked Lab Tests</span>
                <span class="equipment-detail-value">
                    <?php if ($linked_count > 0): ?>
                        <span style="color:var(--page-warning);font-weight:800;">
                            <i class="fas fa-exclamation-triangle"></i> <?= $linked_count ?> test<?= $linked_count != 1 ? 's' : '' ?> linked
                        </span>
                    <?php else: ?>
                        <span style="color:var(--page-success);font-weight:700;">
                            <i class="fas fa-check-circle"></i> No tests linked
                        </span>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- Warning Box (if linked) -->
        <?php if ($linked_count > 0): ?>
            <div class="warning-box">
                <i class="fas fa-exclamation-circle"></i>
                <div class="warning-box-content">
                    <strong>⚠️ Warning: Equipment is linked to <?= $linked_count ?> lab test<?= $linked_count != 1 ? 's' : '' ?></strong>
                    <p>
                        This equipment is currently linked to <?= $linked_count ?> lab test<?= $linked_count != 1 ? 's' : '' ?>.
                        Deleting it will also remove it from all linked lab tests.
                        <strong>The lab tests themselves will NOT be deleted.</strong>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="btn-group">
            <a href="view_equipment.php?id=<?= $equipment['id'] ?>&branch=<?= urlencode($return_branch) ?>" class="btn btn-outline">
                <i class="fas fa-times"></i> Cancel
            </a>
            <a href="?id=<?= $equipment['id'] ?>&branch=<?= urlencode($return_branch) ?>&confirm=yes" class="btn btn-danger">
                <i class="fas fa-trash"></i> Yes, Delete Equipment
            </a>
        </div>
    </div>

<?php else: ?>

    <!-- Equipment Not Found -->
    <div class="page-header-card animate-fade-in-up" style="background:linear-gradient(135deg, #DC2626, #991B1B);">
        <div>
            <h1 class="page-header-title">
                <i class="fas fa-exclamation-triangle"></i>
                Equipment Not Found
                <span class="role-badge-display">ERROR</span>
            </h1>
            <p class="page-header-subtitle">
                <i class="fas fa-tools"></i>
                The equipment you are trying to delete could not be found.
            </p>
        </div>
        <div class="page-header-actions">
            <a href="equipment_inventory.php?branch=<?= urlencode($return_branch) ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="card-modern animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="empty-state">
            <i class="fas fa-tools"></i>
            <h3>Equipment Not Found</h3>
            <p>The equipment with ID #<?= $equipment_id ?> could not be found in the system.</p>
            <p style="font-size:0.85rem;">It may have been deleted or the ID may be incorrect.</p>
            <div style="margin-top:20px;">
                <a href="equipment_inventory.php?branch=<?= urlencode($return_branch) ?>" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Equipment List
                </a>
            </div>
        </div>
    </div>

<?php endif; ?>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE BACKGROUND ENFORCEMENT
    // ================================================================
    function enforceDarkModeBackground() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var body = document.body;
        var mainContent = document.querySelector('.main-content');

        if (isDark) {
            if (body) body.style.background = '#0F172A';
            if (mainContent) mainContent.style.background = '#0F172A';
        } else {
            if (body) body.style.background = '#F0F4F8';
            if (mainContent) mainContent.style.background = '#F0F4F8';
        }
    }

    enforceDarkModeBackground();
    document.addEventListener('darkModeChanged', function() { setTimeout(enforceDarkModeBackground, 50); });
    new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            if (m.attributeName === 'data-theme') enforceDarkModeBackground();
        });
    }).observe(document.documentElement, { attributes: true });

    console.log('%c🗑️ Braick - Delete Equipment', 'font-size:18px; font-weight:bold; color:#DC2626;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🎨 Red danger page header + equipment details', 'font-size:13px; color:#DC2626;');
    console.log('%c🌙 FULL DARK MODE works', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>