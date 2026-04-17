<?php
// ghom/header_mobile.php
// --- Bootstrap and Session ---
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../sercon/bootstrap.php';
    secureSession();
}

// --- Redirect if not logged in ---
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit();
}

// --- Database Connection & Data Fetching ---
$pdo_common_header = null;
try {
    $pdo_common_header = getCommonDBConnection();
} catch (Exception $e) {
    logError("Critical: Common DB connection failed in mobile header: " . $e->getMessage());
}

// Initialize variables
$totalUnreadCount = 0;
$avatarPath = '/assets/images/default-avatar.jpg'; // Default
$all_user_accessible_projects = [];
$current_project_name = $_SESSION['current_project_name'] ?? 'پروژه';

if ($pdo_common_header && isset($_SESSION['user_id'])) {
    $currentUserIdHeader = $_SESSION['user_id'];

    // Fetch Unread Messages Count
    try {
        $stmt_total_unread = $pdo_common_header->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0 AND is_deleted = 0");
        $stmt_total_unread->execute([$currentUserIdHeader]);
        $totalUnreadCount = (int) $stmt_total_unread->fetchColumn();
    } catch (PDOException $e) {
        logError("Error fetching unread count in mobile header: " . $e->getMessage());
    }

    // Fetch User Avatar Path
    try {
        $stmt_avatar = $pdo_common_header->prepare("SELECT avatar_path FROM users WHERE id = ?");
        $stmt_avatar->execute([$currentUserIdHeader]);
        $potentialAvatarWebPath = $stmt_avatar->fetchColumn();
        if ($potentialAvatarWebPath && fileExistsAndReadable(PUBLIC_HTML_ROOT . $potentialAvatarWebPath)) {
            $avatarPath = '/' . ltrim($potentialAvatarWebPath, '/');
        }
    } catch (PDOException $e) {
        logError("Error fetching avatar path in mobile header: " . $e->getMessage());
    }
    
    // Fetch ALL accessible projects
    try {
        $stmt_all_projects = $pdo_common_header->prepare("SELECT p.project_id, p.project_name FROM projects p JOIN user_projects up ON p.project_id = up.project_id WHERE up.user_id = ? AND p.is_active = TRUE ORDER BY p.project_name");
        $stmt_all_projects->execute([$currentUserIdHeader]);
        $all_user_accessible_projects = $stmt_all_projects->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error fetching all accessible projects in mobile header: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'HPC Factory'; ?></title>
    <style>
        :root {
            --primary-color: #007cba;
            --background-color: #f0f2f5;
            --header-bg: #ffffff;
        }
        body {
            margin: 0;
            padding-top: 60px; /* Space for the fixed header */
            font-family: 'Vazir', sans-serif; /* Using Vazir font from your desktop version */
            background-color: var(--background-color);
        }
        .mobile-main-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: var(--header-bg);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            z-index: 1000;
        }
        .header-logo a {
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        .header-logo img {
            height: 35px;
            width: auto;
        }
        .hamburger-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
        }
        .hamburger-btn svg {
            width: 28px;
            height: 28px;
            fill: #333;
        }
        .nav-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1001;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .nav-overlay.visible {
            opacity: 1;
            visibility: visible;
        }
        .mobile-nav-menu {
            position: fixed;
            top: 0;
            right: -300px; /* Start off-screen */
            width: 300px;
            height: 100%;
            background: #fff;
            z-index: 1002;
            transition: right 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        .mobile-nav-menu.open {
            right: 0;
        }
        .nav-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background-color: var(--primary-color);
            color: white;
        }
        .nav-header img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
        }
        .nav-user-details span {
            display: block;
        }
        .nav-user-details .username {
            font-weight: bold;
        }
        .nav-user-details .project-name {
            font-size: 0.9em;
            opacity: 0.9;
        }
        .nav-links {
            flex-grow: 1;
            overflow-y: auto;
            padding: 8px 0;
        }
        .nav-links a, .nav-links button {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            text-decoration: none;
            color: #333;
            font-size: 1.1em;
            border: none;
            background: none;
            width: 100%;
            text-align: right;
            cursor: pointer;
        }
        .nav-links a:hover, .nav-links button:hover {
            background-color: #f5f5f5;
        }
        .nav-links svg {
            width: 24px;
            height: 24px;
            fill: #555;
        }
        .nav-links .unread-badge {
            margin-right: auto;
            background-color: #dc3545;
            color: white;
            font-size: 0.8em;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: bold;
        }
        .nav-footer {
            padding: 16px;
            border-top: 1px solid #eee;
        }
        .logout-btn {
            width: 100%;
            padding: 12px;
            border: none;
            background-color: #dc3545;
            color: white;
            border-radius: 8px;
            font-size: 1.1em;
            cursor: pointer;
        }
    </style>
