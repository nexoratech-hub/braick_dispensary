<?php
// ================================================================
// FILE: frontend/components/doctor_header.php
// DOCTOR SHARED HEADER
// BRAICK DISPENSARY
// ================================================================

// Variables needed: $doctor, $selected_branch_id, $branches
$doctor_name = $doctor['full_name'] ?? 'Doctor';
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$branches = $branches ?? [];
$selected_branch_id = $selected_branch_id ?? 'all';
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - Braick Dispensary</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        /* ================================================================
           DOCTOR THEME - Green/Blue Theme
           ================================================================ */
        
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --primary-light: #34D399;
            --primary-bg: #ECFDF5;
            
            --blue-600: #0B5ED7;
            --blue-700: #0A4CA8;
            
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-hover: #F8FAFC;
            --border-color: #E2E8F0;
            
            --text-primary: #0F172A;
            --text-secondary: #64748B;
            
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-hover: #334155;
            --border-color: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            overflow-x: hidden;
        }
        
        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 8px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary-dark); }
        
        /* ===== TOP NAV ===== */
        .top-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 64px;
            background: var(--primary);
            color: white;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            box-shadow: var(--shadow-md);
        }
        
        .top-nav .brand-text {
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .top-nav .brand-text i {
            margin-right: 8px;
        }
        
        /* ===== SEARCH ===== */
        .search-wrapper {
            display: flex;
            align-items: center;
            background: rgba(255,255,255,0.15);
            border-radius: 10px;
            transition: all 0.3s ease;
            max-width: 400px;
            flex: 1;
        }
        
        .search-wrapper:focus-within {
            background: rgba(255,255,255,0.25);
            box-shadow: 0 0 0 3px rgba(255,255,255,0.2);
        }
        
        .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 12px;
            flex: 1;
            min-width: 120px;
            outline: none;
            color: white;
            font-size: 0.85rem;
        }
        
        .search-wrapper input::placeholder {
            color: rgba(255,255,255,0.6);
        }
        
        .search-wrapper .search-btn {
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            transition: background 0.3s ease;
        }
        
        .search-wrapper .search-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        /* ===== BRANCH SELECTOR ===== */
        .branch-selector {
            background: rgba(255,255,255,0.15);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            cursor: pointer;
            outline: none;
            transition: background 0.3s ease;
        }
        
        .branch-selector:hover {
            background: rgba(255,255,255,0.25);
        }
        
        .branch-selector option {
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        /* ===== DATETIME ===== */
        .datetime {
            font-size: 0.8rem;
            opacity: 0.8;
            display: none;
        }
        
        @media (min-width: 768px) {
            .datetime { display: inline; }
        }
        
        /* ===== DARK MODE TOGGLE ===== */
        .dark-toggle-btn {
            background: rgba(255,255,255,0.15);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dark-toggle-btn:hover {
            background: rgba(255,255,255,0.25);
        }
        
        /* ===== NOTIFICATION ===== */
        .icon-btn {
            background: rgba(255,255,255,0.15);
            color: white;
            border: none;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            cursor: pointer;
            transition: background 0.3s ease;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .icon-btn:hover {
            background: rgba(255,255,255,0.25);
        }
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            background: #EF4444;
            border-radius: 50%;
            animation: pulse-dot 1.5s infinite;
        }
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }
        
        /* ===== AVATAR ===== */
        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.3);
            transition: border-color 0.3s ease;
        }
        
        .avatar:hover {
            border-color: white;
        }
        
        /* ===== SIDEBAR ===== */
        .sidebar {
            position: fixed;
            top: 64px;
            left: 0;
            bottom: 0;
            width: 260px;
            background: var(--bg-card);
            border-right: 2px solid var(--border-color);
            overflow-y: auto;
            z-index: 40;
            transition: transform 0.3s ease;
            padding: 0 0 20px 0;
        }
        
        .sidebar-brand {
            padding: 16px 20px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sidebar-brand .brand-icon {
            width: 40px;
            height: 40px;
            background: var(--primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        .sidebar-brand .brand-name {
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-primary);
        }
        
        .sidebar-brand .brand-sub {
            font-size: 0.6rem;
            color: var(--text-secondary);
            display: block;
            font-weight: 400;
        }
        
        /* Sidebar User Profile */
        .sidebar-user {
            padding: 16px 20px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sidebar-user .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            color: white;
            flex-shrink: 0;
        }
        
        .sidebar-user .user-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-primary);
        }
        
        .sidebar-user .user-role {
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .sidebar-user .online-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #059669;
            margin-right: 4px;
        }
        
        /* Sidebar Links */
        .sidebar-nav {
            padding: 12px 12px;
        }
        
        .sidebar-nav .nav-section {
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-secondary);
            padding: 12px 12px 6px;
            font-weight: 700;
        }
        
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            margin: 2px 0;
            border-radius: 10px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .sidebar-link:hover {
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .sidebar-link.active {
            background: var(--primary);
            color: white;
        }
        
        .sidebar-link i {
            width: 20px;
            text-align: center;
            font-size: 1rem;
        }
        
        .sidebar-link .badge {
            margin-left: auto;
            background: var(--primary);
            color: white;
            font-size: 0.6rem;
            padding: 1px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .sidebar-link.active .badge {
            background: white;
            color: var(--primary);
        }
        
        .sidebar-link .badge-danger {
            background: #EF4444;
            color: white;
        }
        
        .sidebar-link .badge-warning {
            background: #F59E0B;
            color: white;
        }
        
        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 260px;
            margin-top: 64px;
            padding: 1.5rem;
            min-height: calc(100vh - 64px);
        }
        
        /* ===== PAGE HEADER ===== */
        .page-header {
            border-bottom: 3px solid var(--primary);
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        
        .page-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .page-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
        
        /* ===== ANIMATIONS ===== */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        .animate-fade-in-up:nth-child(1) { animation-delay: 0.05s; }
        .animate-fade-in-up:nth-child(2) { animation-delay: 0.10s; }
        .animate-fade-in-up:nth-child(3) { animation-delay: 0.15s; }
        .animate-fade-in-up:nth-child(4) { animation-delay: 0.20s; }
        .animate-fade-in-up:nth-child(5) { animation-delay: 0.25s; }
        .animate-fade-in-up:nth-child(6) { animation-delay: 0.30s; }
        
        /* ===== STAT CARDS ===== */
        .stat-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 18px 20px;
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
            cursor: default;
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .stat-card .stat-icon.green { background: var(--primary-bg); color: var(--primary); }
        .stat-card .stat-icon.blue { background: #E8F0FE; color: #0B5ED7; }
        .stat-card .stat-icon.yellow { background: #FEF3C7; color: #F59E0B; }
        .stat-card .stat-icon.purple { background: #EDE9FE; color: #7C3AED; }
        
        [data-theme="dark"] .stat-card .stat-icon.green { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .stat-card .stat-icon.blue { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .stat-card .stat-icon.yellow { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .stat-card .stat-icon.purple { background: #2D1B4E; color: #A78BFA; }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .stat-trend {
            font-size: 0.65rem;
            color: var(--text-secondary);
            margin-top: 2px;
            display: block;
        }
        
        /* ===== CARD ===== */
        .card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 20px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.06);
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* ===== TOAST ===== */
        .toast-custom {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--bg-card);
            color: var(--text-primary);
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.5s ease;
            border-left: 4px solid var(--primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { border-left-color: #059669; }
        .toast-custom.error { border-left-color: #EF4444; }
        .toast-custom.info { border-left-color: #0B5ED7; }
        
        /* ===== FOOTER ===== */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ===== GRID HELPERS ===== */
        .grid-cols-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        .grid-cols-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
        .grid-cols-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
        
        @media (max-width: 768px) {
            .grid-cols-2, .grid-cols-3, .grid-cols-4 {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 480px) {
            .grid-cols-2, .grid-cols-3, .grid-cols-4 {
                grid-template-columns: 1fr;
            }
        }
        
        .flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-2 { gap: 0.5rem; }
        .gap-3 { gap: 0.75rem; }
        .gap-4 { gap: 1rem; }
        .gap-5 { gap: 1.25rem; }
        .mt-2 { margin-top: 0.5rem; }
        .mt-4 { margin-top: 1rem; }
        .mt-5 { margin-top: 1.25rem; }
        .mb-4 { margin-bottom: 1rem; }
        .mb-5 { margin-bottom: 1.25rem; }
        .ml-2 { margin-left: 0.5rem; }
        .mr-2 { margin-right: 0.5rem; }
        .mx-2 { margin-left: 0.5rem; margin-right: 0.5rem; }
        .text-center { text-align: center; }
        .text-sm { font-size: 0.85rem; }
        .text-xs { font-size: 0.75rem; }
        .text-gray-400 { color: var(--text-secondary); }
        .text-gray-500 { color: var(--text-secondary); opacity: 0.7; }
        .font-medium { font-weight: 500; }
        .font-bold { font-weight: 700; }
        .hidden { display: none; }
        .block { display: block; }
        .w-full { width: 100%; }
        
        @media (min-width: 640px) {
            .sm\\:block { display: block; }
            .sm\\:inline { display: inline; }
            .sm\\:hidden { display: none; }
        }
        @media (min-width: 1024px) {
            .lg\\:hidden { display: none; }
            .lg\\:block { display: block; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-3 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn" style="background:transparent; width:auto;">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <span class="brand-text hidden sm:inline">
            <i class="fas fa-stethoscope"></i> Braick Dispensary
        </span>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-white ml-3 opacity-60"></i>
            <input type="text" id="searchInput" placeholder="Search patients...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn" id="notifBtn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2236%22 height=%2236%22%3E%3Crect width=%2236%22 height=%2236%22 fill=%22%23059669%22 rx=%2250%25%22/%3E%3Ctext x=%2218%22 y=%2224%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2216%22 font-weight=%22bold%22%3E<?= strtoupper(substr($doctor['full_name'], 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

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
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebarToggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('doctorSidebar');
    
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

    console.log('%c👨‍⚕️ Braick - Doctor Dashboard', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 Welcome Dr. <?= htmlspecialchars($doctor['full_name']) ?>', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>