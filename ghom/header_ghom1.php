<?php
// ghom/header.php
// --- Bootstrap and Session ---
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../sercon/bootstrap.php';
    secureSession();
}

// --- Redirect if not logged in or project context not Fereshteh ---
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit();
}
$currentProjectConfigKeyInSession = $_SESSION['current_project_config_key'] ?? null;
if ($currentProjectConfigKeyInSession !== 'ghom') { // Hardcoded for this Fereshteh header
    logError("ghom header loaded with incorrect project context. Session: " . $currentProjectConfigKeyInSession);
    header('Location: /select_project.php?msg=project_mismatch_header');
    exit();
}



$pdo_common_header = null; // Initialize
try {
    $pdo_common_header = getCommonDBConnection();
} catch (Exception $e) {
    logError("Critical: Common DB connection failed in Fereshteh header: " . $e->getMessage());
}

// --- Update Last Activity (uses alumglas_hpc_common.users) ---
if ($pdo_common_header && isset($_SESSION['user_id'])) {
    try {
        // Exclude user with ID 1 (if this is a special admin/system account)
        if ($_SESSION['user_id'] != 1) {
            $stmt_update_activity = $pdo_common_header->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
            $stmt_update_activity->execute([$_SESSION['user_id']]);
        }
    } catch (PDOException $e) {
        logError("Database error in Fereshteh header (updating last_activity): " . $e->getMessage());
    }
}
// --- End Update Last Activity ---


// --- Unread Messages Count (uses alumglas_hpc_common.messages) ---
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



$pageTitle = isset($pageTitle) ? $pageTitle : 'پروژه بیمارستان هزار تخت خوابی قم'; // Default title for Fereshteh
$current_page_filename = basename($_SERVER['PHP_SELF']); // Get the current page filename

// --- Role Text (from session) ---
$role = $_SESSION['role'] ?? '';
$roleText = ''; // Initialize
// Your existing switch statement for $roleText...
switch ($role) {
    case 'admin':
        $roleText = 'مشاور';
        break;
    case 'supervisor':
        $roleText = 'سرپرست';
        break;
    case 'planner':
        $roleText = 'طراح';
        break;
    case 'cnc_operator':
        $roleText = 'اپراتور CNC';
        break;
    case 'superuser':
        $roleText = 'سوپر یوزر';
        break;
    case 'receiver':
        $roleText = 'نصاب';
        break;
    case 'user':
        $roleText = 'کارفرما';
        break;
    case 'guest':
        $roleText = 'مهمان';
        break;
    case 'cat':
        $roleText = 'پیمانکار آتیه نما';
        break;
    case 'car':
        $roleText = 'پیمانکار آرانسج';
        break;
    case 'coa':
        $roleText = 'پیمانکار عمران آذرستان';
        break;
    case 'crs':
        $roleText = 'پیمانکار شرکت ساختمانی رس';
        break;
}

// --- End Role Text ---


// --- Online Users (uses alumglas_hpc_common.users) ---
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
// --- End Fetch Online Users ---


// --- User Avatar Path (uses alumglas_hpc_common.users) ---
$avatarPath = '/assets/images/default-avatar.jpg'; // Default avatar

if ($pdo_common_header && isset($_SESSION['user_id'])) {
    try {
        $stmt_avatar = $pdo_common_header->prepare("SELECT avatar_path FROM users WHERE id = ?");
        $stmt_avatar->execute([$_SESSION['user_id']]);
        $user_avatar_data = $stmt_avatar->fetch(PDO::FETCH_ASSOC);

        $potentialAvatarWebPath = $user_avatar_data['avatar_path'] ?? null;

        if ($potentialAvatarWebPath && fileExistsAndReadable(PUBLIC_HTML_ROOT . $potentialAvatarWebPath)) {
            // Ensure it starts with a slash
            $avatarPath = '/' . ltrim($potentialAvatarWebPath, '/');
        }
    } catch (PDOException $e) {
        logError("Database error in ghom header (fetching avatar path): " . $e->getMessage());
    }
}
// --- End User Avatar Path ---
// --- NEW: Fetch ALL projects the user has access to ---
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
        logError("Error fetching all accessible projects in ghom header: " . $e->getMessage());
    }
}
// --- End Fetch ALL projects ---
// Helper function to check access (relies on $role from session)
// This could also be moved to bootstrap.php if used globally
if (!function_exists('hasAccess')) {
    function hasAccess($requiredRoles)
    {
        $current_user_role = $_SESSION['role'] ?? '';
        return in_array($current_user_role, (array)$requiredRoles);
    }
}

