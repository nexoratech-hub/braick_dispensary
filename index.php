<?php
// ================================================================
// BRAICK DISPENSARY - WEBSITE HOME PAGE
// ================================================================

// Language selection
session_start();
$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'en';
$_SESSION['lang'] = $lang;

// Translations
$translations = [
    'en' => [
        'title' => 'Braick Dispensary - Quality Healthcare Services',
        'brand' => 'Braick Dispensary',
        'home' => 'Home',
        'services' => 'Services',
        'appointment' => 'Appointment',
        'contact' => 'Contact',
        'staff_login' => 'Staff Login',
        'hero_title' => 'Your Health, <span class="text-green-300">Our Priority</span>',
        'hero_sub' => 'Quality healthcare services with modern medical solutions and professional care. Committed to your health and wellbeing.',
        'book_appointment' => 'Book Appointment',
        'contact_us' => 'Contact Us',
        'years_experience' => 'Years Experience',
        'happy_patients' => 'Happy Patients',
        'medical_support' => 'Medical Support',
        'our_services' => 'Our Services',
        'services_sub' => 'We offer a wide range of medical services to meet all your healthcare needs.',
        'general_consultation' => 'General Consultation',
        'general_consultation_desc' => 'Professional medical consultations with experienced doctors for all health concerns.',
        'laboratory_services' => 'Laboratory Services',
        'laboratory_services_desc' => 'Comprehensive diagnostic laboratory tests with accurate and timely results.',
        'pharmacy_services' => 'Pharmacy Services',
        'pharmacy_services_desc' => 'Quality prescription and over-the-counter medicines from our professional pharmacy.',
        'health_checkups' => 'Health Checkups',
        'health_checkups_desc' => 'Regular health checkups and preventive care to keep you healthy and strong.',
        'family_healthcare' => 'Family Healthcare',
        'family_healthcare_desc' => 'Comprehensive family healthcare services for all ages from children to seniors.',
        'emergency_care' => 'Emergency Care',
        'emergency_care_desc' => '24/7 emergency medical care with quick response and professional treatment.',
        'book_now' => 'Book Now',
        'appointment_title' => 'Request an Appointment',
        'appointment_sub' => 'Book your appointment online and we will get back to you promptly.',
        'full_name' => 'Full Name',
        'phone_number' => 'Phone Number',
        'email_address' => 'Email Address',
        'preferred_date' => 'Preferred Date',
        'service_required' => 'Service Required',
        'select_service' => 'Select Service',
        'message' => 'Message',
        'request_appointment' => 'Request Appointment',
        'get_in_touch' => 'Get in Touch',
        'contact_sub' => 'Reach out to us for any inquiries or assistance.',
        'phone' => 'Phone',
        'email' => 'Email',
        'location' => 'Location',
        'working_hours' => 'Working Hours',
        'quick_links' => 'Quick Links',
        'our_services_footer' => 'Our Services',
        'staff_access' => 'Staff Access',
        'access_staff' => 'Access the staff management system.',
        'login' => 'LOGIN',
        'staff_only' => '🔒 Staff members only. Patients cannot access this login.',
        'all_rights' => 'All rights reserved.',
        'designed_with' => 'Designed with',
        'for_better' => 'for better healthcare',
        'consultation' => 'Consultation',
        'laboratory' => 'Laboratory',
        'pharmacy' => 'Pharmacy',
        'emergency' => 'Emergency',
        'dark_mode' => 'Dark Mode',
        'light_mode' => 'Light Mode',
        'search_placeholder' => 'Search services, doctors...',
        'find_services' => 'Find Services',
    ],
    'sw' => [
        'title' => 'Braick Dispensary - Huduma Bora za Afya',
        'brand' => 'Braick Dispensary',
        'home' => 'Nyumbani',
        'services' => 'Huduma',
        'appointment' => 'Miadi',
        'contact' => 'Wasiliana',
        'staff_login' => 'Ingia kwa Wafanyakazi',
        'hero_title' => 'Afya Yako, <span class="text-green-300">Kipaumbele Chetu</span>',
        'hero_sub' => 'Huduma bora za afya kwa suluhisho za kisasa za matibabu na huduma ya kitaalamu. Tumejitolea kwa afya yako na ustawi wako.',
        'book_appointment' => 'Weka Miadi',
        'contact_us' => 'Wasiliana Nasi',
        'years_experience' => 'Miaka ya Uzoefu',
        'happy_patients' => 'Wagonjwa Wenye Furaha',
        'medical_support' => 'Msaada wa Matibabu',
        'our_services' => 'Huduma Zetu',
        'services_sub' => 'Tunatoa huduma mbalimbali za matibabu kukidhi mahitaji yako yote ya afya.',
        'general_consultation' => 'Ushauri wa Jumla',
        'general_consultation_desc' => 'Ushauri wa kitaalamu wa matibabu na madaktari wenye uzoefu kwa wasiwasi wote wa afya.',
        'laboratory_services' => 'Huduma za Maabara',
        'laboratory_services_desc' => 'Vipimo kamili vya uchunguzi wa maabara na matokeo sahihi na kwa wakati.',
        'pharmacy_services' => 'Huduma za Famasia',
        'pharmacy_services_desc' => 'Dawa za ubora wa maagizo na dawa za kaunta kutoka kwa famasia yetu ya kitaalamu.',
        'health_checkups' => 'Ukaguzi wa Afya',
        'health_checkups_desc' => 'Ukaguzi wa mara kwa mara wa afya na huduma ya kinga ili kukuweka mwenye afya na nguvu.',
        'family_healthcare' => 'Hudama za Kifamilia',
        'family_healthcare_desc' => 'Huduma kamili za afya ya familia kwa rika zote kutoka watoto hadi wazee.',
        'emergency_care' => 'Huduma za Dharura',
        'emergency_care_desc' => 'Huduma ya dharura ya 24/7 kwa majibu ya haraka na matibabu ya kitaalamu.',
        'book_now' => 'Weka Sasa',
        'appointment_title' => 'Omba Miadi',
        'appointment_sub' => 'Omba miadi yako mtandaoni na tutawasiliana nawe haraka.',
        'full_name' => 'Jina Kamili',
        'phone_number' => 'Nambari ya Simu',
        'email_address' => 'Barua Pepe',
        'preferred_date' => 'Tarehe Unayopenda',
        'service_required' => 'Huduma Inayohitajika',
        'select_service' => 'Chagua Huduma',
        'message' => 'Ujumbe',
        'request_appointment' => 'Omba Miadi',
        'get_in_touch' => 'Wasiliana Nasi',
        'contact_sub' => 'Wasiliana nasi kwa maswali yoyote au msaada.',
        'phone' => 'Simu',
        'email' => 'Barua Pepe',
        'location' => 'Eneo',
        'working_hours' => 'Saa za Kufanya Kazi',
        'quick_links' => 'Viungo vya Haraka',
        'our_services_footer' => 'Huduma Zetu',
        'staff_access' => 'Ufikiaji wa Wafanyakazi',
        'access_staff' => 'Fikia mfumo wa usimamizi wa wafanyakazi.',
        'login' => 'INGIA',
        'staff_only' => '🔒 Wafanyakazi pekee. Wagonjwa hawawezi kufikia ingia hii.',
        'all_rights' => 'Haki zote zimehifadhiwa.',
        'designed_with' => 'Imeundwa kwa',
        'for_better' => 'kwa afya bora',
        'consultation' => 'Ushauri',
        'laboratory' => 'Maabara',
        'pharmacy' => 'Famasia',
        'emergency' => 'Dharura',
        'dark_mode' => 'Hali ya Giza',
        'light_mode' => 'Hali ya Mwanga',
        'search_placeholder' => 'Tafuta huduma, madaktari...',
        'find_services' => 'Tafuta Huduma',
    ]
];

