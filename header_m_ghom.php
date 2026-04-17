<?php
// ghom/header_m_ghom.php (Minimalist Header for Messenger)

// --- Bootstrap and Session ---
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../sercon/bootstrap.php';
    secureSession();
}

// --- Basic User Data ---
$avatarPath = '/assets/images/default-avatar.jpg';
$userName = '';
if (isLoggedIn()) {
    try {
        $pdo_common_header = getCommonDBConnection();
        $stmt_user_header = $pdo_common_header->prepare("SELECT first_name, last_name, avatar_path FROM users WHERE id = ?");
        $stmt_user_header->execute([$_SESSION['user_id']]);
        $user_header_data = $stmt_user_header->fetch(PDO::FETCH_ASSOC);

        if ($user_header_data) {
            $userName = htmlspecialchars($user_header_data['first_name'] . ' ' . $user_header_data['last_name']);
            $potentialAvatarWebPath = $user_header_data['avatar_path'] ?? null;
            if ($potentialAvatarWebPath && fileExistsAndReadable(PUBLIC_HTML_ROOT . $potentialAvatarWebPath)) {
                $avatarPath = '/uploads/' . ltrim($potentialAvatarWebPath, '/');
            }
        }
    } catch (Exception $e) {
        logError("Error fetching user data for messenger header: " . $e->getMessage());
    }
}

// --- Project Link Logic ---
$project_base_path = $_SESSION['current_project_base_path'] ?? '/';
$project_index_url = rtrim($project_base_path, '/') . '/index.php';

// --- Helper function to check access ---
if (!function_exists('hasAccess')) {
    function hasAccess($requiredRoles) {
        $current_user_role = $_SESSION['role'] ?? '';
        return in_array($current_user_role, (array)$requiredRoles);
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'پیام‌ها'); ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-32x32.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @font-face {
            font-family: 'Vazir';
            src: url('/assets/fonts/Vazir-Regular.woff2') format('woff2');
        }
        body {
            font-family: 'Vazir', sans-serif;
            padding-top: 60px; /* Space for the fixed header */
            background-color: #f4f7f6; /* Match message page background */
        }
        .header-messenger {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            background: linear-gradient(135deg, rgba(3, 61, 7, 0.5) 0%, rgb(123, 88, 6) 100%); /* Style from ghom_header */
            color: white;
            padding: 0 1rem;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .header-messenger a {
            color: inherit;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: 6px;
            transition: background-color 0.2s;
        }
        .header-messenger a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .header-title {
            font-size: 1.2rem;
            font-weight: bold;
        }
        .user-avatar-messenger {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }
        .back-link i {
            font-size: 1.5rem;
        }
    </style>
</head>
<body>
    <header class="header-messenger">
        <div class="profile-link">
            <a href="/profile.php">
                <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="آواتار" class="user-avatar-messenger">
                <span><?php echo $userName; ?></span>
            </a>
        </div>
        <div class="header-title">
            <?php echo htmlspecialchars($pageTitle ?? 'پیام‌ها'); ?>
        </div>
        <div class="back-link">
            <?php // Show "Select Project" for admins/superusers, and "Back to Project" for everyone else. ?>
            <?php if (hasAccess(['admin', 'superuser'])): ?>
                <a href="/select_project.php" title="انتخاب پروژه">
                    <i class="fas fa-th-large"></i>
                </a>
            <?php else: ?>
                <a href="<?php echo htmlspecialchars($project_index_url); ?>" title="بازگشت به پروژه">
                    <i class="fas fa-arrow-left"></i>
                </a>
            <?php endif; ?>
        </div>
    </header>

        <nav class="nav-container">
            <div class="navigation" id="main-nav">
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
                if (hasAccess(['admin', 'superuser', 'supervisor', 'planner', 'user', 'receiver', 'cnc_operator', 'guest'])) : // Adjust roles as needed
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
        