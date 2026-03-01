<?php
require_once __DIR__ . '/config/db.php';
$user = requireAuth();
$db = getDB();

// Get user stats
$commentCount = $db->prepare('SELECT COUNT(*) FROM comments WHERE user_id = ?');
$commentCount->execute([$user['id']]);
$commentCount = (int) $commentCount->fetchColumn();

$messageCount = $db->prepare('SELECT COUNT(*) FROM messages WHERE sender_id = ? AND is_thread_start = 1');
$messageCount->execute([$user['id']]);
$messageCount = (int) $messageCount->fetchColumn();

$unreadCount = getUnreadCount($user['id']);
$userTitles = getUserTitles($user['id']);
$guilds = getGuilds();
$backgrounds = backgroundOptions();
$showSetup = isset($_GET['setup']) && $user['personalization_step'] < 4 && $user['role'] !== 'admin';

$pageTitle = 'Cabinet — ' . SITE_NAME;
$pageDesc = 'Your personal space in the Archives.';
$extraCss = '
    .cab-container { max-width:800px; margin:2rem auto; padding:0 1.5rem; }
    .cab-header { text-align:center; margin-bottom:3rem; }
    .cab-avatar { font-size:4.5rem; display:block; margin-bottom:0.75rem; }
    .cab-name { font-family:var(--font-display); font-size:clamp(1.5rem,3vw,2rem); color:var(--text-primary); }
    .cab-role { font-family:var(--font-label); font-size:0.6rem; letter-spacing:0.3em; text-transform:uppercase; color:var(--text-dim); margin-top:0.3rem; }
    .cab-stats { display:flex; justify-content:center; gap:2.5rem; margin-top:1.25rem; }
    .cab-stat { text-align:center; }
    .cab-stat__num { font-family:var(--font-display); font-size:1.5rem; color:var(--text-primary); display:block; }
    .cab-stat__label { font-family:var(--font-label); font-size:0.6rem; letter-spacing:0.2em; text-transform:uppercase; color:var(--text-dim); }
    .cab-nav { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; margin-bottom:3rem; }
    .cab-nav-card { display:block; text-align:center; padding:1.75rem 1rem; background:rgba(12,12,16,0.6); border:1px solid var(--border-card); text-decoration:none; transition:all 0.3s ease; cursor:pointer; }
    .cab-nav-card:hover { border-color:rgba(160,160,170,0.15); transform:translateY(-2px); }
    .cab-nav-card__icon { font-size:1.8rem; display:block; margin-bottom:0.6rem; }
    .cab-nav-card__label { font-family:var(--font-label); font-size:0.65rem; letter-spacing:0.2em; text-transform:uppercase; color:var(--text-secondary); }
    .cab-section { display:none; margin-bottom:3rem; }
    .cab-section.active { display:block; }
    .cab-section__title { font-family:var(--font-display); font-size:1.4rem; color:var(--text-primary); margin-bottom:1.5rem; text-align:center; }
    /* Messages */
    .thread-list { list-style:none; }
    .thread-item { display:flex; align-items:flex-start; gap:1rem; padding:1.1rem; border-bottom:1px solid var(--border-card); cursor:pointer; transition:background 0.2s; }
    .thread-item:hover { background:rgba(160,160,170,0.03); }
    .thread-item.unread { border-left:2px solid var(--crimson); }
    .thread-item__avatar { font-size:1.6rem; flex-shrink:0; }
    .thread-item__content { flex:1; min-width:0; }
    .thread-item__subject { font-family:var(--font-body); color:var(--text-primary); font-size:1.1rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .thread-item__meta { font-family:var(--font-label); font-size:0.6rem; letter-spacing:0.15em; color:var(--text-dim); margin-top:0.3rem; }
    .thread-item__cat { font-size:0.9rem; }
    .thread-view { background:rgba(12,12,16,0.4); border:1px solid var(--border-card); }
    .thread-view__header { padding:1.5rem; border-bottom:1px solid var(--border-card); }
    .thread-msg { padding:1.25rem 1.5rem; border-bottom:1px solid rgba(30,29,38,0.5); }
    .thread-msg__sender { font-family:var(--font-label); font-size:0.65rem; letter-spacing:0.15em; color:var(--text-secondary); }
    .thread-msg__time { font-family:var(--font-mono); font-size:0.8rem; color:var(--text-dim); float:right; }
    .thread-msg__body { margin-top:0.5rem; color:var(--text-primary); line-height:1.75; font-size:1.05rem; }
    .thread-reply { padding:1.5rem; border-top:1px solid var(--border-card); }
    /* Category filter tabs */
    .cat-tabs { display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:1.5rem; }
    .cat-tab { background:none; border:1px solid var(--border-card); color:var(--text-dim); font-family:var(--font-label); font-size:0.6rem; letter-spacing:0.15em; padding:0.5em 1.1em; cursor:pointer; transition:all 0.3s; text-transform:uppercase; }
    .cat-tab:hover, .cat-tab.active { border-color:rgba(160,160,170,0.3); color:var(--text-secondary); }
    /* Setup overlay */
    .setup-overlay { position:fixed; inset:0; background:rgba(6,6,8,0.95); z-index:9000; display:flex; align-items:center; justify-content:center; }
    .setup-card { max-width:520px; width:90%; max-height:85vh; overflow-y:auto; padding:2.5rem; background:rgba(12,12,16,0.9); border:1px solid var(--border-card); }
    .setup-step-indicator { display:flex; gap:0.5rem; justify-content:center; margin-bottom:2rem; }
    .setup-dot { width:10px; height:10px; border-radius:50%; background:var(--border-card); transition:background 0.3s; }
    .setup-dot.active { background:var(--text-secondary); }
    .setup-dot.done { background:rgba(80,200,120,0.5); }
    /* Background picker */
    .bg-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(110px,1fr)); gap:0.75rem; max-height:350px; overflow-y:auto; }
    .bg-option { position:relative; aspect-ratio:16/10; background-size:cover; background-position:center; border:2px solid transparent; cursor:pointer; transition:all 0.3s; overflow:hidden; }
    .bg-option:hover { border-color:rgba(160,160,170,0.3); }
    .bg-option.selected { border-color:var(--text-primary); }
    .bg-option__name { position:absolute; bottom:0; left:0; right:0; padding:0.3rem 0.5rem; background:rgba(0,0,0,0.7); font-family:var(--font-label); font-size:0.48rem; letter-spacing:0.15em; text-transform:uppercase; color:var(--text-secondary); }
    /* Avatar picker */
    .avatar-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; max-width:320px; margin:0 auto; }
    .avatar-option { text-align:center; padding:1.1rem; border:1px solid var(--border-card); cursor:pointer; transition:all 0.3s; }
    .avatar-option:hover { border-color:rgba(160,160,170,0.3); }
    .avatar-option.selected { border-color:var(--text-primary); background:rgba(160,160,170,0.05); }
    .avatar-option__symbol { font-size:2.2rem; display:block; }
    .avatar-option__label { font-family:var(--font-label); font-size:0.52rem; letter-spacing:0.15em; text-transform:uppercase; color:var(--text-dim); margin-top:0.3rem; }
    /* Profile edit */
    .profile-section { display:none; }
    .profile-section.active { display:block; }
    .info-row { display:flex; justify-content:space-between; padding:0.85rem 0; border-bottom:1px solid var(--border-card); }
    .info-row__label { font-family:var(--font-label); font-size:0.65rem; letter-spacing:0.2em; text-transform:uppercase; color:var(--text-dim); }
    .info-row__value { color:var(--text-secondary); font-family:var(--font-body); font-size:1.05rem; }
    /* Visual creation request details */
    .vc-detail { padding:0.6rem 0; border-bottom:1px solid rgba(30,29,38,0.3); }
    .vc-detail__label { font-family:var(--font-label); font-size:0.58rem; letter-spacing:0.15em; color:var(--text-dim); text-transform:uppercase; }
    .vc-detail__value { color:var(--text-secondary); margin-top:0.2rem; font-size:1.05rem; }
    .status-badge { display:inline-block; padding:0.25em 0.7em; font-family:var(--font-label); font-size:0.52rem; letter-spacing:0.15em; text-transform:uppercase; border:1px solid; }
    .status-badge--pending { border-color:rgba(200,180,80,0.3); color:#c8b850; }
    .status-badge--in_progress { border-color:rgba(80,160,200,0.3); color:#50a0c8; }
    .status-badge--completed { border-color:rgba(80,200,120,0.3); color:#50c878; }
';
include __DIR__ . '/includes/header.php';
?>

    <div class="page-content">
      <div class="cab-container">

        <!-- HEADER -->
        <div class="cab-header">
          <span class="cab-avatar"><?= getAvatarSymbol($user['avatar']) ?></span>
          <div class="cab-name"><?= e($user['display_name'] ?? $user['username']) ?></div>
          <?php if (!empty($user['title_name'])): ?>
            <span class="title-badge" style="color:<?= e($user['title_color'] ?? '#9e9a92') ?>; border-color:<?= e($user['title_color'] ?? '#9e9a92') ?>;">
              <?= e($user['title_icon'] ?? '') ?> <?= e($user['title_name']) ?>
            </span>
          <?php endif; ?>
          <div class="cab-role"><?= e($user['role']) ?></div>
          <?php if ($user['bio']): ?>
            <p style="font-family:var(--font-body); color:var(--text-secondary); font-style:italic; margin-top:0.75rem; max-width:500px; margin-left:auto; margin-right:auto; font-size:1.1rem; line-height:1.7;">
              <?= e($user['bio']) ?>
            </p>
          <?php endif; ?>
          <div class="cab-stats">
            <div class="cab-stat"><span class="cab-stat__num"><?= $commentCount ?></span><span class="cab-stat__label">Comments</span></div>
            <div class="cab-stat"><span class="cab-stat__num"><?= $messageCount ?></span><span class="cab-stat__label">Messages</span></div>
            <div class="cab-stat"><span class="cab-stat__num"><?= date('M Y', strtotime($user['created_at'])) ?></span><span class="cab-stat__label">Joined</span></div>
          </div>
        </div>

        <!-- NAV CARDS -->
        <div class="cab-nav">
          <div class="cab-nav-card" data-section="messages">
            <span class="cab-nav-card__icon">✉</span>
            <span class="cab-nav-card__label">Messages<?php if ($unreadCount): ?> (<?= $unreadCount ?>)<?php endif; ?></span>
          </div>
          <a href="contact" class="cab-nav-card">
            <span class="cab-nav-card__icon">◇</span>
            <span class="cab-nav-card__label">Contact Andrey</span>
          </a>
          <a href="news" class="cab-nav-card">
            <span class="cab-nav-card__icon">☙</span>
            <span class="cab-nav-card__label">News</span>
          </a>
          <a href="/" class="cab-nav-card">
            <span class="cab-nav-card__icon">⌘</span>
            <span class="cab-nav-card__label">Houses</span>
          </a>
          <div class="cab-nav-card" data-section="profile">
            <span class="cab-nav-card__icon">✧</span>
            <span class="cab-nav-card__label">Edit Profile</span>
          </div>
        </div>

        <!-- MESSAGES SECTION -->
        <div class="cab-section" id="section-messages">
          <h2 class="cab-section__title">Messages</h2>
          <div class="cat-tabs" id="msgCatTabs">
            <button class="cat-tab active" data-cat="all">All</button>
            <button class="cat-tab" data-cat="review">☙ Review</button>
            <button class="cat-tab" data-cat="problem">⚠ Problem</button>
            <button class="cat-tab" data-cat="visual_creation">✦ Visual</button>
            <button class="cat-tab" data-cat="collaboration">◇ Collab</button>
            <button class="cat-tab" data-cat="other">◆ Other</button>
          </div>
          <div id="threadListContainer">
            <ul class="thread-list" id="threadList"></ul>
            <div id="threadEmpty" style="text-align:center; padding:2.5rem; color:var(--text-dim); font-style:italic; display:none; font-size:1.05rem;">
              No messages yet. <a href="contact" style="color:var(--text-secondary);">Contact Andrey</a> to start a conversation.
            </div>
          </div>
          <div id="threadViewContainer" style="display:none;">
            <button class="btn btn--small" id="backToThreads" style="margin-bottom:1rem;">← Back to Messages</button>
            <div class="thread-view" id="threadView"></div>
          </div>
        </div>

        <!-- PROFILE SECTION (hidden by default) -->
        <div class="cab-section" id="section-profile">
          <h2 class="cab-section__title">Edit Profile</h2>
          <div id="profileMessages"></div>
          <form id="profileForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
              <label class="form-label">Display Name</label>
              <input type="text" name="display_name" class="form-input" value="<?= e($user['display_name'] ?? '') ?>" maxlength="100">
            </div>

            <div class="form-group">
              <label class="form-label">Avatar</label>
              <div class="avatar-grid" id="avatarPicker">
                <?php foreach (avatarSymbols() as $key => $symbol): ?>
                  <div class="avatar-option <?= $user['avatar'] === $key ? 'selected' : '' ?>" data-avatar="<?= $key ?>">
                    <span class="avatar-option__symbol"><?= $symbol ?></span>
                    <span class="avatar-option__label"><?= $key ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
              <input type="hidden" name="avatar" id="avatarInput" value="<?= e($user['avatar']) ?>">
            </div>

            <div class="form-group">
              <label class="form-label">Bio</label>
              <textarea name="bio" class="form-textarea" maxlength="500" style="min-height:80px;"><?= e($user['bio'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Active Title</label>
              <select name="title_id" class="form-select">
                <option value="">None</option>
                <?php foreach ($userTitles as $t): ?>
                  <option value="<?= $t['id'] ?>" <?= $user['title_id'] == $t['id'] ? 'selected' : '' ?>><?= e($t['icon'] . ' ' . $t['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Discord</label>
              <input type="text" name="discord" class="form-input" value="<?= e($user['discord'] ?? '') ?>" placeholder="username#1234" maxlength="100">
            </div>

            <div class="form-group">
              <label class="form-label">In-Game ID (@handle)</label>
              <input type="text" name="game_id" class="form-input" value="<?= e($user['game_id'] ?? '') ?>" placeholder="@username" maxlength="100">
            </div>

            <div class="form-group">
              <label class="form-label">Guild</label>
              <select name="guild_id" class="form-select">
                <option value="">No Guild</option>
                <?php foreach ($guilds as $g): ?>
                  <option value="<?= $g['id'] ?>" <?= $user['guild_id'] == $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?> (<?= e($g['server']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Cabinet Background</label>
              <div class="bg-grid" id="bgPicker">
                <?php foreach ($backgrounds as $key => $bg): ?>
                  <div class="bg-option <?= $user['background'] === $key ? 'selected' : '' ?>" data-bg="<?= $key ?>"
                    style="<?= $bg['file'] !== 'none' ? "background-image:linear-gradient(rgba(0,0,0,0.3),rgba(0,0,0,0.5)),url('img/backgrounds/{$bg['file']}')" : 'background:var(--bg-card)' ?>">
                    <span class="bg-option__name"><?= e($bg['name']) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <div style="text-align:center; margin-top:2rem;">
              <button type="submit" class="btn btn--primary">Save Changes</button>
            </div>
          </form>

          <div class="card" style="margin-top:2rem;">
            <div class="info-row"><span class="info-row__label">Username</span><span class="info-row__value"><?= e($user['username']) ?></span></div>
            <div class="info-row"><span class="info-row__label">Email</span><span class="info-row__value"><?= e($user['email']) ?></span></div>
            <div class="info-row"><span class="info-row__label">Verified</span><span class="info-row__value"><?= $user['verified'] ? '✓ Yes' : '✗ No' ?></span></div>
            <?php if ($user['guild_name']): ?>
              <div class="info-row"><span class="info-row__label">Guild</span><span class="info-row__value"><?= e($user['guild_name']) ?> (<?= e($user['guild_server']) ?>)</span></div>
            <?php endif; ?>
            <?php if ($user['discord']): ?>
              <div class="info-row"><span class="info-row__label">Discord</span><span class="info-row__value"><?= e($user['discord']) ?></span></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- PERSONALIZATION SETUP OVERLAY -->
    <?php if ($showSetup): ?>
    <div class="setup-overlay" id="setupOverlay">
      <div class="setup-card">
        <div style="text-align:center; margin-bottom:1.5rem;">
          <div class="divider"><span>◆ — ◇ — ◆</span></div>
          <h2 style="font-family:var(--font-display); font-size:1.2rem; color:var(--text-primary); margin:0.75rem 0;">Make It Yours</h2>
          <p style="font-family:var(--font-body); color:var(--text-dim); font-size:0.85rem; font-style:italic;">Customize your presence in the Archives. You can change everything later.</p>
        </div>
        <div class="setup-step-indicator" id="setupDots">
          <div class="setup-dot active"></div><div class="setup-dot"></div><div class="setup-dot"></div><div class="setup-dot"></div>
        </div>
        <div id="setupMessages"></div>

        <!-- Step 1: Background -->
        <div class="setup-step active" id="setup-step-1">
          <h3 style="font-family:var(--font-label); font-size:0.6rem; letter-spacing:0.25em; text-transform:uppercase; color:var(--text-secondary); margin-bottom:1rem;">Choose a Background</h3>
          <div class="bg-grid" id="setupBgPicker">
            <?php foreach ($backgrounds as $key => $bg): ?>
              <div class="bg-option <?= $key === 'default' ? 'selected' : '' ?>" data-bg="<?= $key ?>"
                style="<?= $bg['file'] !== 'none' ? "background-image:linear-gradient(rgba(0,0,0,0.3),rgba(0,0,0,0.5)),url('img/backgrounds/{$bg['file']}')" : 'background:var(--bg-card)' ?>">
                <span class="bg-option__name"><?= e($bg['name']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Step 2: Avatar -->
        <div class="setup-step" id="setup-step-2" style="display:none;">
          <h3 style="font-family:var(--font-label); font-size:0.6rem; letter-spacing:0.25em; text-transform:uppercase; color:var(--text-secondary); margin-bottom:1rem;">Choose an Avatar</h3>
          <div class="avatar-grid" id="setupAvatarPicker">
            <?php foreach (avatarSymbols() as $key => $symbol): ?>
              <div class="avatar-option <?= $key === 'default' ? 'selected' : '' ?>" data-avatar="<?= $key ?>">
                <span class="avatar-option__symbol"><?= $symbol ?></span>
                <span class="avatar-option__label"><?= $key ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Step 3: Guild -->
        <div class="setup-step" id="setup-step-3" style="display:none;">
          <h3 style="font-family:var(--font-label); font-size:0.6rem; letter-spacing:0.25em; text-transform:uppercase; color:var(--text-secondary); margin-bottom:1rem;">Select Your Guild</h3>
          <select class="form-select" id="setupGuild">
            <option value="">No Guild</option>
            <?php foreach ($guilds as $g): ?>
              <option value="<?= $g['id'] ?>"><?= e($g['name']) ?> (<?= e($g['server']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Step 4: Game ID -->
        <div class="setup-step" id="setup-step-4" style="display:none;">
          <h3 style="font-family:var(--font-label); font-size:0.6rem; letter-spacing:0.25em; text-transform:uppercase; color:var(--text-secondary); margin-bottom:1rem;">Your In-Game ID</h3>
          <input type="text" class="form-input" id="setupGameId" placeholder="@username" maxlength="100">
          <p style="font-family:var(--font-body); color:var(--text-dim); font-size:0.8rem; margin-top:0.5rem; font-style:italic;">Your ESO @handle so others can find you in-game.</p>
        </div>

        <div style="display:flex; justify-content:space-between; margin-top:2rem;">
          <button class="btn btn--small" id="setupSkip" style="opacity:0.6;">Skip All</button>
          <button class="btn btn--primary btn--small" id="setupNext">Next →</button>
        </div>
      </div>
    </div>
    <?php endif; ?>

<?php
$csrfToken = csrfToken();
$extraJs = '<script>
document.addEventListener("DOMContentLoaded", () => {
  const csrf = "' . $csrfToken . '";

  // --- Section navigation ---
  document.querySelectorAll(".cab-nav-card[data-section]").forEach(card => {
    card.addEventListener("click", () => {
      document.querySelectorAll(".cab-section").forEach(s => s.classList.remove("active"));
      const sec = document.getElementById("section-" + card.dataset.section);
      if (sec) { sec.classList.add("active"); }
      if (card.dataset.section === "messages") loadThreads();
    });
  });

  // --- Avatar picker ---
  document.querySelectorAll("#avatarPicker .avatar-option, #setupAvatarPicker .avatar-option").forEach(opt => {
    opt.addEventListener("click", () => {
      opt.closest(".avatar-grid").querySelectorAll(".avatar-option").forEach(o => o.classList.remove("selected"));
      opt.classList.add("selected");
      const inp = document.getElementById("avatarInput");
      if (inp) inp.value = opt.dataset.avatar;
    });
  });

  // --- Background picker ---
  document.querySelectorAll("#bgPicker .bg-option, #setupBgPicker .bg-option").forEach(opt => {
    opt.addEventListener("click", () => {
      opt.closest(".bg-grid").querySelectorAll(".bg-option").forEach(o => o.classList.remove("selected"));
      opt.classList.add("selected");
    });
  });

  // --- Profile form ---
  document.getElementById("profileForm")?.addEventListener("submit", async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    // Add selected background
    const selBg = document.querySelector("#bgPicker .bg-option.selected");
    if (selBg) {
      // First save background
      const bgFd = new FormData();
      bgFd.append("csrf_token", csrf);
      bgFd.append("action", "update_background");
      bgFd.append("background", selBg.dataset.bg);
      await fetch("api/profile.php", { method: "POST", body: bgFd, headers: { "X-Requested-With": "XMLHttpRequest" } });
    }
    try {
      const res = await fetch("api/profile.php", { method: "POST", body: fd, headers: { "X-Requested-With": "XMLHttpRequest" } });
      const data = await res.json();
      const msg = document.getElementById("profileMessages");
      msg.innerHTML = `<div class="alert alert--${data.success ? "success" : "error"}">${data.message || data.error}</div>`;
      if (data.success) setTimeout(() => location.reload(), 1000);
    } catch (err) {
      document.getElementById("profileMessages").innerHTML = "<div class=\"alert alert--error\">Connection error.</div>";
    }
  });

  // --- Messages system ---
  let currentCat = "all";
  let currentThreadId = null;

  document.querySelectorAll("#msgCatTabs .cat-tab").forEach(tab => {
    tab.addEventListener("click", () => {
      document.querySelectorAll("#msgCatTabs .cat-tab").forEach(t => t.classList.remove("active"));
      tab.classList.add("active");
      currentCat = tab.dataset.cat;
      loadThreads();
    });
  });

  async function loadThreads() {
    const url = "api/message.php?action=threads&category=" + currentCat;
    try {
      const res = await fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } });
      const data = await res.json();
      const list = document.getElementById("threadList");
      const empty = document.getElementById("threadEmpty");
      list.innerHTML = "";
      if (!data.threads || data.threads.length === 0) {
        empty.style.display = "block";
        return;
      }
      empty.style.display = "none";
      data.threads.forEach(t => {
        const li = document.createElement("li");
        li.className = "thread-item" + (t.unread_count > 0 ? " unread" : "");
        li.innerHTML = `
          <span class="thread-item__cat">${catIcon(t.category)}</span>
          <div class="thread-item__content">
            <div class="thread-item__subject">${esc(t.subject)}</div>
            <div class="thread-item__meta">${esc(t.sender_name || "Unknown")} · ${timeAgo(t.last_activity)}${t.unread_count > 0 ? " · <strong>" + t.unread_count + " new</strong>" : ""}</div>
          </div>
        `;
        li.addEventListener("click", () => openThread(t.id));
        list.appendChild(li);
      });
    } catch (err) { console.error(err); }
  }

  async function openThread(threadId) {
    currentThreadId = threadId;
    document.getElementById("threadListContainer").style.display = "none";
    document.getElementById("threadViewContainer").style.display = "block";
    try {
      const res = await fetch("api/message.php?action=thread&thread_id=" + threadId, { headers: { "X-Requested-With": "XMLHttpRequest" } });
      const data = await res.json();
      const view = document.getElementById("threadView");
      let html = `<div class="thread-view__header">
        <div style="font-family:var(--font-label);font-size:0.5rem;letter-spacing:0.2em;text-transform:uppercase;color:var(--text-dim);">${catIcon(data.thread.category)} ${esc(data.thread.category.replace("_"," "))}</div>
        <h3 style="font-family:var(--font-display);font-size:1.1rem;color:var(--text-primary);margin:0.5rem 0;">${esc(data.thread.subject)}</h3>
      </div>`;

      // Visual request details
      if (data.visual_request) {
        const vr = data.visual_request;
        html += `<div style="padding:1rem 1.5rem;background:rgba(18,19,26,0.5);border-bottom:1px solid var(--border-card);">
          <div style="font-family:var(--font-label);font-size:0.5rem;letter-spacing:0.2em;color:var(--text-dim);margin-bottom:0.75rem;text-transform:uppercase;">Visual Request Details</div>
          <div class="vc-detail"><span class="vc-detail__label">Server</span><div class="vc-detail__value">${esc(vr.server)}</div></div>
          <div class="vc-detail"><span class="vc-detail__label">Handle</span><div class="vc-detail__value">${esc(vr.game_handle)}</div></div>
          <div class="vc-detail"><span class="vc-detail__label">House</span><div class="vc-detail__value">${esc(vr.house_name)}</div></div>
          <div class="vc-detail"><span class="vc-detail__label">Status</span><div class="vc-detail__value"><span class="status-badge status-badge--${vr.status}">${vr.status}</span></div></div>
        </div>`;
      }

      // Messages
      data.messages.forEach(m => {
        html += `<div class="thread-msg">
          <span class="thread-msg__time">${timeAgo(m.created_at)}</span>
          <div class="thread-msg__sender">${avatarSymbol(m.avatar)} ${esc(m.display_name || m.username)}</div>
          <div class="thread-msg__body">${m.content}</div>
        </div>`;
      });

      // Reply form
      html += `<div class="thread-reply">
        <div class="form-group">
          <label class="form-label">Reply</label>
          <textarea class="form-textarea" id="replyContent" style="min-height:80px;" placeholder="Write your reply..."></textarea>
        </div>
        <button class="btn btn--primary btn--small" id="sendReply">Send Reply</button>
      </div>`;

      view.innerHTML = html;

      document.getElementById("sendReply")?.addEventListener("click", async () => {
        const content = document.getElementById("replyContent").value.trim();
        if (!content) return;
        const fd = new FormData();
        fd.append("action", "reply");
        fd.append("thread_id", threadId);
        fd.append("content", content);
        fd.append("csrf_token", csrf);
        const r = await fetch("api/message.php", { method: "POST", body: fd, headers: { "X-Requested-With": "XMLHttpRequest" } });
        const d = await r.json();
        if (d.success) openThread(threadId); // Refresh
      });
    } catch (err) { console.error(err); }
  }

  document.getElementById("backToThreads")?.addEventListener("click", () => {
    document.getElementById("threadViewContainer").style.display = "none";
    document.getElementById("threadListContainer").style.display = "block";
    loadThreads();
  });

  function catIcon(cat) {
    const icons = { review:"☙", problem:"⚠", visual_creation:"✦", collaboration:"◇", other:"◆" };
    return icons[cat] || "◆";
  }
  function avatarSymbol(key) {
    const s = { default:"◆", crimson:"✠", ayleid:"⌘", wyrd:"❧", sheoth:"✦", scroll:"☙", star:"✧", crown:"♛" };
    return s[key] || "◆";
  }
  function esc(str) { if (!str) return ""; const d = document.createElement("div"); d.textContent = str; return d.innerHTML; }
  function timeAgo(dt) {
    if (!dt) return "";
    const diff = (Date.now() - new Date(dt + "Z").getTime()) / 1000;
    if (diff < 60) return "just now";
    if (diff < 3600) return Math.floor(diff/60) + "m ago";
    if (diff < 86400) return Math.floor(diff/3600) + "h ago";
    if (diff < 2592000) return Math.floor(diff/86400) + "d ago";
    return new Date(dt).toLocaleDateString();
  }

  // --- Personalization Setup ---
  const setupOverlay = document.getElementById("setupOverlay");
  if (setupOverlay) {
    let step = 1;
    const dots = document.querySelectorAll(".setup-dot");
    const selections = { background: "default", avatar: "default", guild_id: "", game_id: "" };

    function updateSetupDots() {
      dots.forEach((d, i) => {
        d.classList.toggle("active", i + 1 === step);
        d.classList.toggle("done", i + 1 < step);
      });
    }

    function showSetupStep(n) {
      document.querySelectorAll(".setup-step").forEach(s => s.style.display = "none");
      const el = document.getElementById("setup-step-" + n);
      if (el) el.style.display = "block";
      document.getElementById("setupNext").textContent = n >= 4 ? "Finish ✓" : "Next →";
      updateSetupDots();
    }

    // Setup pickers
    document.querySelectorAll("#setupBgPicker .bg-option").forEach(opt => {
      opt.addEventListener("click", () => {
        document.querySelectorAll("#setupBgPicker .bg-option").forEach(o => o.classList.remove("selected"));
        opt.classList.add("selected");
        selections.background = opt.dataset.bg;
      });
    });
    document.querySelectorAll("#setupAvatarPicker .avatar-option").forEach(opt => {
      opt.addEventListener("click", () => {
        document.querySelectorAll("#setupAvatarPicker .avatar-option").forEach(o => o.classList.remove("selected"));
        opt.classList.add("selected");
        selections.avatar = opt.dataset.avatar;
      });
    });

    document.getElementById("setupNext").addEventListener("click", async () => {
      // Save current step
      const fd = new FormData();
      fd.append("csrf_token", csrf);

      if (step === 1) {
        fd.append("action", "update_background");
        fd.append("background", selections.background);
        await fetch("api/profile.php", { method: "POST", body: fd, headers: { "X-Requested-With": "XMLHttpRequest" } });
      } else if (step === 2) {
        fd.append("action", "update_profile");
        fd.append("avatar", selections.avatar);
        fd.append("display_name", "' . addslashes(e($user['display_name'] ?? $user['username'])) . '");
        await fetch("api/profile.php", { method: "POST", body: fd, headers: { "X-Requested-With": "XMLHttpRequest" } });
      } else if (step === 3) {
        selections.guild_id = document.getElementById("setupGuild").value;
      } else if (step === 4) {
        selections.game_id = document.getElementById("setupGameId").value;
        // Final save
        fd.append("action", "update_profile");
        fd.append("avatar", selections.avatar);
        fd.append("guild_id", selections.guild_id);
        fd.append("game_id", selections.game_id);
        fd.append("display_name", "' . addslashes(e($user['display_name'] ?? $user['username'])) . '");
        await fetch("api/profile.php", { method: "POST", body: fd, headers: { "X-Requested-With": "XMLHttpRequest" } });
      }

      // Mark step complete
      const stepFd = new FormData();
      stepFd.append("csrf_token", csrf);
      stepFd.append("action", "complete_setup_step");
      stepFd.append("step", step);
      await fetch("api/profile.php", { method: "POST", body: stepFd, headers: { "X-Requested-With": "XMLHttpRequest" } });

      if (step >= 4) {
        setupOverlay.style.display = "none";
        location.reload();
        return;
      }
      step++;
      showSetupStep(step);
    });

    document.getElementById("setupSkip").addEventListener("click", async () => {
      const fd = new FormData();
      fd.append("csrf_token", csrf);
      fd.append("action", "skip_setup");
      await fetch("api/profile.php", { method: "POST", body: fd, headers: { "X-Requested-With": "XMLHttpRequest" } });
      setupOverlay.style.display = "none";
    });

    showSetupStep(1);
  }
});
</script>';
include __DIR__ . '/includes/footer.php';
?>