</head>
<body>

<header class="mobile-main-header">
    <div class="header-logo">
        <a href="/index.php">
            <img src="/assets/images/alumglass-farsi-logo-H40.png" alt="AlumGlass Logo">
        </a>
    </div>
    <button class="hamburger-btn" id="hamburgerBtn" aria-label="Open Menu">
        <svg viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
    </button>
</header>

<div class="nav-overlay" id="navOverlay"></div>

<nav class="mobile-nav-menu" id="mobileNavMenu">
    <div class="nav-header">
        <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Profile Picture">
        <div class="nav-user-details">
            <span class="username"><?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?></span>
            <span class="project-name"><?php echo htmlspecialchars($current_project_name); ?></span>
        </div>
    </div>

    <div class="nav-links">
        <a href="/ghom/index.php">
            <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            <span>خانه</span>
        </a>
        <a href="/messages.php">
            <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8l8 5 8-5v10zm-8-7L4 6h16l-8 5z"/></svg>
            <span>پیام‌ها</span>
            <?php if ($totalUnreadCount > 0): ?>
                <span class="unread-badge"><?php echo $totalUnreadCount; ?></span>
            <?php endif; ?>
        </a>

        <?php if (count($all_user_accessible_projects) > 1): ?>
            <hr>
            <?php foreach ($all_user_accessible_projects as $project): ?>
                 <?php if ($_SESSION['current_project_id'] != $project['project_id']): // Show only other projects to switch to ?>
                    <form action="/project_switch_handler.php" method="POST" style="margin: 0;">
                        <input type="hidden" name="switch_to_project_id" value="<?php echo $project['project_id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? '' ?>">
                        <button type="submit">
                             <svg viewBox="0 0 24 24"><path d="M17 17H7V7h10v10zm-2-8H9v6h6V9zm4-4H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
                             <span>رفتن به: <?php echo htmlspecialchars($project['project_name']); ?></span>
                        </button>
                    </form>
                <?php endif; ?>
            <?php endforeach; ?>
            <hr>
        <?php endif; ?>

        <a href="/index.php">
            <svg viewBox="0 0 24 24"><path d="M20 6h-2.18c.11-.31.18-.65.18-1 0-1.66-1.34-3-3-3s-3 1.34-3 3c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM4 19V8h16v11H4z"/></svg>
            <span>بازگشت به سایت اصلی</span>
        </a>

    </div>

    <div class="nav-footer">
        <a href="/logout.php" style="text-decoration: none;">
            <button class="logout-btn">خروج</button>
        </a>
    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const mobileNavMenu = document.getElementById('mobileNavMenu');
        const navOverlay = document.getElementById('navOverlay');

        function openMenu() {
            mobileNavMenu.classList.add('open');
            navOverlay.classList.add('visible');
        }

        function closeMenu() {
            mobileNavMenu.classList.remove('open');
            navOverlay.classList.remove('visible');
        }

        hamburgerBtn.addEventListener('click', openMenu);
        navOverlay.addEventListener('click', closeMenu);
    });
</script>

</body>
</html>