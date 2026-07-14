<?php
// ================================================================
// FILE: frontend/pages/reception/new_patient.php
// RECEPTION - REGISTER NEW PATIENT (WITH AUTO BILL)
// REGISTRATION FEE IN BACKGROUND - CASHIER ANAONA
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Rose Mwangi (Reception)
// ================================================================
$_SESSION['user_id'] = 6;
$_SESSION['full_name'] = 'Rose Mwangi';
$_SESSION['role'] = 'reception';
$_SESSION['branch_id'] = 1;
$_SESSION['branch_name'] = 'Dodoma';
$_SESSION['username'] = 'reception.rose';
$_SESSION['is_admin'] = false;

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$selected_branch_id = $user_branch_id;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$message = '';
$message_type = '';

try {
    $db = getDB();
    
    // ================================================================
    // GET REGISTRATION FEE FROM SERVICES
    // ================================================================
    $registration_fee = 0;
    $registration_service_name = 'Registration Fee';
    
    $stmt = $db->prepare("SELECT id, service_name, price FROM services WHERE category = 'registration' AND is_active = 1 LIMIT 1");
    $stmt->execute();
    $registration_service = $stmt->fetch();
    
    if ($registration_service) {
        $registration_fee = $registration_service['price'];
        $registration_service_name = $registration_service['service_name'];
    }
    
    // Get branches (only user's branch)
    $branches = [];
    $branch = getBranch($selected_branch_id);
    if ($branch) {
        $branches[] = $branch;
    }
    
    // Generate patient ID
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM patients WHERE branch_id = ?");
    $stmt->execute([$selected_branch_id]);
    $count = $stmt->fetch()['total'] ?? 0;
    $next_id = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    $patient_id_number = 'P-' . date('Y') . '-' . $next_id;
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $full_name = trim($_POST['full_name'] ?? '');
        $date_of_birth = $_POST['date_of_birth'] ?? null;
        $gender = $_POST['gender'] ?? null;
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $emergency_contact = trim($_POST['emergency_contact'] ?? '');
        $blood_group = $_POST['blood_group'] ?? null;
        $allergies = trim($_POST['allergies'] ?? '');
        $branch_id = $selected_branch_id;
        
        // Validation
        $errors = [];
        if (empty($full_name)) $errors[] = 'Full name is required';
        if (empty($gender)) $errors[] = 'Gender is required';
        if (empty($phone)) $errors[] = 'Phone number is required';
        
        if (empty($errors)) {
            // ================================================================
            // 1. INSERT PATIENT
            // ================================================================
            $stmt = $db->prepare("
                INSERT INTO patients (
                    patient_id, full_name, date_of_birth, gender, phone, email, 
                    address, emergency_contact, blood_group, allergies, branch_id, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([
                $patient_id_number, $full_name, $date_of_birth, $gender, $phone, $email,
                $address, $emergency_contact, $blood_group, $allergies, $branch_id, $_SESSION['user_id']
            ])) {
                $patient_db_id = $db->lastInsertId();
                
                // ================================================================
                // 2. CREATE VISIT
                // ================================================================
                $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad($patient_db_id, 4, '0', STR_PAD_LEFT);
                
                $stmt = $db->prepare("
                    INSERT INTO visits (
                        visit_number, patient_id, receptionist_id, branch_id, visit_type, status
                    ) VALUES (?, ?, ?, ?, 'new', 'pending')
                ");
                $stmt->execute([$visit_number, $patient_db_id, $_SESSION['user_id'], $branch_id]);
                $visit_id = $db->lastInsertId();
                
                // ================================================================
                // 3. CREATE BILL WITH REGISTRATION FEE (BACKGROUND)
                // ================================================================
                if ($registration_fee > 0) {
                    $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($patient_db_id, 6, '0', STR_PAD_LEFT);
                    
                    // Check if patient_bills table exists, if not create it
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO patient_bills (
                                bill_number, patient_id, visit_id, 
                                registration_fee, subtotal, total_amount, balance, 
                                status, created_by, branch_id
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)
                        ");
                        $subtotal = $registration_fee;
                        $stmt->execute([
                            $bill_number,
                            $patient_db_id,
                            $visit_id,
                            $registration_fee,
                            $subtotal,
                            $subtotal,
                            $subtotal,
                            $_SESSION['user_id'],
                            $branch_id
                        ]);
                        $bill_id = $db->lastInsertId();
                        
                        // ================================================================
                        // 4. ADD REGISTRATION FEE TO BILL ITEMS
                        // ================================================================
                        $stmt = $db->prepare("
                            INSERT INTO bill_items (bill_id, item_type, item_name, quantity, unit_price, total_price)
                            VALUES (?, 'registration', ?, 1, ?, ?)
                        ");
                        $stmt->execute([
                            $bill_id,
                            $registration_service_name,
                            $registration_fee,
                            $registration_fee
                        ]);
                        
                        $_SESSION['current_bill_id'] = $bill_id;
                        
                    } catch (Exception $e) {
                        // If patient_bills table doesn't exist, log error but continue
                        error_log("Bill creation failed: " . $e->getMessage());
                    }
                }
                
                $_SESSION['current_patient_id'] = $patient_db_id;
                $_SESSION['current_visit_id'] = $visit_id;
                
                // Log activity
                try {
                    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'patient_registered', ?)");
                    $stmt->execute([$_SESSION['user_id'], "New patient registered: $full_name (ID: $patient_id_number) in $branch_name"]);
                } catch (Exception $e) {}
                
                $message = "Patient registered successfully! Patient ID: <strong>$patient_id_number</strong>";
                if ($registration_fee > 0) {
                    $message .= "<br>Registration Fee: <strong>TSh " . number_format($registration_fee) . "</strong> added to bill automatically.";
                }
                $message_type = 'success';
                
                echo '<script>setTimeout(function(){ window.location.href = "../doctor/patient_details.php?id=' . $patient_db_id . '&visit_id=' . $visit_id . '"; }, 2000);</script>';
            } else {
                $message = "Failed to register patient!";
                $message_type = 'error';
            }
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $branches = [];
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/reception_header.php';
include_once __DIR__ . '/../../components/reception_sidebar.php';
?>

<style>
    .form-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    .form-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
    }
    .form-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
        display: block;
    }
    .form-label .required {
        color: var(--danger);
        margin-left: 2px;
    }
    .form-control {
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
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.5;
    }
    .form-row {
        margin-bottom: 14px;
    }
    .form-row:last-child {
        margin-bottom: 0;
    }
    .form-actions {
        display: flex;
        gap: 12px;
        padding-top: 16px;
        margin-top: 16px;
        border-top: 2px solid var(--border-color);
        flex-wrap: wrap;
    }
    select.form-control {
        appearance: auto;
        cursor: pointer;
    }
    textarea.form-control {
        resize: vertical;
        min-height: 60px;
    }
    .role-badge-display {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: var(--primary-bg);
        color: var(--primary);
        text-transform: uppercase;
    }
    [data-theme="dark"] .role-badge-display {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    .branch-badge-display {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: var(--success-bg);
        color: var(--success);
    }
    [data-theme="dark"] .branch-badge-display {
        background: #1A3A2A;
        color: #34D399;
    }
    .fee-info {
        background: var(--success-bg);
        border: 1px solid var(--success);
        border-radius: 10px;
        padding: 10px 14px;
        color: var(--success);
        font-size: 0.85rem;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    [data-theme="dark"] .fee-info {
        background: #1A3A2A;
        border-color: #34D399;
        color: #34D399;
    }
    .fee-info i {
        font-size: 1.1rem;
    }
</style>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search patients...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-plus mr-2" style="color: var(--primary);"></i> Register New Patient
                <span class="role-badge-display ml-2">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                Create a new patient record in <?= htmlspecialchars($branch_name) ?>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-id-card mr-1"></i> Next ID: <?= $patient_id_number ?>
                </span>
            </p>
        </div>
        <div>
            <a href="patients.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>
    </div>

    <!-- Fee Info - Hidden from normal view, shows that fee is automatic -->
    <div class="fee-info">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>Registration Fee: TSh <?= number_format($registration_fee) ?></strong>
            <span style="font-size:0.8rem; opacity:0.8;"> - This will be added to the patient's bill automatically (visible to cashier only)</span>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- REGISTRATION FORM -->
    <!-- ================================================================ -->
    <div class="form-card">
        <form method="POST" action="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <!-- Full Name -->
                <div class="form-row md:col-span-2">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" name="full_name" class="form-control" placeholder="e.g. John Doe" 
                           value="<?= htmlspecialchars($full_name ?? '') ?>" required>
                </div>
                
                <!-- Date of Birth -->
                <div class="form-row">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" 
                           value="<?= htmlspecialchars($date_of_birth ?? '') ?>">
                </div>
                
                <!-- Gender -->
                <div class="form-row">
                    <label class="form-label">Gender <span class="required">*</span></label>
                    <select name="gender" class="form-control" required>
                        <option value="">Select Gender</option>
                        <option value="Male" <?= ($gender ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($gender ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= ($gender ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                
                <!-- Phone -->
                <div class="form-row">
                    <label class="form-label">Phone Number <span class="required">*</span></label>
                    <input type="tel" name="phone" class="form-control" placeholder="e.g. 0759 154 160" 
                           value="<?= htmlspecialchars($phone ?? '') ?>" required>
                </div>
                
                <!-- Email -->
                <div class="form-row">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. john@example.com" 
                           value="<?= htmlspecialchars($email ?? '') ?>">
                </div>
                
                <!-- Address -->
                <div class="form-row md:col-span-2">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" placeholder="Enter full address..."><?= htmlspecialchars($address ?? '') ?></textarea>
                </div>
                
                <!-- Emergency Contact -->
                <div class="form-row">
                    <label class="form-label">Emergency Contact</label>
                    <input type="tel" name="emergency_contact" class="form-control" placeholder="e.g. 0755 123 456" 
                           value="<?= htmlspecialchars($emergency_contact ?? '') ?>">
                </div>
                
                <!-- Blood Group -->
                <div class="form-row">
                    <label class="form-label">Blood Group</label>
                    <select name="blood_group" class="form-control">
                        <option value="">Select Blood Group</option>
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                            <option value="<?= $bg ?>" <?= ($blood_group ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Allergies -->
                <div class="form-row md:col-span-2">
                    <label class="form-label">Allergies</label>
                    <textarea name="allergies" class="form-control" placeholder="List any known allergies..."><?= htmlspecialchars($allergies ?? '') ?></textarea>
                </div>
                
                <!-- Branch (hidden - forced to user's branch) -->
                <input type="hidden" name="branch_id" value="<?= $selected_branch_id ?>">
                
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-blue">
                    <i class="fas fa-save"></i> Register Patient
                </button>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="patients.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Register Patient
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
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
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
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
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // TOAST
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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    console.log('%c👤 Braick - New Patient Registration (With Auto Bill)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Next Patient ID: <?= $patient_id_number ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Registration Fee: TSh <?= number_format($registration_fee) ?> (Added to bill automatically)', 'font-size:13px; color:#F59E0B;');
</script>

</body>
</html>