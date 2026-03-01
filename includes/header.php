<?php
/* ============================================================
   THE ARCHIVES OF CLAN LAR — Header (Phase 2)
   Language toggle (EN/RU) + Notification bell + Translated nav
   ============================================================ */
if (!function_exists('getDB')) {
    require_once __DIR__ . '/../config/db.php';
}

$currentUser   = currentUser();
$currentLang   = getLang();
$unreadNotifs  = $currentUser ? getUnreadNotificationCount((int)$currentUser['id']) : 0;
$unreadMsgs    = $currentUser ? getUnreadCount((int)$currentUser['id']) : 0;
$isAdmin       = $currentUser && ($currentUser['role'] === 'admin');
$currentPage   = basename($_SERVER['PHP_SELF'], '.php');

// Page variables (set by including page, or defaults)
$pageTitle  = $pageTitle  ?? (SITE_NAME);
$pageDesc   = $pageDesc   ?? '';
$pageAccent = $pageAccent ?? 'rgba(106,158,180,0.4)';
$extraCss   = $extraCss   ?? '';

// Background
$bgStyle = '';
if ($currentUser && !empty($currentUser['background'])) {
    $bgStyle = getBackgroundCss($currentUser['background']);
}
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?php if ($pageDesc): ?><meta name="description" content="<?= e($pageDesc) ?>"><?php endif; ?>
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="stylesheet" href="/css/brightness-patch.css">
    <style>
    /* ── Notification Bell ────────────────────────────────── */
    .header-actions { display:flex; align-items:center; gap:0.75rem; }

    .lang-toggle { display:flex; gap:0; border:1px solid var(--border-card, #1e1d24); border-radius:2px; overflow:hidden; }
    .lang-toggle__btn {
        padding:0.3rem 0.55rem; font-family:var(--font-label); font-size:0.55rem; letter-spacing:0.15em;
        text-transform:uppercase; color:var(--text-dim, #555); background:transparent;
        border:none; cursor:pointer; transition:all 0.25s ease; line-height:1;
    }
    .lang-toggle__btn:hover { color:var(--text-secondary, #9e9a92); }
    .lang-toggle__btn.active { color:var(--text-primary, #d4d0c8); background:rgba(90,138,158,0.12); }

    .notif-wrapper { position:relative; }
    .notif-bell {
        background:none; border:none; cursor:pointer; padding:0.35rem; position:relative;
        color:var(--text-dim, #555); transition:color 0.25s ease; font-size:1.1rem; line-height:1;
    }
    .notif-bell:hover { color:var(--text-secondary, #9e9a92); }
    .notif-bell__badge {
        position:absolute; top:-2px; right:-4px; min-width:16px; height:16px;
        background:var(--crimson, #c44); color:#fff; font-size:0.55rem; font-family:var(--font-label);
        border-radius:50%; display:flex; align-items:center; justify-content:center;
        line-height:1; padding:0 3px; pointer-events:none;
    }
    .notif-bell__badge:empty, .notif-bell__badge[data-count="0"] { display:none; }

    /* Dropdown panel */
    .notif-dropdown {
        display:none; position:absolute; top:calc(100% + 0.5rem); right:-0.5rem;
        width:320px; max-height:400px; overflow-y:auto;
        background:var(--bg-card, #0e0e12); border:1px solid var(--border-card, #1e1d24);
        box-shadow:0 8px 32px rgba(0,0,0,0.5); z-index:1000;
    }
    .notif-dropdown.open { display:block; }
    .notif-dropdown__header {
        display:flex; justify-content:space-between; align-items:center;
        padding:0.75rem 1rem; border-bottom:1px solid var(--border-card, #1e1d24);
    }
    .notif-dropdown__title {
        font-family:var(--font-label); font-size:0.55rem; letter-spacing:0.2em;
        text-transform:uppercase; color:var(--text-dim, #555);
    }
    .notif-dropdown__mark-read {
        background:none; border:none; cursor:pointer; font-family:var(--font-body);
        font-size:0.75rem; color:var(--ayleid-blue, #6a9eb4); padding:0;
    }
    .notif-dropdown__mark-read:hover { text-decoration:underline; }

    .notif-item {
        display:block; padding:0.75rem 1rem; border-bottom:1px solid rgba(30,29,36,0.5);
        text-decoration:none; transition:background 0.2s ease; cursor:pointer;
    }
    .notif-item:hover { background:rgba(90,138,158,0.06); }
    .notif-item.unread { border-left:2px solid var(--ayleid-blue, #6a9eb4); }
    .notif-item__title { font-family:var(--font-body); font-size:0.88rem; color:var(--text-secondary, #9e9a92); line-height:1.4; margin-bottom:0.2rem; }
    .notif-item.unread .notif-item__title { color:var(--text-primary, #d4d0c8); }
    .notif-item__time { font-family:var(--font-label); font-size:0.55rem; color:var(--text-faint, #3a3840); letter-spacing:0.1em; }

    .notif-empty {
        padding:2rem 1rem; text-align:center; font-family:var(--font-body);
        font-style:italic; color:var(--text-dim, #555); font-size:0.9rem;
    }

    /* ── Nav active state ─────────────────────────────────── */
    .nav-link.active { color:var(--text-primary, #d4d0c8); border-bottom-color:var(--ayleid-blue, #6a9eb4); }

    /* ── Messages badge in nav ────────────────────────────── */
    .nav-badge {
        display:inline-flex; align-items:center; justify-content:center;
        min-width:16px; height:16px; padding:0 3px;
        background:var(--crimson, #c44); color:#fff; font-size:0.55rem;
        border-radius:50%; margin-left:0.3rem; vertical-align:middle;
        font-family:var(--font-label); line-height:1;
    }

    <?= $extraCss ?>
    </style>
</head>
<body<?php if ($bgStyle): ?> style="<?= $bgStyle ?>"<?php endif; ?>>

<header class="site-header">
    <div class="header-inner">

        <!-- Logo / Site name -->
        <a href="/" class="site-logo">
            <span class="site-logo__symbol">◆</span>
            <span class="site-logo__text"><?= e(SITE_NAME) ?></span>
        </a>

        <!-- Navigation -->
        <nav class="main-nav">
            <a href="/houses" class="nav-link<?= $currentPage === 'houses' ? ' active' : '' ?>"><?= t('nav.houses') ?></a>
            <a href="/news" class="nav-link<?= $currentPage === 'news' ? ' active' : '' ?>"><?= t('nav.news') ?></a>
            <a href="/contact" class="nav-link<?= $currentPage === 'contact' ? ' active' : '' ?>"><?= t('nav.contact') ?></a>

            <?php if ($currentUser): ?>
                <a href="/cabinet" class="nav-link<?= $currentPage === 'cabinet' ? ' active' : '' ?>">
                    <?= t('nav.cabinet') ?>
                    <?php if ($unreadMsgs > 0): ?>
                        <span class="nav-badge"><?= $unreadMsgs ?></span>
                    <?php endif; ?>
                </a>
                <?php if ($isAdmin): ?>
                    <a href="/admin" class="nav-link<?= $currentPage === 'admin' ? ' active' : '' ?>"><?= t('nav.admin') ?></a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <!-- Right side: lang toggle + notification bell + auth -->
        <div class="header-actions">

            <!-- Language toggle -->
            <div class="lang-toggle" id="langToggle">
                <button class="lang-toggle__btn<?= $currentLang === 'en' ? ' active' : '' ?>" data-lang="en">EN</button>
                <button class="lang-toggle__btn<?= $currentLang === 'ru' ? ' active' : '' ?>" data-lang="ru">RU</button>
            </div>

            <?php if ($currentUser): ?>
                <!-- Notification bell -->
                <div class="notif-wrapper" id="notifWrapper">
                    <button class="notif-bell" id="notifBell" aria-label="<?= t('notif.title') ?>">
                        &#128276;
                        <span class="notif-bell__badge" id="notifBadge" data-count="<?= $unreadNotifs ?>"><?= $unreadNotifs > 0 ? $unreadNotifs : '' ?></span>
                    </button>
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-dropdown__header">
                            <span class="notif-dropdown__title"><?= t('notif.title') ?></span>
                            <button class="notif-dropdown__mark-read" id="notifMarkRead"><?= t('notif.mark_read') ?></button>
                        </div>
                        <div class="notif-list" id="notifList">
                            <div class="notif-empty"><?= t('notif.empty') ?></div>
                        </div>
                    </div>
                </div>

                <!-- Logout -->
                <a href="/logout" class="nav-link nav-link--auth"><?= t('nav.logout') ?></a>
            <?php else: ?>
                <a href="/login" class="nav-link nav-link--auth"><?= t('nav.enter') ?></a>
            <?php endif; ?>

        </div>
    </div>
</header>

<main>

<script>
/* ── Language Toggle ──────────────────────────────────────── */
document.getElementById('langToggle')?.addEventListener('click', async (e) => {
    const btn = e.target.closest('.lang-toggle__btn');
    if (!btn || btn.classList.contains('active')) return;

    const lang = btn.dataset.lang;
    try {
        const res = await fetch('/api/set-language.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ lang })
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        }
    } catch (err) {
        console.error('Language switch failed:', err);
    }
});

<?php if ($currentUser): ?>
/* ── Notification System ──────────────────────────────────── */
(function() {
    const bell       = document.getElementById('notifBell');
    const dropdown   = document.getElementById('notifDropdown');
    const badge      = document.getElementById('notifBadge');
    const list       = document.getElementById('notifList');
    const markReadBtn= document.getElementById('notifMarkRead');
    let loaded = false;

    function updateBadge(count) {
        badge.textContent = count > 0 ? count : '';
        badge.dataset.count = count;
    }

    async function loadNotifications() {
        try {
            const res = await fetch('/api/notifications.php?limit=15', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (!data.success) return;

            updateBadge(data.unread_count);

            if (data.notifications.length === 0) {
                list.innerHTML = '<div class="notif-empty"><?= t('notif.empty') ?></div>';
                return;
            }

            list.innerHTML = data.notifications.map(n => {
                const cls = n.is_read ? 'notif-item' : 'notif-item unread';
                const href = n.link ? ` onclick="window.location='${n.link}'"` : '';
                return `<div class="${cls}"${href}>
                    <div class="notif-item__title">${escHtml(n.title)}</div>
                    <div class="notif-item__time">${escHtml(n.time_ago)}</div>
                </div>`;
            }).join('');

        } catch (err) {
            console.error('Failed to load notifications:', err);
        }
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    // Toggle dropdown
    bell.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = dropdown.classList.toggle('open');
        if (isOpen && !loaded) {
            loadNotifications();
            loaded = true;
        }
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#notifWrapper')) {
            dropdown.classList.remove('open');
        }
    });

    // Mark all as read
    markReadBtn.addEventListener('click', async (e) => {
        e.stopPropagation();
        try {
            await fetch('/api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ action: 'mark_read' })
            });
            updateBadge(0);
            list.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
        } catch (err) {
            console.error('Failed to mark as read:', err);
        }
    });

    // Poll every 60 seconds for new notifications
    setInterval(async () => {
        try {
            const res = await fetch('/api/notifications.php?limit=1', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (data.success) {
                updateBadge(data.unread_count);
                // If dropdown is open and count changed, reload
                if (dropdown.classList.contains('open')) {
                    loadNotifications();
                } else {
                    loaded = false; // Force reload next open
                }
            }
        } catch (_) {}
    }, 60000);
})();
<?php endif; ?>
</script>
