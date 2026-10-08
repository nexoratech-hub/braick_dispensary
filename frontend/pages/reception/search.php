<?php
// ================================================================
// FILE: frontend/pages/reception/search.php
// RECEPTION - SEARCH PATIENTS (BRANCH FILTERED)
// ✅ Inatumia shared header + sidebar
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

// ================================================================
// ACCESS CONTROL
// ================================================================
$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    switch ($_SESSION['role']) {
        case 'doctor':     header('Location: ../doctor/dashboard.php');     break;
        case 'pharmacy':   header('Location: ../pharmacy/dashboard.php');   break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier':    header('Location: ../cashier/dashboard.php');    break;
        default:           header('Location: ../login.php');                break;
    }
    exit;
}

// ================================================================
// SESSION DATA
// ================================================================
$user_id     = $_SESSION['user_id']     ?? 0;
$full_name   = $_SESSION['full_name']   ?? 'User';
$role        = $_SESSION['role']        ?? 'reception';
$branch_id   = $_SESSION['branch_id']   ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username    = $_SESSION['username']    ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

$query                = isset($_GET['q']) ? trim($_GET['q']) : '';
$results              = [];
$total_results        = 0;
$unread_notifications = 0;

try {
    $db = Database::getInstance()->getConnection();

    // ================================================================
    // SEARCH PATIENTS (BRANCH FILTERED)
    // ================================================================
    if (!empty($query)) {
        $stmt = $db->prepare("
            SELECT id, patient_id, full_name, date_of_birth, gender,
                   phone, email, address, blood_group, allergies,
                   emergency_contact, created_at, created_by, assigned_doctor_id
            FROM patients
            WHERE branch_id = ?
            AND (full_name LIKE ? OR patient_id LIKE ? OR phone LIKE ? OR email LIKE ?)
            ORDER BY full_name
            LIMIT 50
        ");
        $search_term = "%$query%";
        $stmt->execute([$branch_id, $search_term, $search_term, $search_term, $search_term]);
        $results       = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_results = count($results);
    }

    // ================================================================
    // UNREAD NOTIFICATIONS
    // ================================================================
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
        $stmt = $db->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_notifications = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

} catch (Exception $e) {
    $results              = [];
    $total_results        = 0;
    $unread_notifications = 0;
    error_log("Search error: " . $e->getMessage());
}

// ================================================================
// PROFILE URLS
// ================================================================
$profile_pic_url = !empty($profile_pic)
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/reception_header.php';
include_once __DIR__ . '/../../components/reception_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= (isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true') ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Patients - Braick Dispensary</title>

    <link rel="icon" href="<?= htmlspecialchars($logo_path) ?>" type="image/png">
    <link rel="shortcut icon" href="<?= htmlspecialchars($logo_path) ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* ================================================================
           PAGE-SPECIFIC STYLES
           (Header/Sidebar handled by shared components)
           ================================================================ */

        /* PAGE HEADER */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%; right: -20%;
            width: 300px; height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        .page-header .page-title {
            color: #fff;
            font-size: 1.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .page-header .page-title i { font-size: 1.95rem; opacity: .9; }
        .page-header .page-subtitle {
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
        .page-header .page-subtitle strong { color: #fff; font-weight: 600; }

        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: #fff;
            padding: 3px 12px;
            border-radius: 999px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .header-badge {
            background: rgba(255,255,255,0.15);
            color: #fff;
            padding: 4px 14px;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 500;
            backdrop-filter: blur(6px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .btn-outline-light {
            background: rgba(255,255,255,0.14);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.22);
            padding: 9px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all .25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(6px);
            position: relative;
            z-index: 1;
        }
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.26);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.15);
        }

        /* CARD */
        .card {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            transition: all .3s ease;
            box-shadow: var(--shadow-md);
        }
        .card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 30px rgba(11, 94, 215, 0.08);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--border-color);
            flex-wrap: wrap;
            gap: 8px;
        }
        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .title-blue { color: var(--primary); }

        /* RESULT ITEMS */
        .result-item {
            background: var(--bg-body);
            border-radius: 12px;
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            transition: all .3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .result-item:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 14px rgba(11, 94, 215, 0.10);
            background: var(--bg-card);
            transform: translateY(-1px);
        }
        .result-item .name {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-primary);
        }
        .result-item .details {
            font-size: 0.78rem;
            color: var(--text-secondary);
            margin-top: 4px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }
        .result-item .details i { color: var(--primary); }
        .result-item .details .sep {
            color: var(--border-color);
            margin: 0 4px;
        }
        .result-item .actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        /* HIGHLIGHT */
        .highlight {
            background: #FEF08A;
            color: #854D0E;
            padding: 1px 4px;
            border-radius: 4px;
            font-weight: 700;
        }
        [data-theme="dark"] .highlight {
            background: #854D0E;
            color: #FEF08A;
        }

        /* BUTTONS */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.78rem;
            transition: all .25s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
        }
        .btn-blue {
            background: var(--primary);
            color: #fff;
        }
        .btn-blue:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.28);
        }
        .btn-green {
            background: var(--success);
            color: #fff;
        }
        .btn-green:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.28);
        }
        .btn-sm { padding: 6px 12px; font-size: 0.72rem; border-radius: 8px; }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-secondary);
        }
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 14px;
        }
        .empty-state p {
            font-size: 0.95rem;
            color: var(--text-primary);
            font-weight: 600;
        }
        .empty-state .sub {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 400;
            margin-top: 4px;
        }

        /* FOOTER */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .card { padding: 16px 18px; }
            .result-item { flex-direction: column; align-items: flex-start; }
            .result-item .actions { width: 100%; justify-content: flex-start; }
        }
        @media (max-width: 640px) {
            .card { padding: 12px 14px; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- ============================================================
         PAGE HEADER
         ============================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-search"></i>
                Search Patients
                <span class="role-badge-display">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                Search patients in <strong><?= htmlspecialchars($branch_name) ?></strong>
                <?php if (!empty($query)): ?>
                    <span class="header-badge">
                        <i class="fas fa-search"></i> "<?= htmlspecialchars($query) ?>"
                    </span>
                    <span class="header-badge">
                        <i class="fas fa-user"></i> <?= $total_results ?> patient(s) found
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="new_patient.php" class="btn-outline-light">
                <i class="fas fa-user-plus"></i> Register Patient
            </a>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ============================================================
         SEARCH RESULTS
         ============================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue"></i> Search Results
            </h3>
            <span style="font-size:.78rem;color:var(--text-secondary);">
                <?= $total_results ?> record(s)
            </span>
        </div>

        <?php if (!empty($query)): ?>

            <?php if ($total_results > 0): ?>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <?php foreach ($results as $patient):
                        $name = htmlspecialchars($patient['full_name'] ?? 'Unknown');
                        $highlighted_name = preg_replace(
                            '/(' . preg_quote($query, '/') . ')/i',
                            '<span class="highlight">$1</span>',
                            $name
                        );
                        $patient_id  = htmlspecialchars($patient['patient_id'] ?? 'N/A');
                        $phone       = htmlspecialchars($patient['phone'] ?? 'N/A');
                        $email       = htmlspecialchars($patient['email'] ?? 'N/A');
                        $gender      = htmlspecialchars($patient['gender'] ?? 'N/A');
                        $blood_group = htmlspecialchars($patient['blood_group'] ?? 'N/A');
                    ?>
                        <div class="result-item">
                            <div style="min-width:0;flex:1;">
                                <p class="name">
                                    <?= $highlighted_name ?>
                                    <span style="font-size:.72rem;color:var(--text-secondary);font-weight:400;margin-left:6px;">
                                        <i class="fas fa-<?= $gender === 'Male' ? 'mars' : ($gender === 'Female' ? 'venus' : 'genderless') ?>"></i>
                                        <?= $gender ?>
                                    </span>
                                    <?php if (!empty($blood_group) && $blood_group !== 'N/A'): ?>
                                        <span style="font-size:.72rem;color:#DC2626;font-weight:400;margin-left:6px;">
                                            <i class="fas fa-tint"></i> <?= $blood_group ?>
                                        </span>
                                    <?php endif; ?>
                                </p>
                                <p class="details">
                                    <span><i class="fas fa-id-card"></i> <?= $patient_id ?></span>
                                    <span class="sep">|</span>
                                    <span><i class="fas fa-phone"></i> <?= $phone ?></span>
                                    <span class="sep">|</span>
                                    <span><i class="fas fa-envelope"></i> <?= $email ?></span>
                                    <span class="sep">|</span>
                                    <span><i class="fas fa-calendar-alt"></i>
                                        <?= isset($patient['created_at']) ? date('M d, Y', strtotime($patient['created_at'])) : 'N/A' ?>
                                    </span>
                                </p>
                            </div>
                            <div class="actions">
                                <a href="view_patient.php?id=<?= (int)$patient['id'] ?>" class="btn btn-blue btn-sm" title="View Patient">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="new_appointment.php?patient_id=<?= (int)$patient['id'] ?>" class="btn btn-green btn-sm" title="New Appointment">
                                    <i class="fas fa-calendar-plus"></i> Appointment
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>No patients found matching "<strong><?= htmlspecialchars($query) ?></strong>"</p>
                    <p class="sub">Try searching by name, patient ID, phone number or email</p>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <p>Enter a search term to find patients</p>
                <p class="sub">Search by name, patient ID, phone number or email</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 6px;opacity:.4;">|</span>
            Search Patients
            <span style="margin:0 6px;opacity:.4;">|</span>
            <span id="footerTimestamp">Last updated: <?= date('h:i:s A') ?></span>
            <span style="margin:0 6px;opacity:.4;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
    // ================================================================
    // SYNC SEARCH WITH HEADER
    // Header ina 'searchInput' (kutoka shared header)
    // ================================================================
    document.addEventListener('DOMContentLoaded', function () {
        var headerSearchInput = document.getElementById('searchInput');
        var headerSearchBtn   = document.querySelector('.search-wrapper .search-btn') ||
                                document.querySelector('.search-btn');

        if (headerSearchInput) {
            headerSearchInput.value = <?= json_encode($query) ?>;

            if (headerSearchInput.value.trim() !== '') {
                headerSearchInput.focus();
                headerSearchInput.setSelectionRange(
                    headerSearchInput.value.length,
                    headerSearchInput.value.length
                );
            }

            headerSearchInput.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var q = this.value.trim();
                    if (q.length > 0) {
                        window.location.href = 'search.php?q=' + encodeURIComponent(q);
                    }
                }
            });
        }

        if (headerSearchBtn) {
            headerSearchBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var q = (headerSearchInput?.value || '').trim();
                if (q.length > 0) {
                    window.location.href = 'search.php?q=' + encodeURIComponent(q);
                }
            });
        }
    });

    // ================================================================
    // FOOTER CLOCK (header clock handled by shared header)
    // ================================================================
    setInterval(function () {
        var now = new Date();
        var t   = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTimestamp');
        if (ftEl) ftEl.textContent = 'Last updated: ' + t;
    }, 1000);

    // ================================================================
    // TOAST — Inatumia ya header kama ipo
    // ================================================================
    if (typeof window.showToast !== 'function') {
        window.showToast = function (title, message, type) {
            console.log('[' + type + '] ' + title + ': ' + message);
        };
    }

    console.log('%c🔍 Braick - Search Patients (Branch Filtered)', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
    console.log('%c✅ Inatumia shared header + sidebar', 'font-size:13px;color:#059669;font-weight:bold;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px;color:#059669;');
    console.log('%c📊 Results: <?= $total_results ?>', 'font-size:13px;color:#64748B;');
    console.log('%c🔍 Query: <?= htmlspecialchars($query) ?: 'Empty' ?>', 'font-size:13px;color:#64748B;');
</script>

</body>
</html>