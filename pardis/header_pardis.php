<?php
// pardis/header_pardis.php

// --- Bootstrap and Session ---
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../../sercon/bootstrap.php';
    secureSession();
}
// --- Redirect if not logged in or project context not Fereshteh ---
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit();
}
$currentProjectConfigKeyInSession = $_SESSION['current_project_config_key'] ?? null;
if ($currentProjectConfigKeyInSession !== 'pardis') {
    logError("pardis header loaded with incorrect project context. Session: " . $currentProjectConfigKeyInSession);
    header('Location: /select_project.php?msg=project_mismatch_header');
    exit();
}

$pdo_common_header = null;
try {
    $pdo_common_header = getCommonDBConnection();
} catch (Exception $e) {
    logError("Critical: Common DB connection failed in Fereshteh header: " . $e->getMessage());
}

// --- Update Last Activity ---
if ($pdo_common_header && isset($_SESSION['user_id'])) {
    try {
        if ($_SESSION['user_id'] != 1) {
            $stmt_update_activity = $pdo_common_header->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
            $stmt_update_activity->execute([$_SESSION['user_id']]);
        }
    } catch (PDOException $e) {
        logError("Database error in Fereshteh header (updating last_activity): " . $e->getMessage());
    }
}

// --- Unread Messages Count ---
$totalUnreadCount = 0;
if ($pdo_common_header && isset($_SESSION['user_id'])) {
    $currentUserIdHeader = $_SESSION['user_id'];
    try {
        $stmt_total_unread = $pdo_common_header->prepare("
            SELECT COUNT(*) as total_unread_count
            FROM messages
            WHERE receiver_id = :user_id AND is_read = 0 AND is_deleted = 0
        ");
        $stmt_total_unread->execute([':user_id' => $currentUserIdHeader]);
        $result_unread = $stmt_total_unread->fetch(PDO::FETCH_ASSOC);
        if ($result_unread) {
            $totalUnreadCount = (int) $result_unread['total_unread_count'];
        }
    } catch (PDOException $e) {
        logError("Error fetching total unread count in Fereshteh header: " . $e->getMessage());
    }
}

$pageTitle = isset($pageTitle) ? $pageTitle : 'پروژه دانشگاه خاتم';
$current_page_filename = basename($_SERVER['PHP_SELF']);

// --- Role Text ---
$role = $_SESSION['role'] ?? '';
$roleText = '';
switch ($role) {
    case 'admin': $roleText = 'مشاور'; break;
    case 'supervisor': $roleText = 'سرپرست'; break;
    case 'planner': $roleText = 'طراح'; break;
    case 'cnc_operator': $roleText = 'اپراتور CNC'; break;
    case 'superuser': $roleText = 'سوپر یوزر'; break;
    case 'receiver': $roleText = 'نصاب'; break;
    case 'user': $roleText = 'کارفرما'; break;
    case 'guest': $roleText = 'مهمان'; break;
    case 'cat': $roleText = ' آتیه نما'; break;
    case 'car': $roleText = ' آرانسج'; break;
    case 'cod': $roleText = 'شرکت طرح و نقش آدرم'; break;
    case 'cod': $roleText = ' شرکت ساختمانی رس'; break;
}

// --- Online Users ---
$onlineUsers = [];
if ($pdo_common_header && isset($_SESSION['user_id'])) {
    $onlineTimeoutMinutes = 5;
    try {
        $stmt_online = $pdo_common_header->prepare("
            SELECT id, first_name, last_name
            FROM users
            WHERE last_activity >= NOW() - INTERVAL :timeout MINUTE
              AND id != :current_user_id
            ORDER BY first_name, last_name
        ");
        $stmt_online->bindParam(':timeout', $onlineTimeoutMinutes, PDO::PARAM_INT);
        $stmt_online->bindParam(':current_user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt_online->execute();
        $onlineUsers = $stmt_online->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Database error in Fereshteh header (fetching online users): " . $e->getMessage());
    }
}

// --- User Avatar Path ---
$avatarPath = '/assets/images/default-avatar.jpg';
if ($pdo_common_header && isset($_SESSION['user_id'])) {
    try {
        $stmt_avatar = $pdo_common_header->prepare("SELECT avatar_path FROM users WHERE id = ?");
        $stmt_avatar->execute([$_SESSION['user_id']]);
        $user_avatar_data = $stmt_avatar->fetch(PDO::FETCH_ASSOC);
        $potentialAvatarWebPath = $user_avatar_data['avatar_path'] ?? null;
        if ($potentialAvatarWebPath && fileExistsAndReadable(PUBLIC_HTML_ROOT . $potentialAvatarWebPath)) {
            $avatarPath = '/' . ltrim($potentialAvatarWebPath, '/');
        }
    } catch (PDOException $e) {
        logError("Database error in pardis header (fetching avatar path): " . $e->getMessage());
    }
}

// --- Fetch ALL projects the user has access to ---
$all_user_accessible_projects = [];
if ($pdo_common_header && isset($_SESSION['user_id'])) {
    try {
        $stmt_all_projects = $pdo_common_header->prepare(
            "SELECT p.project_id, p.project_name, p.project_code, p.base_path, p.config_key, p.ro_config_key
    FROM projects p
    JOIN user_projects up ON p.project_id = up.project_id
    WHERE up.user_id = ? AND p.is_active = TRUE
    ORDER BY p.project_name"
        );
        $stmt_all_projects->execute([$_SESSION['user_id']]);
        $all_user_accessible_projects = $stmt_all_projects->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error fetching all accessible projects in pardis header: " . $e->getMessage());
    }
}

// Helper functions
if (!function_exists('hasAccess')) {
    function hasAccess($requiredRoles) {
        $current_user_role = $_SESSION['role'] ?? '';
        return in_array($current_user_role, (array)$requiredRoles);
    }
}

if (!function_exists('getActiveClass')) {
    function getActiveClass($link_filename, $current_filename) {
        return ($link_filename == $current_filename) ? ' active' : '';
    }
}

if (!function_exists('isHomeDropdownActive')) {
    function isHomeDropdownActive($current_filename) {
        $home_pages = ['index.php', 'contractor_batch_update.php', 'project_schedule.php', 'reports.php', 'record_management.php', 'weight_management.php'];
        return in_array($current_filename, $home_pages);
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>دانشگاه خاتم پردیس - <?php echo htmlspecialchars($pageTitle); ?></title>
    
    <!-- Favicons -->
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/assets/images/favicon-96x96.png">
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/apple-touch-icon.png">
    <link rel="manifest" href="/assets/images/site.webmanifest">
    
    <!-- Stylesheets -->
    <link href="/assets/css/tailwind.min.css" rel="stylesheet">
    <link href="/assets/css/font-face.css" rel="stylesheet">
    <link href="/assets/css/main.min.css" rel="stylesheet">
    <link href="/assets/css/persian-datepicker.min.css" rel="stylesheet">
    <link href="/assets/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="/assets/css/sweetalert2.min.css" rel="stylesheet">
    <link href="/assets/css/datatables.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/pardis/assets/css/jalalidatepicker.min.css">

<style>
        @font-face {
            font-family: 'Vazir';
            src: url('/assets/fonts/Vazir-Regular.woff2') format('woff2');
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Vazir', sans-serif;
            margin: 0;
            padding: 0;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
        }

        /* Modern Header Styles */
        .modern-header {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            position: sticky;
            top: 0;
            z-index: 1000;
            height: 64px; /* Fixed low height */
        }

        .header-container {
            max-width: 100%;
            margin: 0 auto;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1rem;
            gap: 1rem;
        }

        /* Logo Section */
        .logo-section {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        .logo-img {
            height: 32px;
            width: auto;
        }

        .site-title {
            color: white;
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .site-icon {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        /* Navigation */
        .main-nav {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            flex: 1;
            justify-content: center;
            margin: 0 2rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            position: relative;
            white-space: nowrap;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateY(-1px);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            font-weight: 600;
        }

        .nav-icon {
            width: 16px;
            height: 16px;
            fill: currentColor;
            flex-shrink: 0;
        }

        /* Dropdown Styles */
        .nav-dropdown {
            position: relative;
        }

        .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            position: relative;
            white-space: nowrap;
            cursor: pointer;
            background: none;
            border: none;
        }

        .dropdown-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateY(-1px);
        }

        .dropdown-toggle.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            font-weight: 600;
        }

        .dropdown-arrow {
            width: 12px;
            height: 12px;
            fill: currentColor;
            transition: transform 0.2s ease;
        }

        .nav-dropdown.show .dropdown-arrow {
            transform: rotate(180deg);
        }

        .nav-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            min-width: 200px;
            z-index: 50;
            display: none;
            margin-top: 0.25rem;
            opacity: 0;
            transform: translateY(-10px);
            transition: opacity 0.2s ease, transform 0.2s ease;
        }

        .nav-dropdown-menu.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .nav-dropdown-item {
            display: block;
            width: 100%;
            padding: 0.75rem 1rem;
            color: #374151;
            text-decoration: none;
            border: none;
            background: none;
            text-align: right;
            font-size: 0.875rem;
            transition: background 0.2s ease;
            border-bottom: 1px solid #f3f4f6;
        }

        .nav-dropdown-item:last-child {
            border-bottom: none;
        }

        .nav-dropdown-item:hover {
            background: #f3f4f6;
            color: #1d4ed8;
        }

        .nav-dropdown-item.active {
            background: #dbeafe;
            color: #1d4ed8;
            font-weight: 600;
        }

        /* User Section */
        .user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-shrink: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
            font-size: 0.875rem;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .user-details {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            line-height: 1.2;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.875rem;
        }

        .user-role {
            font-size: 0.75rem;
            opacity: 0.8;
        }

        /* Messages with Badge */
        .messages-link {
            position: relative;
        }

        .unread-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ef4444;
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.125rem 0.375rem;
            border-radius: 9999px;
            min-width: 1.25rem;
            text-align: center;
            line-height: 1;
        }

        /* Logout Button */
        .logout-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            text-decoration: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .logout-btn:hover {
            background: #dc2626;
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
        }

        /* Mobile Menu */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.375rem;
            transition: background 0.2s ease;
        }

        .mobile-menu-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .mobile-menu-icon {
            width: 24px;
            height: 24px;
            fill: currentColor;
        }

        /* Project Switcher */
        .project-switcher {
            position: relative;
        }

        .project-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            min-width: 200px;
            z-index: 50;
            display: none;
        }

        .project-dropdown.show {
            display: block;
        }

        .project-dropdown-item {
            display: block;
            width: 100%;
            padding: 0.75rem 1rem;
            color: #374151;
            text-decoration: none;
            border: none;
            background: none;
            text-align: right;
            font-size: 0.875rem;
            transition: background 0.2s ease;
        }

        .project-dropdown-item:hover {
            background: #f3f4f6;
        }

        .project-dropdown-item.current {
            background: #dbeafe;
            color: #1d4ed8;
            font-weight: 600;
        }

        /* Content Area */
        .main-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .content-wrapper {
            flex: 1;
            padding: 1.5rem;
        }

        .content-container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-nav {
                margin: 0 1rem;
            }
            
            .nav-link {
                font-size: 0.8125rem;
                padding: 0.375rem 0.625rem;
            }

            .dropdown-toggle {
                font-size: 0.8125rem;
                padding: 0.375rem 0.625rem;
            }
        }

