<?php
/**
 * /chat/ — New chat page (replaces the legacy messages.php monolith).
 *
 * The PHP layer is intentionally thin: auth, CSRF token, contact list
 * for the composer/group-create modals, and a semantic HTML skeleton.
 * All dynamic rendering is handled by chat/assets/js/chat-app.js.
 */

require_once __DIR__ . '/../sercon/bootstrap.php';
secureSession();
requireLogin();

$pdo = getCommonDBConnection();
$currentUserId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE id != ? ORDER BY first_name");
$stmt->execute([$currentUserId]);
$contactsForGroup = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csrf = generateCsrfToken();
$sessionId = session_id();

$wsUrl = getenv('WS_PUBLIC_URL') ?: '';
if ($wsUrl === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'wss' : 'ws';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $wsUrl = $scheme . '://' . $host . '/ws';
}

$pageTitle = 'پیام‌ها';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>

    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <meta name="session-token" content="<?= e($sessionId) ?>">
    <meta name="user-id" content="<?= (int)$currentUserId ?>">
    <meta name="ws-url" content="<?= e($wsUrl) ?>">

    <link rel="icon" href="/assets/images/favicon-32x32.png">
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/chat/assets/css/chat.css">

    <script type="module" src="/chat/assets/js/chat-app.js"></script>
</head>
<body class="chat-body">

<header class="chat-topbar" role="banner">
    <a class="chat-topbar__back" href="/select_project.php" aria-label="بازگشت">‹</a>
    <h1 class="chat-topbar__title"><?= e($pageTitle) ?></h1>
    <span id="connection-status" class="chat-connection-status" aria-live="polite">در حال اتصال…</span>
</header>

<main id="chat-shell" class="chat-shell" role="main">
    <aside class="chat-sidebar" aria-label="فهرست گفتگوها">
        <div class="chat-sidebar__header">
            <div class="chat-sidebar__title">گفتگوها</div>
            <div class="chat-sidebar__actions">
                <button id="contact-picker-btn" type="button" title="چت جدید" aria-label="چت جدید">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                </button>
                <button id="new-group-btn" type="button" title="ساخت گروه" aria-label="ساخت گروه">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </button>
            </div>
        </div>

        <div class="chat-search">
            <input id="chat-search-input" type="search" placeholder="جست‌وجو در پیام‌ها…" autocomplete="off">
            <div id="chat-search-results" class="chat-search-results" role="listbox" aria-label="نتایج جست‌وجو"></div>
        </div>

        <nav id="conv-list" class="chat-conv-list" aria-label="گفتگوها">
            <div class="chat-loading">در حال بارگذاری…</div>
        </nav>
    </aside>

    <section class="chat-main" aria-label="محتوای گفتگو">
        <header id="chat-header" class="chat-header">
            <div class="chat-empty" style="padding:0;">یک گفتگو را انتخاب کنید</div>
        </header>

        <div id="chat-messages" class="chat-messages" role="log" aria-live="polite" aria-relevant="additions"></div>

        <div id="typing-indicator" aria-live="polite"></div>

        <div id="reply-bar" class="chat-reply-bar" role="region" aria-label="در پاسخ به">
            <span class="chat-reply-bar__label" id="reply-text"></span>
            <button id="clear-reply-btn" type="button" aria-label="لغو پاسخ">×</button>
        </div>
        <div id="preview-bar" class="chat-preview-bar" role="region" aria-label="فایل پیوست">
            <span class="chat-preview-bar__label" id="preview-text"></span>
            <button id="clear-preview-btn" type="button" aria-label="حذف فایل">×</button>
        </div>

        <div class="chat-composer">
            <button id="attach-btn" type="button" title="پیوست فایل" aria-label="پیوست فایل">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
            </button>
            <input id="file-input" type="file" hidden>

            <button id="emoji-btn" type="button" title="شکلک" aria-label="شکلک">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>
            </button>

            <textarea id="message-input" class="chat-composer__input" rows="1" placeholder="پیامی بنویسید…" aria-label="پیام"></textarea>

            <button id="send-btn" type="button" class="chat-composer__send" title="ارسال" aria-label="ارسال">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
            </button>
        </div>
    </section>
</main>

<!-- Contact picker modal -->
<div id="contact-modal" class="chat-modal" role="dialog" aria-modal="true" aria-labelledby="contact-modal-title" hidden aria-hidden="true">
    <div class="chat-modal__card">
        <h2 id="contact-modal-title" class="chat-modal__title">چت جدید</h2>
        <div class="chat-modal__scroll">
            <div id="contact-list"></div>
        </div>
    </div>
</div>

<!-- Group creation modal -->
<div id="group-modal" class="chat-modal" role="dialog" aria-modal="true" aria-labelledby="group-modal-title" hidden aria-hidden="true">
    <form id="group-form" class="chat-modal__card" autocomplete="off">
        <h2 id="group-modal-title" class="chat-modal__title">ساخت گروه</h2>
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <label>
            نام گروه
            <input type="text" name="name" required maxlength="100" style="width:100%;padding:8px 12px;border:1px solid var(--chat-border);border-radius:8px;margin-top:4px;">
        </label>
        <div class="chat-modal__scroll">
            <div class="chat-group-members">
                <?php foreach ($contactsForGroup as $c): ?>
                    <label>
                        <input type="checkbox" name="members" value="<?= (int)$c['id'] ?>">
                        <?= e(trim($c['first_name'] . ' ' . $c['last_name'])) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="chat-modal__actions">
            <button type="button" onclick="document.getElementById('group-modal').classList.remove('is-open')">انصراف</button>
            <button type="submit">ساخت گروه</button>
        </div>
    </form>
</div>

</body>
</html>