// Function to check if a link is active
if (!function_exists('getActiveClass')) {
    function getActiveClass($link_filename, $current_filename)
    {
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
    <title>qom hospital<?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/assets/images/favicon-96x96.png">
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico"> <!-- For older IE -->
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/apple-touch-icon.png"> <!-- For Apple devices -->
    <link rel="manifest" href="/assets/images/site.webmanifest"> <!-- PWA manifest (optional) -->
    <link href="/assets/css/tailwind.min.css" rel="stylesheet">
    <!-- <link href="/assets/css/all.min.css" rel="stylesheet"> Removed Font Awesome -->
    <link href="/assets/css/font-face.css" rel="stylesheet">
    <link href="/assets/css/font-face.css" rel="stylesheet" type="text/css" />

    <!--  admin_assigne_date.php -->
    <link href="/assets/css/main.min.css" rel="stylesheet" />
    <link type="module" href="/assets/css/main.min.css" rel="stylesheet" />
    <link href="/assets/css/persian-datepicker.min.css" rel="stylesheet">
    <link href="/assets/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="/assets/css/sweetalert2.min.css" rel="stylesheet">
    <!-- ... other head elements ... -->

    <link href="/assets/css/datatables.min.css" rel="stylesheet">
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
            max-width: 1280px;
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
            max-width: 1280px;
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

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }

            .main-nav {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
                flex-direction: column;
                padding: 1rem;
                gap: 0;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                max-height: calc(100vh - 64px);
                overflow-y: auto;
            }

            .main-nav.show {
                display: flex;
            }

            .nav-dropdown {
                width: 100%;
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
    pointer-events: none; /* Add this */
}
.nav-dropdown-menu.show {
    display: block;
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto; /* Add this */
}

            .nav-dropdown-item {
                color: rgba(255, 255, 255, 0.9);
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                padding: 0.75rem 1rem;
            }

            .nav-dropdown-item:hover {
                background: rgba(255, 255, 255, 0.1);
                color: white;
            }

            .nav-dropdown-item.active {
                background: rgba(255, 255, 255, 0.15);
                color: white;
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
                justify-content: flex-end;
                text-align: right;
            }

            .user-details {
                display: none;
            }

            .header-container {
                padding: 0 0.75rem;
            }

            .site-title {
                font-size: 1rem;
            }

            .content-wrapper {
                padding: 1rem;
            }

            .content-container {
                padding: 1.5rem;
            }

            .admin-link {
                margin-top: 0.5rem;
                border: 1px solid rgba(239, 68, 68, 0.3);
            }
        }

        @media (max-width: 480px) {
            .site-title span {
                display: none;
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
 <nav class="main-nav" id="main-nav">
                    <?php if (hasAccess(['admin', 'superuser', 'user', 'cat', 'car', 'coa', 'crs'])): ?>
                        <!-- Home Dropdown -->
                        <div class="nav-dropdown" id="home-dropdown">
                            <button class="dropdown-toggle<?php echo isHomeDropdownActive($current_page_filename) ? ' active' : ''; ?>" 
                                    onclick="toggleDropdown('home-dropdown')">
                                <svg class="nav-icon" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                                    <path d="M3 12L12 3l9 9" />
                                    <path d="M9 21V12h6v9" />
                                </svg>
                                خانه
                                <svg class="dropdown-arrow" viewBox="0 0 24 24">
                                    <path d="M7 10l5 5 5-5z" />
                                </svg>
                            </button>
                            <div class="nav-dropdown-menu">
                                <a href="index.php" class="nav-dropdown-item<?php echo getActiveClass('index.php', $current_page_filename); ?>">
                                    صفحه اصلی
                                </a>
                                <?php if (hasAccess(['admin', 'superuser', 'user', 'cat', 'car', 'coa', 'crs'])): ?>
                    <a href="contractor_batch_update.php" class="nav-dropdown-item<?php echo getActiveClass('contractor_batch_update.php', $current_page_filename); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="3 7 12 2 21 7 12 12 3 7" />
                            <polyline points="3 12 12 17 21 12" />
                            <polyline points="3 17 12 22 21 17" />
                        </svg>
                        مراحل پیش بازرسی
                    </a>
                <?php endif; ?>
                                
                               <?php if (hasAccess(['admin', 'superuser', 'user', 'cat', 'car', 'coa', 'crs'])): ?>
                    <a href="inspection_dashboard.php" class="nav-dropdown-item<?php echo getActiveClass('inspection_dashboard.php', $current_page_filename); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" rx="1" ry="1" />
                            <rect x="14" y="3" width="7" height="7" rx="1" ry="1" />
                            <rect x="3" y="14" width="7" height="7" rx="1" ry="1" />
                            <rect x="14" y="14" width="7" height="7" rx="1" ry="1" />
                        </svg>
                        بازرسی ها
                    </a>
                <?php endif; ?>
                                 <?php if (hasAccess(['admin', 'superuser', 'user', 'cat', 'car', 'coa', 'crs'])): ?>
                    <a href="reports.php" class="nav-dropdown-item<?php echo getActiveClass('reports.php', $current_page_filename); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" rx="1" ry="1" />
                            <rect x="14" y="3" width="7" height="7" rx="1" ry="1" />
                            <rect x="3" y="14" width="7" height="7" rx="1" ry="1" />
                            <rect x="14" y="14" width="7" height="7" rx="1" ry="1" />
                        </svg>
                        گزارشات
                    </a>
                <?php endif; ?>
                <?php if (hasAccess(['superuser'])): ?>
                    <a href="checklist_manager.php" class="nav-dropdown-item<?php echo getActiveClass('checklist_manager.php', $current_page_filename); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 6h11" />
                            <path d="M9 12h11" />
                            <path d="M9 18h11" />
                            <path d="M4 6l1.5 1.5L8 5" />
                            <path d="M4 12l1.5 1.5L8 11" />
                            <path d="M4 18l1.5 1.5L8 17" />
                        </svg>
                        مدیریت چک‌لیست‌ها
                    </a>
                <?php endif; ?>
                <?php if (hasAccess(['superuser'])): ?>
                    <a href="workflow_manager.php" class="nav-dropdown-item<?php echo getActiveClass('workflow_manager.php', $current_page_filename); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 6h11" />
                            <path d="M9 12h11" />
                            <path d="M9 18h11" />
                            <path d="M4 6l1.5 1.5L8 5" />
                            <path d="M4 12l1.5 1.5L8 11" />
                            <path d="M4 18l1.5 1.5L8 17" />
                        </svg>
                        مدیریت گردش کار
                    </a>
                <?php endif; ?>
                <?php if (hasAccess(['admin', 'superuser', 'user', 'cat', 'car', 'coa', 'crs'])): ?>
                    <a href="my_calendar.php" class="nav-dropdown-item<?php echo getActiveClass('my_calendar.php', $current_page_filename); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6.94028 2C7.35614 2 7.69326 2.32421 7.69326 2.72414V4.18487C8.36117 4.17241 9.10983 4.17241 9.95219 4.17241H13.9681C14.8104 4.17241 15.5591 4.17241 16.227 4.18487V2.72414C16.227 2.32421 16.5641 2 16.98 2C17.3958 2 17.733 2.32421 17.733 2.72414V4.24894C19.178 4.36022 20.1267 4.63333 20.8236 5.30359C21.5206 5.97385 21.8046 6.88616 21.9203 8.27586L22 9H2.92456H2V8.27586C2.11571 6.88616 2.3997 5.97385 3.09665 5.30359C3.79361 4.63333 4.74226 4.36022 6.1873 4.24894V2.72414C6.1873 2.32421 6.52442 2 6.94028 2Z" fill="#1C274C" />
                            <path opacity="0.5" d="M21.9995 14.0001V12.0001C21.9995 11.161 21.9963 9.66527 21.9834 9H2.00917C1.99626 9.66527 1.99953 11.161 1.99953 12.0001V14.0001C1.99953 17.7713 1.99953 19.6569 3.1711 20.8285C4.34267 22.0001 6.22829 22.0001 9.99953 22.0001H13.9995C17.7708 22.0001 19.6564 22.0001 20.828 20.8285C21.9995 19.6569 21.9995 17.7713 21.9995 14.0001Z" fill="#1C274C" />
                            <path d="M18 17C18 17.5523 17.5523 18 17 18C16.4477 18 16 17.5523 16 17C16 16.4477 16.4477 16 17 16C17.5523 16 18 16.4477 18 17Z" fill="#1C274C" />
                            <path d="M18 13C18 13.5523 17.5523 14 17 14C16.4477 14 16 13.5523 16 13C16 12.4477 16.4477 12 17 12C17.5523 12 18 12.4477 18 13Z" fill="#1C274C" />
                            <path d="M13 17C13 17.5523 12.5523 18 12 18C11.4477 18 11 17.5523 11 17C11 16.4477 11.4477 16 12 16C12.5523 16 13 16.4477 13 17Z" fill="#1C274C" />
                            <path d="M13 13C13 13.5523 12.5523 14 12 14C11.4477 14 11 13.5523 11 13C11 12.4477 11.4477 12 12 12C12.5523 12 13 12.4477 13 13Z" fill="#1C274C" />
                            <path d="M8 17C8 17.5523 7.55228 18 7 18C6.44772 18 6 17.5523 6 17C6 16.4477 6.44772 16 7 16C7.55228 16 8 16.4477 8 17Z" fill="#1C274C" />
                            <path d="M8 13C8 13.5523 7.55228 14 7 14C6.44772 14 6 13.5523 6 13C6 12.4477 6.44772 12 7 12C7.55228 12 8 12.4477 8 13Z" fill="#1C274C" />
                        </svg>
                        تقویم کاری
                        <span id="notification-badge" class="badge" style="display: none; background-color: #dc3545; color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px; margin-right: 5px;"></span>
                    </a>
                <?php endif; ?>
                <?php if (hasAccess(['admin', 'superuser', 'user', 'cat', 'car', 'coa', 'crs'])): ?>
                    <a href="instruction.php" target="_blank" rel="noopener noreferrer" class="nav-dropdown-item<?php echo getActiveClass('instruction.php', $current_page_filename); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                        راهنمای تعاملی
                    </a>
                <?php endif; ?>
                                        <?php if (hasAccess(['superuser'])): ?>
                            <a href="/admin.php" class="nav-dropdown-item">
                                <i class="fas fa-cogs me-2"></i> مدیریت مرکزی کاربران
                            </a>
                        <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (hasAccess(['admin', 'superuser', 'user', 'cat', 'car', 'coa', 'crs'])): ?>
                        <a href="/messages.php" class="nav-link messages-link<?php echo getActiveClass('messages.php', $current_page_filename); ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8l8 5 8-5v10zm-8-7L4 6h16l-8 5z" />
                            </svg>
                            پیام‌ها
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
    </script>

</body>
</html>