/* Replace the existing mobile media query section with this fixed version */

@media (max-width: 768px) {
    .mobile-menu-btn {
        display: block;
         height: 56px;
    }
    .header-container {
        padding: 0 0.5rem; /* Reduced padding */
    }


    .main-nav {
        display: none;
        position: absolute;
         top: 56px;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        flex-direction: column;
        padding: 1rem;
        gap: 0.5rem; /* Add gap between items */
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        max-height: calc(100vh - 56px);
        overflow-y: auto;
        z-index: 1000; /* Ensure it's above other content */
    }

    .main-nav.show {
        display: flex;
    }

    .nav-dropdown {
        width: 100%;
    }

    /* Fix dropdown menu positioning for mobile */
    .nav-dropdown-menu {
        position: static !important; /* Changed from absolute */
        background: rgba(255, 255, 255, 0.1) !important; /* Mobile background */
        border-radius: 0.5rem;
        box-shadow: none;
        min-width: auto;
        z-index: 50;
        display: none;
        margin-top: 0.5rem;
        opacity: 0;
        transform: none; /* Remove transform for mobile */
        transition: opacity 0.2s ease;
        pointer-events: auto; /* Enable pointer events */
    }

    .nav-dropdown-menu.show {
        display: block;
        opacity: 1;
        pointer-events: auto;
    }

    .nav-dropdown-item {
        color: rgba(255, 255, 255, 0.9) !important; /* White text for mobile */
        background: none !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding: 0.75rem 1rem;
        display: block;
        width: 100%;
        text-align: right;
        border: none;
        font-size: 0.875rem;
        transition: background 0.2s ease;
    }

    .nav-dropdown-item:last-child {
        border-bottom: none;
    }

    .nav-dropdown-item:hover {
        background: rgba(255, 255, 255, 0.1) !important;
        color: white !important;
    }

    .nav-dropdown-item.active {
        background: rgba(255, 255, 255, 0.15) !important;
        color: white !important;
        font-weight: 600;
    }

    .nav-link {
        width: 100%;
        justify-content: flex-end;
        padding: 0.75rem 1rem;
        border-radius: 0.375rem;
        margin-bottom: 0.25rem;
    }

    .dropdown-toggle {
        width: 100%;
        justify-content: space-between; /* Changed from flex-end */
        text-align: right;
        padding: 0.75rem 1rem;
        border-radius: 0.375rem;
        margin-bottom: 0.25rem;
    }

    /* Ensure dropdown arrow is visible */
    .dropdown-arrow {
        order: -1; /* Move arrow to the left side */
        margin-left: 0.5rem;
    }
.logo-img {
        height: 28px; /* Reduced from 32px */
    }
    .user-details {
        display: none;
    }


     .site-title {
        font-size: 0.875rem; /* Reduced from 1rem */
    }

    .content-wrapper {
        padding: 1rem;
    }

    .content-container {
        padding: 1.5rem;
    }
    .site-icon {
        width: 16px; /* Reduced from 20px */
        height: 16px;
    }
     .user-avatar {
        width: 28px; /* Reduced from 32px */
        height: 28px;
    }
    .logout-btn {
        padding: 0.375rem 0.75rem; /* Reduced padding */
        font-size: 0.8125rem;
    }
      .user-section {
        gap: 0.375rem; /* Reduced gap */
    }
}

        @media (max-width: 480px) {
            .site-title span {
                display: none;
            }
   .modern-header {
        height: 52px; /* Even smaller for very small screens */
    }
         
     .logo-img {
        height: 24px;
    }
     .main-nav {
        top: 52px;
        max-height: calc(100vh - 52px);
    }

.user-section {
                gap: 0.5rem;
            }

            .logout-btn {
                padding: 0.5rem;
                font-size: 0.8125rem;
            }

            .logout-btn span {
                display: none;
            }
             .logout-btn span {
        display: none;
    }
     .logout-btn {
        padding: 0.375rem;
    }
        }

        /* All your existing styles continue here... */
        /* Keep all the existing styles for forms, tables, modals, etc. */
        
        .container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            width: 100%;
            box-sizing: border-box;
        }

        h2 {
            color: #374151;
            text-align: center;
            margin-bottom: 2rem;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }
.header-weather-widget {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            padding: 0 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            height: 36px;
            font-size: 0.8rem;
            white-space: nowrap;
        }
        .header-weather-widget .weather-icon {
            font-size: 1.5em;
        }
        .header-weather-widget .weather-details {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .header-weather-widget .weather-temp {
            font-weight: 600;
        }
        .header-weather-widget .weather-condition {
            opacity: 0.9;
        }
        .header-weather-widget .weather-loading,
        .header-weather-widget .weather-error {
            font-size: 0.8rem;
        }
        .header-weather-widget .btn-refresh-weather {
             background: none;
             border: none;
             color: white;
             cursor: pointer;
             padding: 0;
             opacity: 0.7;
             transition: all 0.3s;
        }
        .header-weather-widget .btn-refresh-weather:hover {
            opacity: 1;
            transform: rotate(180deg);
        }
        .nav-text {
    white-space: nowrap;
}

.main-nav {
    gap: 0.125rem; /* Reduced gap between items */
}

.nav-link, .dropdown-toggle {
    padding: 0.5rem 0.625rem; /* Slightly reduced padding */
    font-size: 0.8125rem; /* Smaller font for better fit */
}

.nav-icon {
    width: 14px; /* Slightly smaller icons */
    height: 14px;
}

.dropdown-arrow {
    width: 10px;
    height: 10px;
}

/* Better responsive breakpoints */
@media (max-width: 1200px) {
    .nav-link, .dropdown-toggle {
        padding: 0.375rem 0.5rem;
        font-size: 0.75rem;
    }
    
    .nav-text {
        font-size: 0.75rem;
    }
    
    .main-nav {
        gap: 0.125rem;
        margin: 0 0.5rem;
    }
}

@media (max-width: 1024px) {
    /* Hide text on smaller desktops, keep icons */
    .nav-text {
        display: none;
    }
    
    .nav-link, .dropdown-toggle {
        padding: 0.5rem;
    }
    
    /* Show tooltip on hover */
    .nav-link:hover::after,
    .dropdown-toggle:hover::after {
        content: attr(title);
        position: absolute;
        bottom: -2rem;
        right: 50%;
        transform: translateX(50%);
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        white-space: nowrap;
        z-index: 1000;
    }
}
        @media (max-width: 768px) {
            .header-weather-widget {
                display: none; /* Hide widget on mobile to save space */
            }
        }
        /* Continue with all your existing component styles... */
        
        <?php
        if (isset($styles)) {
            echo $styles;
        }
        ?>
    </style>

    <!-- Scripts -->
    <script src="/assets/js/main.min.js"></script>
    <script src="/assets/js/jquery-3.5.1.slim.min.js"></script>
    <script src="/assets/js/popper.min.js"></script>
    <script src="/pardis/assets/js/jalalidatepicker.min.js"></script>
    <script src="/assets/js/bootstrap.min.js"></script>
    <script src="/assets/js/datatables.js"></script>
    <script src="/assets/js/datatables.min.js"></script>
    <script src="/assets/js/mobile-detect.min.js"></script>

</head>

<body>
    <div class="main-container">
        <!-- Modern Header -->
        <header class="modern-header">
            <div class="header-container">
                <!-- Logo Section -->
                <div class="logo-section">
                    <a href="/">
                        <img src="/assets/images/alumglass-farsi-logo-H40.png" alt="Logo" class="logo-img">
                    </a>
                    <h1 class="site-title">
                        <svg class="site-icon" viewBox="0 0 24 24">
                            <path d="M19.5 13h-1.8v2.5h-1.5V13h-1.8v2.5h-1.5V13h-1.8v2.5h-1.5V13H8.1v2.5H6.6V13H4.8v8.5h14.7V13zm-6 5h-1.5v-2h1.5v2zm7.5 2h-16V11.5h1.5v.8h1.5v-.8h1.8v.8h1.5v-.8h1.8v.8h1.5v-.8h1.8v.8h1.5v-.8H18v.8h1.5V20zM21 8.9c-.1-.3-.4-.5-.7-.5H3.8c-.3 0-.6.2-.7.5l-1.9 5.3V21h20v-6.8L21 8.9zm-1 10.6H4v-4.5l.9-2.6h14.2l.9 2.6v4.5z" />
                        </svg>
                        <span><?php echo htmlspecialchars($pageTitle); ?></span>
                    </h1>
                </div>

                <!-- Navigation -->
<nav class="main-nav" id="main-nav">
    <?php if (hasAccess(['admin', 'superuser','planner','supervisor', 'user', 'cat', 'car', 'coa', 'cod'])): ?>
        
        <!-- Home Dropdown - Main Pages -->
        <div class="nav-dropdown" id="home-dropdown">
            <button class="dropdown-toggle<?php echo isHomeDropdownActive($current_page_filename) ? ' active' : ''; ?>" 
                    onclick="toggleDropdown('home-dropdown')">
                <svg class="nav-icon" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                    <path d="M3 12L12 3l9 9" />
                    <path d="M9 21V12h6v9" />
                </svg>
                <span class="nav-text">خانه</span>
                <svg class="dropdown-arrow" viewBox="0 0 24 24">
                    <path d="M7 10l5 5 5-5z" />
                </svg>
            </button>
            <div class="nav-dropdown-menu">
                <a href="index.php" class="nav-dropdown-item<?php echo getActiveClass('index.php', $current_page_filename); ?>">
                    صفحه اصلی
                </a>
                <a href="contractor_batch_update.php" class="nav-dropdown-item<?php echo getActiveClass('contractor_batch_update.php', $current_page_filename); ?>">
                    مراحل پیش بازرسی
                </a>
                <a href="forms_list.php" class="nav-dropdown-item<?php echo getActiveClass('forms_list.php', $current_page_filename); ?>">
                    صفحه فرم‌ها
                </a>
                <a href="daily_reports_dashboard_ps.php" class="nav-dropdown-item<?php echo getActiveClass('daily_reports_dashboard_ps.php', $current_page_filename); ?>">
           گزارش رورانه
                </a>
                   <a href="weekly_report_ps.php" class="nav-dropdown-item<?php echo getActiveClass('weekly_report_ps.php', $current_page_filename); ?>">
           گزارش هفتگی
                </a>
                <?php if (hasAccess(['admin', 'superuser', 'user','supervisor', 'planner'])): ?>
                <a href="daily_reports.php" class="nav-dropdown-item<?php echo getActiveClass('daily_reports.php', $current_page_filename); ?>">
                    گزارشات روزانه مهندسی
                </a>
                <?php endif; ?>
                <a href="my_calendar.php" class="nav-dropdown-item<?php echo getActiveClass('my_calendar.php', $current_page_filename); ?>">
                    تقویم کاری
                </a>
                <a href="letters.php" class="nav-dropdown-item<?php echo getActiveClass('letters.php', $current_page_filename); ?>">
                    نامه‌ها و مکاتبات
                </a>
            </div>
        </div>

        <!-- Materials Dropdown -->
        <div class="nav-dropdown" id="materials-dropdown">
            <button class="dropdown-toggle" onclick="toggleDropdown('materials-dropdown')">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19.5 3.5L18 2l-1.5 1.5L15 2l-1.5 1.5L12 2l-1.5 1.5L9 2 7.5 3.5 6 2v14H3v3c0 1.66 1.34 3 3 3h12c1.66 0 3-1.34 3-3V2l-1.5 1.5zM19 19c0 .55-.45 1-1 1s-1-.45-1-1v-3H8V5h11v14z"/>
                    <path d="M9 7h6v2H9zm7 0h2v2h-2zm-7 3h6v2H9zm7 0h2v2h-2z"/>
                </svg>
                <span class="nav-text">مواد</span>
                <svg class="dropdown-arrow" viewBox="0 0 24 24">
                    <path d="M7 10l5 5 5-5z" />
                </svg>
            </button>
            <div class="nav-dropdown-menu">
                <a href="zirsazi_status.php" class="nav-dropdown-item<?php echo getActiveClass('zirsazi_status.php', $current_page_filename); ?>">
                    متریال زیرسازی
                </a>
                <a href="packing_list_viewer.php" class="nav-dropdown-item<?php echo getActiveClass('packing_list_viewer.php', $current_page_filename); ?>">
                    متریال پروفیل آلومینیومی
                </a>
                <?php if (hasAccess(['admin', 'superuser','planner','supervisor'])): ?>
                <a href="materials_log.php" class="nav-dropdown-item<?php echo getActiveClass('materials_log.php', $current_page_filename); ?>">
                    مدیریت ورود مصالح
                </a>
                <?php endif; ?>
                <a href="weight_management.php" class="nav-dropdown-item<?php echo getActiveClass('weight_management.php', $current_page_filename); ?>">
                    مدیریت وزن‌ها
                </a>
            </div>
        </div>

        <?php if (hasAccess(['admin', 'superuser'])): ?>
        <!-- Management Dropdown - Admin Only -->
        <div class="nav-dropdown" id="management-dropdown">
            <button class="dropdown-toggle" onclick="toggleDropdown('management-dropdown')">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/>
                </svg>
                <span class="nav-text">مدیریت</span>
                <svg class="dropdown-arrow" viewBox="0 0 24 24">
                    <path d="M7 10l5 5 5-5z" />
                </svg>
            </button>
            <div class="nav-dropdown-menu">
                <a href="checklist_manager.php" class="nav-dropdown-item<?php echo getActiveClass('checklist_manager.php', $current_page_filename); ?>">
                    مدیریت چک‌لیست‌ها
                </a>
                <a href="workflow_manager.php" class="nav-dropdown-item<?php echo getActiveClass('workflow_manager.php', $current_page_filename); ?>">
                    مدیریت گردش کار
                </a>
                <a href="test_weather.php" class="nav-dropdown-item<?php echo getActiveClass('test_weather.php', $current_page_filename); ?>">
                    آب و هوا
                </a>
                <a href="telegram_report_manual.php" target="_blank" class="nav-dropdown-item<?php echo getActiveClass('telegram_report_manual.php', $current_page_filename); ?>">
                    ارسال دستی گزارشات تلگرام
                </a>
                <a href="manage_reminders.php" target="_blank" class="nav-dropdown-item<?php echo getActiveClass('manage_reminders.php', $current_page_filename); ?>">
                    ارسال یادآوری
                </a>
                <?php if (hasAccess(['superuser'])): ?>
                <a href="/admin.php" class="nav-dropdown-item">
                    مدیریت مرکزی کاربران
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>

    <!-- Messages Link (Always Visible) -->
    <?php if (hasAccess(['admin', 'superuser', 'user', 'cat', 'car', 'coa', 'cod'])): ?>
    <a href="/messages.php" class="nav-link messages-link<?php echo getActiveClass('messages.php', $current_page_filename); ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
            <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8l8 5 8-5v10zm-8-7L4 6h16l-8 5z" />
        </svg>
        <span class="nav-text">پیام‌ها</span>
        <?php if ($totalUnreadCount > 0): ?>
            <span class="unread-badge"><?= $totalUnreadCount ?></span>
        <?php endif; ?>
    </a>
    <?php endif; ?>
</nav>

                <!-- Mobile Menu Button -->
                <button class="mobile-menu-btn" id="mobile-menu-btn">
                    <svg class="mobile-menu-icon" viewBox="0 0 24 24">
                        <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z" />
                    </svg>
                </button>

                <!-- User Section -->
                <div class="user-section">
                    <!-- Project Switcher (if multiple projects) -->
                    <?php if (count($all_user_accessible_projects) > 1): ?>
                        <div class="project-switcher">
                            <button class="nav-link" onclick="toggleProjectDropdown()">
                                <svg class="nav-icon" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                                    <path d="M7 8l4-4 4 4" />
                                    <path d="M7 16l4 4 4-4" />
                                </svg>
                                تعویض پروژه
                            </button>
                            <div class="project-dropdown" id="project-dropdown">
                                <?php foreach ($all_user_accessible_projects as $project): ?>
                                    <?php $is_current = (isset($_SESSION['current_project_id']) && $_SESSION['current_project_id'] == $project['project_id']); ?>
                                    <form action="/project_switch_handler.php" method="POST" style="margin: 0;">
                                        <input type="hidden" name="switch_to_project_id" value="<?= $project['project_id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                        <button type="submit" class="project-dropdown-item <?= $is_current ? 'current' : '' ?>" 
                                                <?= $is_current ? 'disabled' : '' ?>>
                                            <?= htmlspecialchars($project['project_name']) ?>
                                            <?= $is_current ? ' (فعلی)' : '' ?>
                                        </button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
<div id="header-weather-widget-container">
                      <!-- This div will be populated by JavaScript -->
                    </div>
                    <!-- User Info -->
                    <div class="user-info">
                        <a href="/../profile.php" style="display: flex; align-items: center; gap: 0.5rem; color: inherit; text-decoration: none;">
                            <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Profile" class="user-avatar">
                            <div class="user-details">
                                <div class="user-name">
                                    <?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?>
                                </div>
                                <div class="user-role"><?php echo $roleText; ?></div>
                            </div>
                        </a>
                    </div>

                    <!-- Logout Button -->
                    <a href="/logout.php" id="logout-link" class="logout-btn">
                        <svg class="nav-icon" viewBox="0 0 24 24">
                            <path d="M14.08,15.59L16.67,13H7V11H16.67L14.08,8.41L15.5,7L20.5,12L15.5,17L14.08,15.59M19,3A2,2 0 0,1 21,5V9.67L19,7.67V5H5V19H19V16.33L21,14.33V19A2,2 0 0,1 19,21H5C3.89,21 3,20.1 3,19V5C3,3.89 3.89,3 5,3H19Z" />
                        </svg>
                        <span>خروج</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <div class="content-container">
                <!-- Your page content will go here -->

    <script>
        // Dropdown toggle function
        function toggleDropdown(dropdownId) {
    event.preventDefault(); // Add this to prevent default behavior
    event.stopPropagation(); // Add this to prevent event bubbling
    
    const dropdown = document.getElementById(dropdownId);
    if (dropdown) {
        const menu = dropdown.querySelector('.nav-dropdown-menu');
        const isCurrentlyOpen = dropdown.classList.contains('show');
        
        // Close all other dropdowns first
        const allDropdowns = document.querySelectorAll('.nav-dropdown');
        allDropdowns.forEach(d => {
            d.classList.remove('show');
            const otherMenu = d.querySelector('.nav-dropdown-menu');
            if (otherMenu) {
                otherMenu.classList.remove('show');
            }
        });
        
        // Toggle current dropdown
        if (!isCurrentlyOpen) {
            dropdown.classList.add('show');
            if (menu) {
                menu.classList.add('show');
            }
        }
    }
}

document.addEventListener('click', function(event) {
    if (!event.target.closest('.nav-dropdown') && !event.target.closest('.project-switcher')) {
        const dropdowns = document.querySelectorAll('.nav-dropdown');
        const projectDropdowns = document.querySelectorAll('.project-dropdown');
        
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
            const menu = dropdown.querySelector('.nav-dropdown-menu');
            if (menu) {
                menu.classList.remove('show');
            }
        });
        
        projectDropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }
});

        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const mainNav = document.getElementById('main-nav');

            if (mobileMenuBtn && mainNav) {
                mobileMenuBtn.addEventListener('click', function() {
                    mainNav.classList.toggle('show');
                });

                // Close menu when clicking outside
                document.addEventListener('click', function(event) {
                    if (!event.target.closest('.header-container')) {
                        mainNav.classList.remove('show');
                    }
                });

                // Close menu on window resize
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 768) {
                        mainNav.classList.remove('show');
                    }
                });
            }
        });

        // Project switcher dropdown
        function toggleProjectDropdown() {
            const dropdown = document.getElementById('project-dropdown');
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }

        // Logout with localStorage cleanup
        document.addEventListener('DOMContentLoaded', function() {
            const logoutLink = document.getElementById('logout-link');
            if (logoutLink) {
                logoutLink.addEventListener('click', function() {
                    console.log('Logout clicked. Clearing private key from local storage...');
                    localStorage.removeItem('user_private_key');
                });
            }
        });

        // Notification badge update (if you have real-time notifications)
        function updateNotificationBadge() {
            // Add your notification logic here
            const badge = document.getElementById('notification-badge');
            if (badge) {
                // Update badge visibility and count
            }
        }

        // Auto-hide mobile menu on link click
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.main-nav .nav-link, .main-nav .nav-dropdown-item');
            const mainNav = document.getElementById('main-nav');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        mainNav.classList.remove('show');
                    }
                });
            });
        });

        // Handle dropdown behavior in mobile
        document.addEventListener('DOMContentLoaded', function() {
            const isMobile = window.innerWidth <= 768;
            
            if (isMobile) {
                // In mobile, show dropdown items always when menu is open
                const homeDropdown = document.getElementById('home-dropdown');
                const dropdownMenu = homeDropdown?.querySelector('.nav-dropdown-menu');
                
                if (dropdownMenu) {
                    dropdownMenu.classList.add('show');
                }
            }
            
            // Handle window resize
            window.addEventListener('resize', function() {
                const isMobileNow = window.innerWidth <= 768;
                const homeDropdown = document.getElementById('home-dropdown');
                const dropdownMenu = homeDropdown?.querySelector('.nav-dropdown-menu');
                
                if (dropdownMenu) {
                    if (isMobileNow) {
                        dropdownMenu.classList.add('show');
                    } else {
                        dropdownMenu.classList.remove('show');
                        homeDropdown.classList.remove('show');
                    }
                }
            });
        });

          document.addEventListener("DOMContentLoaded", () => {
            // Ensure the WeatherIntegration class is available before running
            if (typeof WeatherIntegration !== 'undefined') {
                
                // We define a specific config for our header widget
               const headerWidgetConfig = {
                    apiEndpoint: '/pardis/weather_api.php',
                    // A unique ID for the container so it doesn't conflict with anything else
                    weatherDisplayId: 'header-weather-widget-container',
                    // We have no form select to update in the header
                    weatherSelectId: null,
                    // We want it to load automatically
                    autoFetch: false, // Set to false to prevent auto-fetching from the original class
                    // Set the location permanently to 'pardis'
                    location: 'pardis'
                };


                // Create a new instance specifically for our header widget
                const headerWeatherWidget = new WeatherIntegration(headerWidgetConfig);

                // --- Override the default class methods for our compact header display ---

                // 1. Override the display function to create our custom compact HTML
                headerWeatherWidget.displayWeather = function(weather) {
                    const displayElement = document.getElementById(this.weatherDisplayId);
                    if (!displayElement) return;

                    displayElement.innerHTML = `
                        <div class="header-weather-widget">
                            <div class="weather-icon">${weather.icon}</div>
                            <div class="weather-details">
                                <span class="weather-temp">${weather.temperature}°C</span>
                                <span class="weather-condition">${weather.condition_fa}</span>
                            </div>
                            <button type="button" class="btn-refresh-weather" title="بروزرسانی آب و هوا">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/><path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/></svg>
                            </button>
                        </div>
                    `;
                    
                    // Re-attach the refresh handler to the new button
                    const refreshBtn = displayElement.querySelector(".btn-refresh-weather");
                    if (refreshBtn) {
                        refreshBtn.addEventListener("click", () => this.fetchWeather());
                    }
                };
                
                // 2. Override the fetch function to use our custom loading/error states
                headerWeatherWidget.fetchWeather = async function(location = null) {
                    const displayElement = document.getElementById(this.weatherDisplayId);
                    if (!displayElement) return;

                    const displayLoadingError = (message, isError = false) => {
                        const icon = isError 
                            ? '<i class="fas fa-exclamation-triangle"></i>' 
                            : '<i class="fas fa-spinner fa-spin"></i>';
                        displayElement.innerHTML = `
                             <div class="header-weather-widget">
                                <div class="weather-loading">${icon} ${message}</div>
                             </div>
                        `;
                    };

                    displayLoadingError('...'); // Show a minimal loading indicator
                    try {
                        const url = location ? `${this.apiEndpoint}?location=${location}` : `${this.apiEndpoint}?location=${this.location}`;
                        const response = await fetch(url);
                        const result = await response.json();
                        if (!result.success) {
                            throw new Error(result.message || "Error");
                        }
                        this.displayWeather(result.data);
                    } catch (error) {
                        console.error("Header weather fetch error:", error);
                        displayLoadingError('خطا', true);
                    }
                };

                // Manually trigger the first fetch for our header widget
                headerWeatherWidget.fetchWeather();
                
            } else {
                console.error("WeatherIntegration class not found. Ensure weather_integration.js is loaded before this script runs.");
            }
        });
    
    </script>
<script src="/pardis/assets/js/weather_integration.js"></script>
</body>
</html>