$t = $translations[$lang] ?? $translations['en'];

function __($key) {
    global $t;
    return $t[$key] ?? $key;
}

// Logo path - FIXED
$logo_path = 'assets/images/braick_logo.png';
$bg_path = 'assets/images/dispensary-bg.jpg';

// Also try frontend path if exists
if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/' . $logo_path)) {
    $logo_path = 'frontend/assets/images/braick_logo.png';
}
if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/' . $bg_path)) {
    $bg_path = 'frontend/assets/images/bg.PNG';
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('title') ?></title>
    <meta name="description" content="Braick Dispensary offers quality healthcare services with modern medical solutions and professional care.">
    
    <!-- Favicon -->
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS (for production, use CDN only for development) -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --secondary: #0AA84F;
            --secondary-dark: #08944A;
            --bg-body: #FFFFFF;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.06);
            --navbar-bg: rgba(255,255,255,0.95);
            --hero-overlay: rgba(11, 94, 215, 0.92);
            --input-bg: #F8FAFC;
        }

        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.4);
            --navbar-bg: rgba(15, 23, 42, 0.95);
            --hero-overlay: rgba(11, 94, 215, 0.88);
            --input-bg: #1E293B;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: all 0.3s ease;
            overflow-x: hidden;
        }
        a { text-decoration: none; }
        img { max-width: 100%; height: auto; }
        
        /* ===== NAVBAR ===== */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            padding: 14px 0;
            transition: all 0.3s ease;
            background: transparent;
        }
        .navbar.scrolled {
            background: var(--navbar-bg);
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            padding: 8px 0;
        }
        [data-theme="dark"] .navbar.scrolled {
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .navbar .logo {
            height: 44px;
            width: auto;
            border-radius: 10px;
            background: white;
            padding: 4px;
            object-fit: contain;
        }
        .nav-link {
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            padding: 8px 16px;
            border-radius: 8px;
        }
        .nav-link:hover {
            color: var(--primary);
            background: rgba(11, 94, 215, 0.05);
        }
        .nav-link.active {
            color: var(--primary);
            background: rgba(11, 94, 215, 0.08);
        }
        .nav-link.white-link {
            color: rgba(255,255,255,0.9);
        }
        .nav-link.white-link:hover {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        .nav-link.white-link.active {
            color: white;
            background: rgba(255,255,255,0.15);
        }
        
        /* Language & Dark Mode Buttons */
        .nav-btn {
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.05);
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        .nav-btn:hover {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.4);
        }
        .nav-btn.active {
            background: var(--secondary);
            border-color: var(--secondary);
        }
        .nav-btn.scrolled-btn {
            border-color: var(--border-color);
            color: var(--text-primary);
            background: var(--input-bg);
        }
        .nav-btn.scrolled-btn:hover {
            background: var(--border-color);
        }
        .nav-btn.scrolled-btn.active {
            background: var(--secondary);
            color: white;
            border-color: var(--secondary);
        }
        .dark-toggle-btn {
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.05);
            color: white;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .dark-toggle-btn:hover {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.4);
        }
        .dark-toggle-btn.scrolled-btn {
            border-color: var(--border-color);
            color: var(--text-primary);
            background: var(--input-bg);
        }
        .dark-toggle-btn.scrolled-btn:hover {
            background: var(--border-color);
        }
        
        /* ===== HERO ===== */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            padding-top: 80px;
        }
        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--hero-overlay) 0%, rgba(10, 76, 168, 0.85) 100%);
            z-index: 1;
        }
        .hero-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('<?= $bg_path ?>') center/cover no-repeat;
            opacity: 0.25;
            z-index: 0;
        }
        .hero .hero-content {
            position: relative;
            z-index: 2;
            color: white;
        }
        .hero .hero-title {
            font-size: 3.8rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 20px;
        }
        .hero .hero-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 520px;
            line-height: 1.8;
            margin-bottom: 30px;
        }
        
        /* ===== BUTTONS ===== */
        .btn-primary {
            background: var(--secondary);
            color: white;
            padding: 14px 32px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary:hover {
            background: var(--secondary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(10, 168, 79, 0.35);
        }
        .btn-outline-light {
            background: transparent;
            color: white;
            padding: 14px 32px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            border: 2px solid rgba(255,255,255,0.3);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.1);
            border-color: white;
            transform: translateY(-2px);
        }
        
        /* ===== SEARCH BAR ===== */
        .search-wrapper {
            display: flex;
            align-items: center;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(10px);
            border-radius: 14px;
            padding: 4px;
            border: 1px solid rgba(255,255,255,0.15);
            max-width: 450px;
            width: 100%;
        }
        .search-wrapper input {
            flex: 1;
            border: none;
            background: transparent;
            padding: 10px 16px;
            font-size: 0.85rem;
            color: white;
            outline: none;
        }
        .search-wrapper input::placeholder {
            color: rgba(255,255,255,0.6);
        }
        .search-wrapper .search-btn {
            background: var(--secondary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            font-size: 0.8rem;
        }
        .search-wrapper .search-btn:hover {
            background: var(--secondary-dark);
        }
        
        /* ===== SECTIONS ===== */
        .section {
            padding: 80px 0;
        }
        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
        }
        .section-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
            max-width: 600px;
        }
        .section-badge {
            display: inline-block;
            background: rgba(11, 94, 215, 0.08);
            color: var(--primary);
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .section-divider {
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 4px;
            margin: 16px auto 0;
        }
        
        /* ===== SERVICE CARDS ===== */
        .service-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 30px 24px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            opacity: 0;
            transition: all 0.3s;
        }
        .service-card:hover::before {
            opacity: 1;
        }
        .service-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }
        .service-card .service-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 16px;
            transition: all 0.3s;
        }
        .service-card:hover .service-icon {
            transform: scale(1.05);
        }
        .service-card .icon-blue { background: rgba(11, 94, 215, 0.08); color: #0B5ED7; }
        .service-card .icon-green { background: rgba(10, 168, 79, 0.08); color: #0AA84F; }
        .service-card .icon-purple { background: rgba(139, 92, 246, 0.08); color: #8B5CF6; }
        .service-card .icon-orange { background: rgba(245, 158, 11, 0.08); color: #F59E0B; }
        .service-card .icon-red { background: rgba(239, 68, 68, 0.08); color: #EF4444; }
        .service-card .icon-teal { background: rgba(13, 148, 136, 0.08); color: #0D9488; }
        .service-card h4 { color: var(--text-primary); }
        .service-card p { color: var(--text-secondary); }
        
        /* ===== APPOINTMENT FORM ===== */
        .appointment-form {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 0.9rem;
            transition: all 0.2s;
            outline: none;
            background: var(--input-bg);
            color: var(--text-primary);
        }
        .form-input:focus {
            border-color: var(--primary);
            background: var(--bg-card);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.08);
        }
        .form-input::placeholder {
            color: var(--text-secondary);
            opacity: 0.6;
        }
        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        
        /* ===== CONTACT CARDS ===== */
        .contact-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .contact-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }
        .contact-card .cc-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin: 0 auto 12px;
        }
        .contact-card h4 { color: var(--text-primary); }
        .contact-card p { color: var(--text-secondary); }
        
        /* ===== CONTACT BACKGROUND ===== */
        .contact-bg-section {
            position: relative;
            overflow: hidden;
        }
        .contact-bg-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('<?= $bg_path ?>') center/cover no-repeat;
            opacity: 0.06;
            z-index: 0;
        }
        .contact-bg-section .container {
            position: relative;
            z-index: 1;
        }
        
        /* ===== FOOTER ===== */
        .footer {
            background: #0F172A;
            color: white;
            padding: 60px 0 20px;
        }
        .footer a {
            color: rgba(255,255,255,0.7);
            transition: all 0.3s;
        }
        .footer a:hover {
            color: white;
        }
        .footer .footer-heading {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .footer .footer-link {
            display: block;
            padding: 4px 0;
            font-size: 0.9rem;
        }
        .footer .footer-link:hover {
            transform: translateX(4px);
        }
        .staff-login-btn {
            background: var(--secondary);
            color: white;
            padding: 10px 28px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .staff-login-btn:hover {
            background: var(--secondary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(10, 168, 79, 0.3);
        }
        .footer .footer-logo {
            height: 44px;
            width: auto;
            border-radius: 10px;
            background: white;
            padding: 4px;
            object-fit: contain;
        }
        
        /* ===== WHATSAPP ===== */
        .whatsapp-btn {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 999;
            background: #25D366;
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            box-shadow: 0 4px 20px rgba(37, 211, 102, 0.4);
            transition: all 0.3s;
        }
        .whatsapp-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 30px rgba(37, 211, 102, 0.5);
        }
        
        /* ===== STATS ===== */
        .stat-item {
            text-align: center;
            padding: 12px 24px;
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        .stat-item:last-child { border-right: none; }
        .stat-item .stat-number { font-size: 2rem; font-weight: 700; }
        .stat-item .stat-label { font-size: 0.85rem; opacity: 0.75; }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .hero .hero-title { font-size: 3rem; }
        }
        @media (max-width: 768px) {
            .hero { min-height: auto; padding: 120px 0 60px; }
            .hero .hero-title { font-size: 2.2rem; }
            .hero .hero-subtitle { font-size: 1rem; }
            .section { padding: 50px 0; }
            .section-title { font-size: 1.8rem; }
            .stat-item { border-right: none; border-bottom: 1px solid rgba(255,255,255,0.05); padding: 8px 0; }
            .appointment-form { padding: 24px; }
            .search-wrapper { max-width: 100%; }
            .navbar .logo { height: 36px; }
        }
        @media (max-width: 480px) {
            .hero .hero-title { font-size: 1.6rem; }
            .btn-primary, .btn-outline-light { padding: 10px 20px; font-size: 0.85rem; }
            .whatsapp-btn { width: 50px; height: 50px; font-size: 1.6rem; bottom: 16px; right: 16px; }
            .nav-btn { padding: 4px 8px; font-size: 0.6rem; }
            .dark-toggle-btn { padding: 4px 8px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- NAVBAR -->
<!-- ================================================================ -->
<nav class="navbar" id="navbar">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
            <!-- Logo -->
            <a href="index.php" class="flex items-center gap-3">
                <img src="<?= $logo_path ?>" alt="Braick Dispensary" class="logo"
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2244%22 height=%2244%22%3E%3Crect width=%2244%22 height=%2244%22 fill=%22%230B5ED7%22 rx=%2210%22/%3E%3Ctext x=%2222%22 y=%2230%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
                <span class="text-xl font-bold text-white hidden sm:block" id="brandText"><?= __('brand') ?></span>
            </a>
            
            <!-- Desktop Nav -->
            <div class="hidden lg:flex items-center gap-1">
                <a href="index.php" class="nav-link white-link active"><?= __('home') ?></a>
                <a href="#services" class="nav-link white-link"><?= __('services') ?></a>
                <a href="#appointment" class="nav-link white-link"><?= __('appointment') ?></a>
                <a href="#contact" class="nav-link white-link"><?= __('contact') ?></a>
                
                <!-- Language Switcher -->
                <div class="flex items-center gap-1 ml-2">
                    <a href="?lang=en" class="nav-btn <?= $lang == 'en' ? 'active' : '' ?>">EN</a>
                    <a href="?lang=sw" class="nav-btn <?= $lang == 'sw' ? 'active' : '' ?>">SW</a>
                </div>
                
                <!-- Dark Mode Toggle -->
                <button id="darkModeToggle" class="dark-toggle-btn">
                    <i class="fas fa-moon" id="darkIcon"></i>
                </button>
            </div>
            
            <!-- Mobile Toggle -->
            <div class="flex items-center gap-2 lg:hidden">
                <div class="flex items-center gap-1">
                    <a href="?lang=en" class="nav-btn <?= $lang == 'en' ? 'active' : '' ?>" style="padding: 4px 8px; font-size: 0.65rem;">EN</a>
                    <a href="?lang=sw" class="nav-btn <?= $lang == 'sw' ? 'active' : '' ?>" style="padding: 4px 8px; font-size: 0.65rem;">SW</a>
                </div>
                <button id="darkModeToggleMobile" class="dark-toggle-btn" style="padding: 4px 8px; font-size: 0.8rem;">
                    <i class="fas fa-moon"></i>
                </button>
                <button id="mobileToggle" class="text-white text-2xl">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
        
        <!-- Mobile Nav -->
        <div id="mobileNav" class="hidden lg:hidden mt-4 pb-4 border-t border-white/10 pt-4">
            <a href="index.php" class="nav-link white-link block py-2 active"><?= __('home') ?></a>
            <a href="#services" class="nav-link white-link block py-2"><?= __('services') ?></a>
            <a href="#appointment" class="nav-link white-link block py-2"><?= __('appointment') ?></a>
            <a href="#contact" class="nav-link white-link block py-2"><?= __('contact') ?></a>
        </div>
    </div>
</nav>

<!-- ================================================================ -->
<!-- HERO SECTION -->
<!-- ================================================================ -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div class="hero-content">
                <h1 class="hero-title"><?= __('hero_title') ?></h1>
                <p class="hero-subtitle"><?= __('hero_sub') ?></p>
                
                <!-- Search Bar -->
                <div class="search-wrapper mb-6">
                    <input type="text" placeholder="<?= __('search_placeholder') ?>" id="searchInput">
                    <button class="search-btn" onclick="performSearch()">
                        <i class="fas fa-search mr-2"></i> <?= __('find_services') ?>
                    </button>
                </div>
                
                <div class="flex flex-wrap gap-4">
                    <a href="#appointment" class="btn-primary">
                        <i class="fas fa-calendar-check"></i> <?= __('book_appointment') ?>
                    </a>
                    <a href="#contact" class="btn-outline-light">
                        <i class="fas fa-phone"></i> <?= __('contact_us') ?>
                    </a>
                </div>
            </div>
            
            <div class="relative">
                <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-8 border border-white/20">
                    <div class="text-center text-white">
                        <img src="<?= $logo_path ?>" alt="Braick Dispensary" class="w-24 h-24 rounded-2xl mx-auto mb-4 bg-white p-2 object-contain"
                             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2296%22 height=%2296%22%3E%3Crect width=%2296%22 height=%2296%22 fill=%22%230B5ED7%22 rx=%2216%22/%3E%3Ctext x=%2248%22 y=%2258%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2230%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
                        <h3 class="text-2xl font-bold"><?= __('brand') ?></h3>
                        <p class="opacity-80 mt-2">Quality Healthcare Services</p>
                        <div class="flex flex-wrap justify-center gap-2 mt-4">
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm">✅ Professional</span>
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm">✅ Trusted</span>
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm">✅ Modern</span>
                        </div>
                    </div>
                </div>
                
                <!-- Stats -->
                <div class="flex flex-wrap justify-center mt-6 text-white">
                    <div class="stat-item">
                        <div class="stat-number">15+</div>
                        <div class="stat-label"><?= __('years_experience') ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">1000+</div>
                        <div class="stat-label"><?= __('happy_patients') ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label"><?= __('medical_support') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ================================================================ -->
<!-- SERVICES SECTION -->
<!-- ================================================================ -->
<section id="services" class="section" style="background: var(--bg-body);">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <span class="section-badge"><?= __('our_services') ?></span>
            <h2 class="section-title"><?= __('our_services') ?></h2>
            <div class="section-divider"></div>
            <p class="section-subtitle mx-auto mt-4"><?= __('services_sub') ?></p>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="service-card">
                <div class="service-icon icon-blue"><i class="fas fa-user-md"></i></div>
                <h4 class="font-bold text-lg mb-2"><?= __('general_consultation') ?></h4>
                <p class="text-sm"><?= __('general_consultation_desc') ?></p>
            </div>
            <div class="service-card">
                <div class="service-icon icon-green"><i class="fas fa-flask"></i></div>
                <h4 class="font-bold text-lg mb-2"><?= __('laboratory_services') ?></h4>
                <p class="text-sm"><?= __('laboratory_services_desc') ?></p>
            </div>
            <div class="service-card">
                <div class="service-icon icon-purple"><i class="fas fa-pills"></i></div>
                <h4 class="font-bold text-lg mb-2"><?= __('pharmacy_services') ?></h4>
                <p class="text-sm"><?= __('pharmacy_services_desc') ?></p>
            </div>
            <div class="service-card">
                <div class="service-icon icon-orange"><i class="fas fa-heartbeat"></i></div>
                <h4 class="font-bold text-lg mb-2"><?= __('health_checkups') ?></h4>
                <p class="text-sm"><?= __('health_checkups_desc') ?></p>
            </div>
            <div class="service-card">
                <div class="service-icon icon-red"><i class="fas fa-users"></i></div>
                <h4 class="font-bold text-lg mb-2"><?= __('family_healthcare') ?></h4>
                <p class="text-sm"><?= __('family_healthcare_desc') ?></p>
            </div>
            <div class="service-card">
                <div class="service-icon icon-teal"><i class="fas fa-ambulance"></i></div>
                <h4 class="font-bold text-lg mb-2"><?= __('emergency_care') ?></h4>
                <p class="text-sm"><?= __('emergency_care_desc') ?></p>
            </div>
        </div>
    </div>
</section>

<!-- ================================================================ -->
<!-- APPOINTMENT SECTION -->
<!-- ================================================================ -->
<section id="appointment" class="section" style="background: var(--bg-body);">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <span class="section-badge"><?= __('book_now') ?></span>
            <h2 class="section-title"><?= __('appointment_title') ?></h2>
            <div class="section-divider"></div>
            <p class="section-subtitle mx-auto mt-4"><?= __('appointment_sub') ?></p>
        </div>
        
        <div class="max-w-3xl mx-auto appointment-form">
            <form action="#" method="POST" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label"><?= __('full_name') ?> *</label>
                        <input type="text" name="name" required class="form-input" placeholder="John Doe">
                    </div>
                    <div>
                        <label class="form-label"><?= __('phone_number') ?> *</label>
                        <input type="tel" name="phone" required class="form-input" placeholder="+255 712 345 678">
                    </div>
                </div>
                <div>
                    <label class="form-label"><?= __('email_address') ?></label>
                    <input type="email" name="email" class="form-input" placeholder="john@example.com">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label"><?= __('preferred_date') ?></label>
                        <input type="date" name="date" class="form-input">
                    </div>
                    <div>
                        <label class="form-label"><?= __('service_required') ?></label>
                        <select name="service" class="form-input">
                            <option value=""><?= __('select_service') ?></option>
                            <option value="consultation"><?= __('consultation') ?></option>
                            <option value="laboratory"><?= __('laboratory') ?></option>
                            <option value="pharmacy"><?= __('pharmacy') ?></option>
                            <option value="checkup"><?= __('health_checkups') ?></option>
                            <option value="emergency"><?= __('emergency_care') ?></option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="form-label"><?= __('message') ?></label>
                    <textarea name="message" rows="3" class="form-input" placeholder="Describe your health concern..."></textarea>
                </div>
                <button type="submit" class="btn-primary w-full justify-center text-center">
                    <i class="fas fa-calendar-check"></i> <?= __('request_appointment') ?>
                </button>
            </form>
        </div>
    </div>
</section>

<!-- ================================================================ -->
<!-- CONTACT SECTION -->
<!-- ================================================================ -->
<section id="contact" class="section contact-bg-section" style="background: var(--bg-body);">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <span class="section-badge"><?= __('get_in_touch') ?></span>
            <h2 class="section-title"><?= __('get_in_touch') ?></h2>
            <div class="section-divider"></div>
            <p class="section-subtitle mx-auto mt-4"><?= __('contact_sub') ?></p>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="contact-card">
                <div class="cc-icon" style="background: rgba(11, 94, 215, 0.08); color: #0B5ED7;">
                    <i class="fas fa-phone"></i>
                </div>
                <h4 class="font-semibold"><?= __('phone') ?></h4>
                <p class="text-sm">+255 759 154 160</p>
                <p class="text-sm">+255 675 751 799</p>
            </div>
            <div class="contact-card">
                <div class="cc-icon" style="background: rgba(10, 168, 79, 0.08); color: #0AA84F;">
                    <i class="fas fa-envelope"></i>
                </div>
                <h4 class="font-semibold"><?= __('email') ?></h4>
                <p class="text-sm">info@braickdispensary.com</p>
                <p class="text-sm">support@braickdispensary.com</p>
            </div>
            <div class="contact-card">
                <div class="cc-icon" style="background: rgba(139, 92, 246, 0.08); color: #8B5CF6;">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <h4 class="font-semibold"><?= __('location') ?></h4>
                <p class="text-sm">Chang'ombe, Dodoma</p>
                <p class="text-sm">City Centre, Mbeya</p>
            </div>
            <div class="contact-card">
                <div class="cc-icon" style="background: rgba(245, 158, 11, 0.08); color: #F59E0B;">
                    <i class="fas fa-clock"></i>
                </div>
                <h4 class="font-semibold"><?= __('working_hours') ?></h4>
                <p class="text-sm">Mon-Fri: 8AM - 6PM</p>
                <p class="text-sm">Sat: 8AM - 2PM</p>
                <p class="text-sm">Sun: Closed</p>
            </div>
        </div>
    </div>
</section>

<!-- ================================================================ -->
<!-- FOOTER -->
<!-- ================================================================ -->
<footer class="footer">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <!-- Column 1 -->
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <img src="<?= $logo_path ?>" alt="Braick Dispensary" class="footer-logo"
                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2244%22 height=%2244%22%3E%3Crect width=%2244%22 height=%2244%22 fill=%22%230B5ED7%22 rx=%2210%22/%3E%3Ctext x=%2222%22 y=%2230%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
                    <span class="text-xl font-bold"><?= __('brand') ?></span>
                </div>
                <p class="text-gray-400 text-sm leading-relaxed">
                    <?= __('hero_sub') ?>
                </p>
                <div class="flex gap-4 mt-4">
                    <a href="#" class="text-gray-400 hover:text-white text-xl"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="text-gray-400 hover:text-white text-xl"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-gray-400 hover:text-white text-xl"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="text-gray-400 hover:text-white text-xl"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
            
            <!-- Column 2 -->
            <div>
                <h4 class="footer-heading"><?= __('quick_links') ?></h4>
                <a href="index.php" class="footer-link"><i class="fas fa-chevron-right text-xs mr-2"></i> <?= __('home') ?></a>
                <a href="#services" class="footer-link"><i class="fas fa-chevron-right text-xs mr-2"></i> <?= __('services') ?></a>
                <a href="#appointment" class="footer-link"><i class="fas fa-chevron-right text-xs mr-2"></i> <?= __('appointment') ?></a>
                <a href="#contact" class="footer-link"><i class="fas fa-chevron-right text-xs mr-2"></i> <?= __('contact') ?></a>
            </div>
            
            <!-- Column 3 -->
            <div>
                <h4 class="footer-heading"><?= __('our_services_footer') ?></h4>
                <a href="#services" class="footer-link"><i class="fas fa-chevron-right text-xs mr-2"></i> <?= __('consultation') ?></a>
                <a href="#services" class="footer-link"><i class="fas fa-chevron-right text-xs mr-2"></i> <?= __('laboratory') ?></a>
                <a href="#services" class="footer-link"><i class="fas fa-chevron-right text-xs mr-2"></i> <?= __('pharmacy') ?></a>
                <a href="#services" class="footer-link"><i class="fas fa-chevron-right text-xs mr-2"></i> <?= __('emergency') ?></a>
            </div>
            
            <!-- Column 4: Staff Access -->
            <div>
                <h4 class="footer-heading"><?= __('staff_access') ?></h4>
                <p class="text-gray-400 text-sm mb-4"><?= __('access_staff') ?></p>
                <a href="staff_login.php" class="staff-login-btn">
                    <i class="fas fa-lock"></i> <?= __('login') ?>
                </a>
                <p class="text-xs text-gray-500 mt-4"><?= __('staff_only') ?></p>
            </div>
        </div>
        
        <hr class="border-gray-700 my-6">
        
        <div class="flex flex-col sm:flex-row justify-between items-center text-sm text-gray-500">
            <p>&copy; <?= date('Y') ?> <?= __('brand') ?>. <?= __('all_rights') ?></p>
            <p><?= __('designed_with') ?> <i class="fas fa-heart text-red-500"></i> <?= __('for_better') ?></p>
        </div>
    </div>
</footer>

<!-- ================================================================ -->
<!-- WHATSAPP BUTTON -->
<!-- ================================================================ -->
<a href="https://wa.me/255759154160" target="_blank" class="whatsapp-btn">
    <i class="fab fa-whatsapp"></i>
</a>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    const darkToggle = document.getElementById('darkModeToggle');
    const darkToggleMobile = document.getElementById('darkModeToggleMobile');
    const darkIcon = document.getElementById('darkIcon');
    const htmlRoot = document.getElementById('htmlRoot');
    
    if (localStorage.getItem('darkMode') === 'true') {
        htmlRoot.setAttribute('data-theme', 'dark');
        if (darkIcon) darkIcon.className = 'fas fa-sun';
    }
    
    function toggleDarkMode() {
        const isDark = htmlRoot.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlRoot.removeAttribute('data-theme');
            localStorage.setItem('darkMode', 'false');
            if (darkIcon) darkIcon.className = 'fas fa-moon';
        } else {
            htmlRoot.setAttribute('data-theme', 'dark');
            localStorage.setItem('darkMode', 'true');
            if (darkIcon) darkIcon.className = 'fas fa-sun';
        }
    }
    
    darkToggle?.addEventListener('click', toggleDarkMode);
    darkToggleMobile?.addEventListener('click', toggleDarkMode);

    // ================================================================
    // NAVBAR SCROLL
    // ================================================================
    const navbar = document.getElementById('navbar');
    const brandText = document.getElementById('brandText');
    const navBtns = document.querySelectorAll('.nav-btn');
    const darkToggleBtn = document.querySelector('.dark-toggle-btn');
    
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
            brandText?.classList.remove('text-white');
            brandText?.classList.add('text-gray-800');
            navBtns.forEach(btn => btn.classList.add('scrolled-btn'));
            darkToggleBtn?.classList.add('scrolled-btn');
        } else {
            navbar.classList.remove('scrolled');
            brandText?.classList.add('text-white');
            brandText?.classList.remove('text-gray-800');
            navBtns.forEach(btn => btn.classList.remove('scrolled-btn'));
            darkToggleBtn?.classList.remove('scrolled-btn');
        }
    });

    // ================================================================
    // MOBILE MENU
    // ================================================================
    const mobileToggle = document.getElementById('mobileToggle');
    const mobileNav = document.getElementById('mobileNav');
    
    mobileToggle?.addEventListener('click', () => {
        mobileNav.classList.toggle('hidden');
        mobileToggle.innerHTML = mobileNav.classList.contains('hidden') 
            ? '<i class="fas fa-bars"></i>' 
            : '<i class="fas fa-times"></i>';
    });

    // ================================================================
    // SEARCH FUNCTION
    // ================================================================
    function performSearch() {
        const query = document.getElementById('searchInput')?.value.trim();
        if (query && query.length > 0) {
            const services = document.querySelectorAll('.service-card');
            let found = false;
            services.forEach(card => {
                const text = card.textContent.toLowerCase();
                if (text.includes(query.toLowerCase())) {
                    card.style.borderColor = '#0B5ED7';
                    card.style.boxShadow = '0 0 0 3px rgba(11, 94, 215, 0.2)';
                    found = true;
                    setTimeout(() => {
                        card.style.borderColor = '';
                        card.style.boxShadow = '';
                    }, 3000);
                }
            });
            if (!found) {
                alert('No services found matching "' + query + '". Please try another search.');
            }
            document.getElementById('services')?.scrollIntoView({ behavior: 'smooth' });
        }
    }

    document.getElementById('searchInput')?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // SMOOTH SCROLL
    // ================================================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== '#' && href.startsWith('#')) {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    });
</script>
</body>
</html>