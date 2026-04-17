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

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <title>HPC Factory - <?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="/assets/css/design-system.css">
    <link rel="stylesheet" href="/assets/css/global.css">
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
<link rel="stylesheet" href="/ghom/assets/css/mobile.css">
<link rel="stylesheet" href="/ghom/assets/css/jalalidatepicker.min.css" />
    <link href="/assets/css/datatables.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/ghom/assets/css/crack1.css" />
    <script src="/ghom/assets/js/interact.min.js"></script>
    <script src="/ghom/assets/js/fabric.min.js"></script>


    <style>
        /* Base Styles */
        @font-face {
            font-family: "Samim";
            src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"),
                 url("/ghom/assets/fonts/Samim-FD.woff") format("woff"),
                 url("/ghom/assets/fonts/Samim-FD.ttf") format("truetype");
        }


        body {
            font-family: 'Samim', sans-serif;
            margin: 0;
            padding: 20px;
            background: rgb(78, 148, 217);
            text-align: right;
        }

        .main-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .top-header {
            background: linear-gradient(135deg, rgba(3, 61, 7, 0.5) 0%, rgb(123, 88, 6) 100%);
            color: white;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 1rem;
            flex-wrap: wrap;
            /* Allow wrapping on smaller screens */
        }

        .site-logo {
            height: 40px;
            /* Set the height to match your image */
            width: auto;
            /* Maintain aspect ratio */
            margin-right: 1rem;
            /* Add some spacing */
        }

        .profile-pic {
            width: 60px;
            /* Adjust the size as needed */
            height: 60px;
            /* Ensure it stays circular if desired */
            object-fit: cover;
            /* Ensures the image covers the area without distortion */
            border-radius: 50%;
            /* Makes the image round */
        }

        .site-title {
            font-size: 1.5rem;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }

        .user-infoheader {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0 auto;
            /* Center horizontally */
            text-align: center;
        }

        .user-infoheader i {
            font-size: 1.2rem;
        }

        /* Nav Styles */
        .nav-container {
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .navigation {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0.5rem 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: rgb(30, 138, 59);
            /* Dark blue text */
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            white-space: nowrap;
            border-right: 3px solid transparent;
            border-right-color: transparent;
            /* Reserve space for active indicator (RTL) */

        }

        .nav-item svg {
            /* Use margin-left for RTL to put space *after* icon */
            margin-left: 0.5rem;
            /* If your layout is LTR, use margin-right */
            /* margin-right: 0.5rem; */
            font-size: 1.1rem;
            /* This might not affect SVG size directly */
            width: 1.1em;
            /* Control SVG size relative to font */
            height: 1.1em;
            fill: currentColor;
            /* Make SVG color match text color */
            flex-shrink: 0;
            /* Prevent icon squishing */
        }

        .nav-item i {
            margin-left: 0.5rem;
            font-size: 1.1rem;
        }

        .nav-item:hover {
            background: #e5e7eb;
            /* Light grey background */
            /* Keep text color on hover or change if desired */
            color: rgb(21, 39, 17);
            /* Slightly darker text on hover */
            /* transform: translateY(-1px); */
            /* Optional: Can remove/keep hover transform */
        }

        .nav-item.active {
            background-color: #dbeafe;
            /* A light blue background (Tailwind blue-100) */
            color: rgb(224, 197, 25);
            /* Keep the dark blue text or make it darker */
            font-weight: 600;
            /* Make text slightly bolder (semibold) */
            border-right-color: rgb(27, 80, 26);
            /* Make the reserved border visible (RTL) */

        }

        .logout-btn {
            background: #dc2626;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            margin-right: auto;
        }

        .logout-btn:hover {
            background: #b91c1c;
        }


        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
        }

        /* Content Wrapper for Consistent Padding */
        .content-wrapper {
            padding: 20px;
        }

        .content-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            flex-grow: 1;
        }

        /* Styles from upload_panel.php */
        .container {
            background-color: white;
            border-radius: 12px;
            /* Increased border-radius */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            /* More pronounced shadow */
            padding: 40px;
            /* Increased padding */
            width: 100%;

            box-sizing: border-box;
        }

        h2 {
            color: rgb(58, 64, 52);
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.75em;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #343a40;
            font-size: 1.1em;
        }

        select,
        input[type="file"],
        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 10px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        select:focus,
        input[type="file"]:focus,
        input[type="text"]:focus,
        input[type="number"]:focus {
            border-color: rgb(106, 255, 0);
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        input[type="file"]::file-selector-button {
            background-color: rgb(63, 219, 52);
            color: white;
            border: none;
            padding: 12px 18px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-right: 12px;
            font-size: 1em;
        }

        input[type="file"]::file-selector-button:hover {
            background-color: rgb(96, 185, 41);
        }

        .file-drop-zone {
            border: 2px dashed #3498db;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            background-color: #f0f8ff;
            transition: all 0.3s ease;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 120px;
        }

        .file-drop-zone.dragover {
            border-color: #2980b9;
            background-color: #e0f0ff;
            box-shadow: 0 0 10px rgba(52, 152, 219, 0.5);
        }

        .file-drop-zone p {
            margin: 0;
            font-size: 1.1em;
            color: #666;
        }


        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: space-between;
        }

        .btn.download-template {
            flex: 1;
            min-width: 120px;
            background-color: #28a745;
            border-color: #28a745;
            padding: 10px 15px;
            font-size: 16px;
        }

        .btn.download-template:hover {
            background-color: rgb(33, 38, 136);
            border-color: rgb(30, 68, 126);
            transform: none;
        }

        .btn.confirm-upload {
            background-color: rgb(40, 57, 167);
            border-color: rgb(40, 97, 167);
        }

        .btn.confirm-upload:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }

        .message {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 1.1em;
            text-align: center;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .preview-container {
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow-x: auto;
        }



        .admin-badge {
            background-color: #28a745;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 1em;
            margin-right: auto;
            white-space: nowrap;
        }

        .logout-btn,
        .other-page-btn {
            color: #007bff;
            text-decoration: none;
            transition: all 0.3s ease;
            padding: 8px 15px;
            border-radius: 6px;
            border: 1px solid #007bff;
            white-space: nowrap;
        }

        .logout-btn:hover,
        .other-page-btn:hover {
            color: #0056b3;
            background-color: #e0f0ff;
            border-color: #0056b3;
            text-decoration: none;
        }

        .validation-message {
            color: #dc3545;
            margin-top: 8px;
            font-size: 1em;
        }

        /* Define a new style option for dark datepicker */
        .datepicker-dark {
            box-sizing: border-box;
            overflow: hidden;
            min-height: 70px;
            display: block;
            width: 220px;
            min-width: 220px;
            padding: 8px;
            position: absolute;
            font: 14px 'Vazir', sans-serif;
            border: 1px solid #4b5563;
            background-color: #1f2937;
            color: #e5e7eb;
            border-radius: 6px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .datepicker-dark .datepicker-day-view .month-grid-box .header .header-row-cell {
            display: block;
            width: 14.2%;
            height: 25px;
            float: right;
            line-height: 25px;
            font: 11px;
            font-weight: bold;
            color: #fff;
        }

        .datepicker-dark .datepicker-navigator .pwt-btn-next,
        .datepicker-dark .datepicker-navigator .pwt-btn-switch,
        .datepicker-dark .datepicker-navigator .pwt-btn-prev {
            display: block;
            float: left;
            height: 28px;
            line-height: 28px;
            font-weight: bold;
            background-color: rgba(250, 250, 250, 0.1);
            color: #e5e7eb;
        }

        .datepicker-dark .toolbox .pwt-btn-today {
            background-color: #e5e7eb;
            float: right;
            display: block;
            font-weight: bold;
            font-size: 11px;
            height: 24px;
            line-height: 24px;
            white-space: nowrap;
            margin: 0 auto;
            margin-left: 5px;
            padding: 0 5px;
            min-width: 50px;
        }

        .datepicker-dark .datepicker-day-view .table-days td span.other-month {
            background-color: "";
            color: #2585eb;
            border: none;
            text-shadow: none;
        }

        @media (max-width: 768px) {
            .h .header-content {
        flex-direction: column; 
        gap: 0.5rem; /* Add some space between items */
    }
  .navigation .user-infoheader {
        width: 100%;
        background: rgba(0, 0, 0, 0.05); /* Light background to stand out */
        padding: 1rem;
        margin-bottom: 0.5rem; /* Space between profile and first link */
        border-radius: 0;
        justify-content: flex-start; /* Align content to the right (for RTL) */
        color: #333; /* Darker text for readability */
        box-sizing: border-box;
    }

    .navigation .user-infoheader .profile-pic {
        width: 40px; /* Make avatar a bit smaller for the menu */
        height: 40px;
    }
            .user-infoheader {
                margin: 1rem 0;
                /* Add margin for spacing */
            }

            .container {
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            h2 {
                font-size: 1.5em;
            }

            .admin-badge {
                margin-right: 0;
            }

            .file-info {
                justify-content: flex-start;
            }

            .button-group {
                flex-direction: column;
            }

            .btn.download-template {
                flex: none;
                width: 100%;
            }

            /* Remove padding override on mobile */
            .content-container {
                padding: 0;
                /* Remove horizontal padding */
            }
        }

        .preview-table td[contenteditable="true"] {
            background-color: #fff3cd;
            cursor: text;
        }

        .preview-table td[contenteditable="true"]:focus {
            outline: 2px solid #ffc107;
            box-shadow: 0 0 5px rgba(255, 193, 7, 0.5);
        }

        .preview-table .edited {
            font-weight: bold;
            color: #007bff;
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }

            .navigation {
                display: none;
                width: 100%;
                flex-direction: column;
                padding: 0;
            }

            .navigation.active {
                display: flex;

            }

            .nav-item {
                width: 100%;
                border-radius: 0;
                border-bottom: 1px solidrgb(231, 235, 229);
                padding: 0.75rem 1rem;
                /* Add padding directly to nav-item */

            }

            .nav-item.active {

                border-right-color: transparent;
                /* Ensure side border is not shown */

                border-bottom-color: rgb(30, 138, 50);
                border-bottom-width: 2px;
            }

            .logout-btn {
                margin: 1rem;
                justify-content: center;
            }

            .mobile-user-infoheader {
    display: flex; 
}

            .mobile-user-infoheader {
                display: flex;
                /* Show in nav on mobile */
                padding: 1rem;
                background: rgba(255, 255, 255, 0.05);
                margin-bottom: 1rem;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                align-items: center;
                /* Vertically center items */
                gap: 0.5rem;
                /* Add some spacing */
            }

            .mobile-user-infoheader i {
                font-size: 1.2rem;
                /* Consistent icon size */
            }

            .online-users-container {
                margin-left: 0;
                margin-top: 0.5rem;
                /* Add space below title */
            }

            .online-users-dropdown {
                left: auto;
                /* Let it align naturally or set right: 0 */
                right: 0;
                min-width: 180px;
                user-select: all !important;
            }
        }

        .unread-badge {
            display: inline-block;
            /* Allows padding/margins */
            background-color: #dc3545;
            /* Red color (Bootstrap's danger color) - adjust as needed */
            color: white;
            font-size: 0.7em;
            /* Make it small */
            padding: 2px 6px;
            /* Small padding */
            border-radius: 10px;
            /* Make it pill-shaped */
            margin-right: 8px;
            /* Space from the text (adjust if RTL needs margin-left) */
            font-weight: bold;
            line-height: 1;
            /* Keep it vertically tight */
            vertical-align: middle;
            /* Try to align vertically with text */
            min-width: 18px;
            /* Ensure even single digits have some width */
            text-align: center;
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 1.5rem 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .data-table th {
            background: linear-gradient(to bottom, #f8f9fa, #e9ecef);
            color: #1e3a8a;
            font-weight: bold;
            padding: 1rem;
            text-align: right;
            border-bottom: 2px solid #dee2e6;
        }

        .data-table td {
            padding: 1rem;
            text-align: right;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.2s;
        }

        .data-table tr:hover td {
            background-color: #f8f9fa;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }


        .status-active {
            color: #059669;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .status-active::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #059669;
            border-radius: 50%;
        }

        .status-inactive {
            color: #dc2626;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .status-inactive::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #dc2626;
            border-radius: 50%;
        }

        .message {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 8px;
            text-align: right;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: slideIn 0.3s ease-out;
        }

        .message-success {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }

        .message-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .message-warning {
            background: #fffbeb;
            color: #d97706;
            border: 1px solid #fde68a;
        }


        .close {
            position: absolute;
            top: 1rem;
            left: 1rem;
            font-size: 1.5rem;
            color: #6b7280;
            cursor: pointer;
            transition: color 0.2s;
        }

        .close:hover {
            color: #374151;
        }

        /* Animations */
        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .data-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }


            .modal-content {
                width: 95%;
                margin: 5% auto;
            }
        }

        /* Print Styles */
        @media print {


            .data-table {
                box-shadow: none;
            }

            .data-table th {
                background: #f8f9fa !important;
                color: black !important;
            }
        }

        /* Custom SVG Icons */
        .icon-upload {
            width: 18px;
            height: 18px;
            margin-left: 0.5rem;
            fill: currentColor;
            /* Use the text color */
        }

        .icon-search {
            width: 16px;
            height: 16px;
            margin-left: 0.5rem;
            fill: currentColor;
        }

        .icon-calendar {
            width: 16px;
            height: 16px;
            margin-left: 0.5rem;
            fill: currentColor;
        }

        .icon-tasks {
            width: 16px;
            height: 16px;
            margin-left: 0.5rem;
            fill: currentColor;
        }

        .icon-history {
            width: 16px;
            height: 16px;
            margin-left: 0.5rem;
            fill: currentColor;
        }

        .icon-clipboard {
            width: 16px;
            height: 16px;
            margin-left: 0.5rem;
            fill: currentColor;
        }

        .icon-tools {
            width: 16px;
            height: 16px;
            margin-left: 0.5rem;
            fill: currentColor;
        }

        .icon-signout {
            width: 16px;
            height: 16px;
            margin-left: 0.5rem;
            fill: currentColor;
        }

        .icon-user-circle {
            width: 20px;
            /* Adjust as needed */
            height: 20px;
            /* Adjust as needed */
            fill: currentColor;
            /* Use the text color */
        }

        .icon-bars {
            width: 24px;
            /* Adjust as needed */
            height: 24px;
            /* Adjust as needed */
            fill: currentColor;
            /* Use the text color */
        }

        .icon-industry {
            width: 20px;
            /* Adjust as needed */
            height: 20px;
            /* Adjust as needed */
            fill: currentColor;
            /* Use the text color */
        }

        /* admin_assigne_date.php styles */
        .panel-item {
            cursor: move;
            touch-action: none;
        }

        .fc-event {
            cursor: pointer;
            margin: 5px;
            touch-action: none;
        }

        .external-events {
            max-height: 500px;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        .panel-item.hidden {
            display: none;
        }

        .search-highlight {
            background-color: #fde68a;
        }

        .panel-item.being-dragged {
            opacity: 0.5;
            background-color: #93c5fd;
        }

        /* Add icon styles */
        .fc-icon {
            /*font-family: 'FontAwesome'; REMOVED*/
            font-style: normal;
        }

        /* use svg */
        /*.fc-icon-chevron-left:before {
            content: "\f053";
        }
        .fc-icon-chevron-right:before {
            content: "\f054";
        }*/


        @media (max-width: 768px) {

            /*
            .fc-button {
                padding: 0.75em 1em !important;
                font-size: 1.1em !important;
            }
            */
            /*removed.  Let tailwind handle it*/
            .panel-item {
                padding: 1em !important;
                margin-bottom: 0.75em !important;
            }

            /* Remove padding override on mobile */
            .content-container {
                padding: 0;
                /* Remove horizontal padding */
            }
        }

        @media (max-width: 767px) {

            /* Tailwind's 'md' breakpoint */
            .md\:col-span-1 {
                grid-column: span 4 / span 4;
                /* Make it full width */
            }
        }

        .panel-svg {
            width: 100%;
            height: 300px;
            background-color: #f8f9fa;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
            padding: 1rem;
        }

        .panel-svg svg {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .table-container {
            overflow-x: auto;
        }

        .action-buttons {
            white-space: nowrap;
        }

        .nav-tabs .nav-link {
            color: #495057;
        }

        .nav-tabs .nav-link.active {
            font-weight: bold;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .modal-dialog {
            max-width: 700px;
        }

        .status-col {
            min-width: 150px;
            /*from manage_formworks.php*/
        }


        <?php
        // Add any additional styles specific to a page.
        if (isset($styles)) {
            echo "<style>\n$styles\n</style>";
        }
        ?>.dataTables_wrapper .dataTables_filter {
            float: right;
            /* Move search box to the right */
            margin-bottom: 1rem;
        }

        .dataTables_wrapper .dataTables_filter input {
            margin-left: 0.5em;
            /* Spacing between label and input */
            display: inline-block;
            /* So it respects the margin */
            width: auto;
            /* Expand to fill container */
            padding: 0.375rem 0.75rem;
            /*For spacing*/
            border-radius: 0.25rem;
            /*Rounded corners*/
            border: 1px solid #ced4da;
            /*Bootstrap default border*/
            box-sizing: border-box;
            /* Include padding and border in the element's total width and height */
        }


        /* Responsive table styles */
        .dataTables_wrapper {
            overflow-x: auto;
        }

        @media (max-width: 768px) {
            .dataTables_wrapper {
                width: 100%;
            }
        }

        .profile-link {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }

        .profile-link:hover {
            opacity: 0.8;
        }

        .project-switcher-nav .nav-link.current-project-highlight {
            background-color: #102a66 !important;
            /* A darker shade of Fereshteh blue */
            color: #f39c12 !important;
            /* Orange accent for current project */
            font-weight: bold;
        }

        .project-switcher-dropdown .dropdown-item.current-project-highlight {
            background-color: #4a627a;
            color: #f39c12;
            font-weight: bold;
        }

        .project-switcher-dropdown .dropdown-toggle {
            background-color: #f39c12;
            /* Default Orange button */
            color: #2c3e50;
        }

        .project-switcher-dropdown .dropdown-toggle:hover {
            background-color: #e67e22;
        }
    </style>
    <!-- Put all the JavaScript includes *here*, just before the closing </head> tag -->

    <script src="/assets/js/main.min.js"></script>
    <!-- Add the DataTables CSS and JS -->
    <script src="/assets/js/jquery-3.5.1.slim.min.js"></script>
    <script src="/assets/js/popper.min.js"></script>

    <script src="/assets/js/bootstrap.min.js"></script>
    <script type="text/javascript" charset="utf8" src="/assets/js/datatables.js"></script>
    <script type="text/javascript" charset="utf8" src="/assets/js/datatables.min.js"></script>


    <script src="/assets/js/persian-date.min.js"></script>
    <script src="/assets/js/persian-datepicker.min.js"></script>
    <script src="/assets/js/mobile-detect.min.js"></script>
    <script src="/assets/js/csrf-injector.js"></script>
    <script src="/assets/js/global.js" defer></script>
</head>

<body>
    <div class="main-container">
        <header class="top-header">
            <div class="header-content">
                <div class="site-logo-container">
                    <a href="/">
                        <img src="/assets/images/alumglass-farsi-logo-H40.png" alt="HPC Factory Logo" class="site-logo">
                    </a>
                    <h1 class="site-title" style="display: flex; align-items: center; gap: 0.5rem;">
                        <!-- Keep the SVG icon *inside* the h1 -->
                        <svg class="icon-industry" viewBox="0 0 24 24" style="flex-shrink: 0;"><?php echo htmlspecialchars($pageTitle); ?>
                            <path d="M19.5 13h-1.8v2.5h-1.5V13h-1.8v2.5h-1.5V13h-1.8v2.5h-1.5V13H8.1v2.5H6.6V13H4.8v8.5h14.7V13zm-6 5h-1.5v-2h1.5v2zm7.5 2h-16V11.5h1.5v.8h1.5v-.8h1.8v.8h1.5v-.8h1.8v.8h1.5v-.8h1.8v.8h1.5v-.8H18v.8h1.5V20zM21 8.9c-.1-.3-.4-.5-.7-.5H3.8c-.3 0-.6.2-.7.5l-1.9 5.3V21h20v-6.8L21 8.9zm-1 10.6H4v-4.5l.9-2.6h14.2l.9 2.6v4.5z" />
                        </svg>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                </div>


                <a href="/logout.php" id="logout-link" class="logout-btn">
                    <!-- Inline SVG for sign-out-alt -->
                    <svg class="icon-signout" viewBox="0 0 24 24">
                        <path d="M14.08,15.59L16.67,13H7V11H16.67L14.08,8.41L15.5,7L20.5,12L15.5,17L14.08,15.59M19,3A2,2 0 0,1 21,5V9.67L19,7.67V5H5V19H19V16.33L21,14.33V19A2,2 0 0,1 19,21H5C3.89,21 3,20.1 3,19V5C3,3.89 3.89,3 5,3H19Z" />
                    </svg>
                    خروج
                </a>
                <button class="mobile-menu-btn">
                    <!-- Inline SVG for bars (hamburger menu) -->
                    <svg class="icon-bars" viewBox="0 0 24 24">
                        <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z" />
                    </svg>
                </button>
            </div>
        </header>

        <nav class="nav-container">
            <div class="navigation" id="main-nav">
                                <div class="user-infoheader">
                    <!-- Make the avatar and username clickable to redirect to user's profile -->
                    <a href="/../profile.php" class="profile-link">
                        <!-- Display user's avatar or default avatar if not set -->
                        <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Profile Picture" class="profile-pic">
                        <!-- Inline SVG for user-circle -->
                        <svg class="icon-user-circle" viewBox="0 0 24 24">
                            <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 5a3 3 0 1 1 0 6 3 3 0 0 1 0-6zm0 13c-2.67 0-8 1.34-8 4v1h16v-1c0-2.66-5.33-4-8-4z" />
                        </svg>
                        <?php
                        echo htmlspecialchars(
                            ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')
                        );
                        ?>
                        (<?php echo $roleText; ?>)
                    </a>
                </div>
                <?php if (hasAccess(['admin', 'superuser', 'user', 'cat', 'car', 'coa', 'crs'])): ?>
                    <a href="index.php" class="nav-item<?php echo getActiveClass('index.php', $current_page_filename); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12L12 3l9 9" />
                            <path d="M9 21V12h6v9" />
                        </svg>

                        خانه
                    </a>
                <?php endif; ?>
                <?php if (hasAccess(['admin', 'superuser', 'user', 'cat', 'car', 'coa', 'crs'])): ?>
                    <a href="contractor_batch_update.php" class="nav-item<?php echo getActiveClass('contractor_batch_update.php', $current_page_filename); ?>">
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
                    <a href="inspection_dashboard.php" class="nav-item<?php echo getActiveClass('inspection_dashboard.php', $current_page_filename); ?>">
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
                    <a href="reports.php" class="nav-item<?php echo getActiveClass('reports.php', $current_page_filename); ?>">
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
                    <a href="checklist_manager.php" class="nav-item<?php echo getActiveClass('checklist_manager.php', $current_page_filename); ?>">
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
                    <a href="workflow_manager.php" class="nav-item<?php echo getActiveClass('workflow_manager.php', $current_page_filename); ?>">
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
                    <a href="my_calendar.php" class="nav-item<?php echo getActiveClass('my_calendar.php', $current_page_filename); ?>">
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
                    <a href="instruction.php" target="_blank" rel="noopener noreferrer" class="nav-item<?php echo getActiveClass('instruction.php', $current_page_filename); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                        راهنمای تعاملی
                    </a>
                <?php endif; ?>
                <?php // --- Messages Link Block ---
                // Original access check
                if (hasAccess(['admin', 'superuser', 'supervisor', 'planner', 'user', 'receiver', 'cnc_operator', 'guest', 'user', 'cat', 'car', 'coa', 'crs'])) : // Adjust roles as needed
                ?>
                    <a href="/messages.php"
                        class="nav-item<?php echo getActiveClass('messages.php', $current_page_filename); ?>"
                        style="position: relative; display: inline-flex; align-items: center;"> <!-- Add styles for alignment -->

                        <!-- Your existing SVG Icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="margin-left: 0.5rem;">
                            <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8l8 5 8-5v10zm-8-7L4 6h16l-8 5z" />
                        </svg>

                        پیام‌ها <!-- Message Text -->

                        <?php // --- Add the Badge ---
                        // Conditionally display badge only if count > 0
                        if ($totalUnreadCount > 0): ?>
                            <span class="unread-badge"><?= $totalUnreadCount ?></span>
                        <?php endif; ?>
                        <?php if (hasAccess(['superuser'])): ?>
                            <a href="/admin.php" class="nav-item" style="background-color: #5c6ac4; color: white;">
                                <i class="fas fa-cogs me-2"></i> مدیریت مرکزی کاربران
                            </a>
                        <?php endif; ?>

                        <!-- **** END: Change Project Link/Dropdown **** -->
                        <?php // --- End Badge --- 
                        ?>

                    </a>
                <?php endif; ?>
                <?php if (count($all_user_accessible_projects) > 1): ?>
                    <li class="nav-item dropdown project-switcher-dropdown ms-auto">
                        <a class="nav-link dropdown-toggle btn btn-sm" href="#" id="projectSwitcherDropdown" role="button"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">
                            <i class="fas fa-exchange-alt me-1"></i> تعویض پروژه
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end" aria-labelledby="projectSwitcherDropdown">
                            <?php foreach ($all_user_accessible_projects as $project_nav_item): ?>
                                <?php
                                $is_current_project_link = (isset($_SESSION['current_project_id']) && $_SESSION['current_project_id'] == $project_nav_item['project_id']);
                                ?>
                                <li>
                                    <form action="/project_switch_handler.php" method="POST" class="d-inline">
                                        <input type="hidden" name="switch_to_project_id" value="<?= $project_nav_item['project_id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                        <button type="submit"
                                            class="dropdown-item <?= $is_current_project_link ? 'current-project-highlight active' : '' ?>"
                                            <?= $is_current_project_link ? 'disabled title="شما در این پروژه هستید"' : 'title="رفتن به پروژه ' . escapeHtml($project_nav_item['project_name']) . '"' ?>>
                                            <i class="fas fa-folder me-2"></i> <?= escapeHtml($project_nav_item['project_name']) ?>
                                            <?= $is_current_project_link ? ' (فعلی)' : '' ?>
                                        </button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endif; ?>
                <!-- **** END: Project Switcher **** -->
            </div>


            <?php // --- End Messages Link Block --- 
            ?>
        </nav>
        
        <script>
            function updateNotificationBadge() {
                fetch('/ghom/api/get_notifications.php')
                    .then(res => res.json())
                    .then(data => {
                        const badge = document.getElementById('notification-badge');
                        if (badge && data.total_unread > 0) {
                            badge.textContent = data.total_unread;
                            badge.style.display = 'inline-block';
                        } else if (badge) {
                            badge.style.display = 'none';
                        }
                    })
                    .catch(err => console.error('Error fetching notification count:', err));
            }

            document.addEventListener('DOMContentLoaded', () => {
                updateNotificationBadge();
                // Check for new notifications every 2 minutes
                setInterval(updateNotificationBadge, 120000);
            });
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {

                const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
                const navigation = document.querySelector('.navigation');

                mobileMenuBtn.addEventListener('click', function() {
                    navigation.classList.toggle('active');
                });

                // Close menu when clicking outside
                document.addEventListener('click', function(event) {
                    if (!event.target.closest('.nav-container') &&
                        !event.target.closest('.mobile-menu-btn')) {
                        navigation.classList.remove('active');
                    }
                });

                // Close menu when window is resized to desktop view
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 768) {
                        navigation.classList.remove('active');
                    }
                });
            });
        </script>
         <script>
        // Wait until the page content is fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Find the logout link by the ID we just added
            const logoutLink = document.getElementById('logout-link');

            // If the link exists on the page...
            if (logoutLink) {
                // ...add a 'click' event listener to it.
                logoutLink.addEventListener('click', function() {
                    console.log('Logout clicked. Clearing private key from local storage...');
                    
                    // CRUCIAL: Remove the user's private key from the browser's storage
                    localStorage.removeItem('user_private_key');
                    
                    // After this code runs, the browser will continue to the href="/logout.php" as normal.
                });
            }
        });
    </script>