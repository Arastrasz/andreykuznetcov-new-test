<?php
require_once __DIR__ . '/config/db.php';
$user = requireAdmin();
$db = getDB();

$section = $_GET['section'] ?? 'overview';
$pageTitle = 'Admin Panel — ' . SITE_NAME;
$pageAccent = 'rgba(224,96,122,0.4)';
$extraCss = '
    .admin-container { max-width:900px; margin:2rem auto; padding:0 1.5rem; }
    .admin-nav { display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:2rem; border-bottom:1px solid var(--border-card); padding-bottom:1rem; }
    .admin-nav a { font-family:var(--font-label); font-size:0.62rem; letter-spacing:0.18em; text-transform:uppercase;
      color:var(--text-dim); text-decoration:none; padding:0.5em 1.1em; border:1px solid transparent; transition:all 0.3s; }
    .admin-nav a:hover, .admin-nav a.active { color:var(--text-secondary); border-color:var(--border-card); }
    .admin-stat { display:inline-block; padding:1.5rem; background:rgba(12,12,16,0.6); border:1px solid var(--border-card); text-align:center; min-width:130px; }
    .admin-stat__num { font-family:var(--font-display); font-size:1.6rem; color:var(--text-primary); display:block; }
    .admin-stat__label { font-family:var(--font-label); font-size:0.58rem; letter-spacing:0.2em; text-transform:uppercase; color:var(--text-dim); }
    .admin-table { width:100%; border-collapse:collapse; }
    .admin-table th { font-family:var(--font-label); font-size:0.58rem; letter-spacing:0.18em; text-transform:uppercase; color:var(--text-dim); text-align:left; padding:0.85rem; border-bottom:1px solid var(--border-card); }
    .admin-table td { font-family:var(--font-body); font-size:1rem; color:var(--text-secondary); padding:0.85rem; border-bottom:1px solid rgba(30,29,38,0.3); }
    .admin-table tr:hover td { background:rgba(160,160,170,0.02); }
    .admin-table tr { cursor:pointer; }
    .msg-unread { font-weight:bold; color:var(--text-primary); }
    /* Inline thread viewer */
    .thread-panel { display:none; margin-top:1.5rem; background:rgba(12,12,16,0.5); border:1px solid var(--border-card); }
    .thread-panel.active { display:block; }
    .thread-panel__header { padding:1.25rem 1.5rem; border-bottom:1px solid var(--border-card); display:flex; justify-content:space-between; align-items:center; }
    .thread-panel__subject { font-family:var(--font-display); font-size:1.2rem; color:var(--text-primary); }
    .thread-panel__close { background:none; border:1px solid var(--border-card); color:var(--text-dim); padding:0.3rem 0.8rem; cursor:pointer; font-size:0.9rem; transition:all 0.3s; }
    .thread-panel__close:hover { color:var(--text-secondary); border-color:var(--text-dim); }
    .thread-panel__messages { max-height:400px; overflow-y:auto; }
    .thread-msg { padding:1.1rem 1.5rem; border-bottom:1px solid rgba(30,29,38,0.4); }
    .thread-msg__sender { font-family:var(--font-label); font-size:0.65rem; letter-spacing:0.15em; color:var(--text-secondary); }
    .thread-msg__time { font-family:var(--font-mono); font-size:0.8rem; color:var(--text-dim); float:right; }
    .thread-msg__body { margin-top:0.5rem; color:var(--text-primary); line-height:1.75; font-size:1.05rem; }
    .thread-msg--admin { background:rgba(160,160,170,0.02); }
    .thread-reply { padding:1.5rem; border-top:1px solid var(--border-card); }
    /* Visual request details */
    .vc-detail { padding:0.5rem 0; border-bottom:1px solid rgba(30,29,38,0.25); }
    .vc-detail__label { font-family:var(--font-label); font-size:0.58rem; letter-spacing:0.15em; color:var(--text-dim); text-transform:uppercase; }
    .vc-detail__value { color:var(--text-secondary); margin-top:0.15rem; font-size:1.05rem; }
    .cat-tabs { display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:1.5rem; }
    .cat-tab { background:none; border:1px solid var(--border-card); color:var(--text-dim); font-family:var(--font-label); font-size:0.6rem; letter-spacing:0.15em; padding:0.5em 1.1em; cursor:pointer; transition:all 0.3s; text-transform:uppercase; text-decoration:none; display:inline-block; }
    .cat-tab:hover, .cat-tab.active { border-color:rgba(160,160,170,0.3); color:var(--text-secondary); }
';
include __DIR__ . '/includes/header.php';

// Stats
$totalUsers = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalMessages = (int) $db->query('SELECT COUNT(*) FROM messages WHERE is_thread_start = 1')->fetchColumn();
$unreadStmt = $db->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0');
$unreadStmt->execute([ADMIN_USER_ID]);
$unreadMessages = (int) $unreadStmt->fetchColumn();
$totalNews = (int) $db->query('SELECT COUNT(*) FROM news')->fetchColumn();
$totalComments = (int) $db->query('SELECT COUNT(*) FROM comments')->fetchColumn();
?>

    <div class="page-content">
      <div class="admin-container">
        <div style="text-align:center; margin-bottom:2rem;">
          <div class="divider"><span>✠ — ◆ — ✠</span></div>
          <h1 class="section-title" style="font-size:clamp(1.2rem,2.5vw,1.6rem);">Admin Panel</h1>
        </div>

        <nav class="admin-nav">
          <a href="admin" class="<?= $section === 'overview' ? 'active' : '' ?>">Overview</a>
          <a href="admin?section=messages" class="<?= $section === 'messages' ? 'active' : '' ?>">Messages<?php if ($unreadMessages): ?> (<?= $unreadMessages ?>)<?php endif; ?></a>
          <a href="admin?section=news" class="<?= $section === 'news' ? 'active' : '' ?>">News</a>
          <a href="admin?section=users" class="<?= $section === 'users' ? 'active' : '' ?>">Users</a>
        </nav>

        <?php if ($section === 'overview'): ?>
          <div style="display:flex; flex-wrap:wrap; gap:1rem; margin-bottom:2rem;">
            <div class="admin-stat"><span class="admin-stat__num"><?= $totalUsers ?></span><span class="admin-stat__label">Users</span></div>
            <div class="admin-stat"><span class="admin-stat__num"><?= $unreadMessages ?></span><span class="admin-stat__label">Unread</span></div>
            <div class="admin-stat"><span class="admin-stat__num"><?= $totalNews ?></span><span class="admin-stat__label">News Posts</span></div>
            <div class="admin-stat"><span class="admin-stat__num"><?= $totalComments ?></span><span class="admin-stat__label">Comments</span></div>
            <div class="admin-stat"><span class="admin-stat__num"><?= $totalMessages ?></span><span class="admin-stat__label">Threads</span></div>
          </div>

        <?php elseif ($section === 'messages'): ?>
          <?php
          $cat = $_GET['cat'] ?? null;
          $where = 'WHERE m.is_thread_start = 1';
          $params = [];
          if ($cat) { $where .= ' AND m.category = ?'; $params[] = $cat; }
          $stmt = $db->prepare("
              SELECT m.*, u.display_name as sender_name, u.username as sender_username, u.avatar as sender_avatar,
                (SELECT COUNT(*) FROM messages m2 WHERE m2.thread_id = m.id AND m2.receiver_id = ? AND m2.is_read = 0) as unread_count,
                (SELECT MAX(m3.created_at) FROM messages m3 WHERE m3.thread_id = m.id) as last_activity
              FROM messages m
              JOIN users u ON m.sender_id = u.id
              {$where}
              ORDER BY last_activity DESC
              LIMIT 50
          ");
          $stmt->execute(array_merge([ADMIN_USER_ID], $params));
          $threads = $stmt->fetchAll();
          ?>
          <div class="cat-tabs">
            <a href="admin?section=messages" class="cat-tab <?= !$cat ? 'active' : '' ?>">All</a>
            <?php foreach (['review','problem','visual_creation','collaboration','other'] as $c): ?>
              <a href="admin?section=messages&cat=<?= $c ?>" class="cat-tab <?= $cat === $c ? 'active' : '' ?>"><?= getCategoryIcon($c) ?> <?= ucfirst(str_replace('_',' ',$c)) ?></a>
            <?php endforeach; ?>
          </div>

          <?php if (empty($threads)): ?>
            <p style="text-align:center; color:var(--text-dim); font-style:italic; padding:2rem; font-size:1.05rem;">No messages in this category.</p>
          <?php else: ?>
            <table class="admin-table" id="threadTable">
              <thead><tr><th>Cat</th><th>Subject</th><th>From</th><th>Last Activity</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($threads as $t): ?>
                  <tr data-thread-id="<?= $t['id'] ?>" onclick="openThread(<?= $t['id'] ?>)" class="<?= $t['unread_count'] > 0 ? 'msg-unread' : '' ?>">
                    <td><?= getCategoryIcon($t['category']) ?></td>
                    <td><?= e($t['subject']) ?></td>
                    <td><?= getAvatarSymbol($t['sender_avatar'] ?? 'default') ?> <?= e($t['sender_name'] ?? $t['sender_username']) ?></td>
                    <td><?= timeAgo($t['last_activity']) ?></td>
                    <td><?= $t['unread_count'] > 0 ? '<strong style="color:#e0607a;">' . $t['unread_count'] . ' new</strong>' : 'Read' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>

          <!-- Inline thread viewer panel -->
          <div class="thread-panel" id="threadPanel">
            <div class="thread-panel__header">
              <span class="thread-panel__subject" id="threadSubject"></span>
              <button class="thread-panel__close" onclick="closeThread()">✕ Close</button>
            </div>
            <div id="threadVcDetails"></div>
            <div class="thread-panel__messages" id="threadMessages"></div>
            <div class="thread-reply">
              <div id="replyMessages"></div>
              <div class="form-group" style="margin-bottom:0.75rem;">
                <label class="form-label">Reply</label>
                <div class="rte-toolbar" id="adminRteToolbar">
                  <button type="button" class="rte-btn" data-cmd="bold"><strong>B</strong></button>
                  <button type="button" class="rte-btn" data-cmd="italic"><em>I</em></button>
                  <button type="button" class="rte-btn" data-cmd="underline"><u>U</u></button>
                  <button type="button" class="rte-btn" data-cmd="insertUnorderedList">• List</button>
                </div>
                <div class="rte-editor" id="adminReplyEditor" contenteditable="true" style="min-height:100px;"></div>
              </div>
              <input type="hidden" id="replyThreadId" value="">
              <button class="btn btn--primary" onclick="sendReply()">Send Reply →</button>
            </div>
          </div>

        <?php elseif ($section === 'news'): ?>
          <?php if (isset($_GET['new']) || isset($_GET['edit_id'])): ?>
            <?php
            $editPost = null;
            if (isset($_GET['edit_id'])) {
                $stmt = $db->prepare('SELECT * FROM news WHERE id = ?');
                $stmt->execute([(int) $_GET['edit_id']]);
                $editPost = $stmt->fetch();
            }
            ?>
            <h3 style="font-family:var(--font-label); font-size:0.65rem; letter-spacing:0.25em; margin-bottom:1.5rem; text-transform:uppercase; color:var(--text-secondary);">
              <?= $editPost ? 'Edit Post' : 'New Post' ?>
            </h3>
            <div id="newsFormMessages"></div>
            <form id="adminNewsForm">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="save_news">
              <?php if ($editPost): ?><input type="hidden" name="news_id" value="<?= $editPost['id'] ?>"><?php endif; ?>
              <div class="form-group">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-input" value="<?= e($editPost['title'] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Excerpt</label>
                <textarea name="excerpt" class="form-textarea" style="min-height:60px;"><?= e($editPost['excerpt'] ?? '') ?></textarea>
              </div>
              <div class="form-group">
                <label class="form-label">Hero Image Path</label>
                <input type="text" name="image" class="form-input" value="<?= e($editPost['image'] ?? '') ?>" placeholder="img/filename.jpg">
              </div>
              <div class="form-group">
                <label class="form-label">Content</label>
                <div class="rte-toolbar">
                  <button type="button" class="rte-btn" data-cmd="bold"><strong>B</strong></button>
                  <button type="button" class="rte-btn" data-cmd="italic"><em>I</em></button>
                  <button type="button" class="rte-btn" data-cmd="underline"><u>U</u></button>
                  <button type="button" class="rte-btn" data-cmd="insertUnorderedList">• List</button>
                  <button type="button" class="rte-btn" data-cmd="formatBlock" data-val="h2">H2</button>
                  <button type="button" class="rte-btn" data-cmd="formatBlock" data-val="h3">H3</button>
                  <button type="button" class="rte-btn" data-cmd="formatBlock" data-val="blockquote">Quote</button>
                  <button type="button" class="rte-btn" data-cmd="createLink">Link</button>
                </div>
                <div class="rte-editor" id="adminNewsEditor" contenteditable="true" style="min-height:250px;"><?= $editPost['content'] ?? '' ?></div>
                <input type="hidden" name="content" id="adminNewsContent">
              </div>
              <div class="form-group">
                <label class="form-label" style="display:flex; align-items:center; gap:0.5rem;">
                  <input type="checkbox" name="published" <?= ($editPost['published'] ?? 1) ? 'checked' : '' ?> style="accent-color:var(--text-secondary);">
                  Published
                </label>
              </div>
              <div style="display:flex; gap:1rem;">
                <a href="admin?section=news" class="btn">Cancel</a>
                <button type="submit" class="btn btn--primary">Save</button>
              </div>
            </form>
          <?php else: ?>
            <div style="margin-bottom:1rem;">
              <a href="admin?section=news&new=1" class="btn btn--primary btn--small">+ New Post</a>
            </div>
            <?php $allNews = $db->query('SELECT * FROM news ORDER BY created_at DESC')->fetchAll(); ?>
            <table class="admin-table">
              <thead><tr><th>Title</th><th>Published</th><th>Created</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($allNews as $n): ?>
                  <tr onclick="event.stopPropagation();" style="cursor:default;">
                    <td><?= e($n['title']) ?></td>
                    <td><?= $n['published'] ? '✓' : '—' ?></td>
                    <td><?= date('M j, Y', strtotime($n['created_at'])) ?></td>
                    <td>
                      <a href="admin?section=news&edit_id=<?= $n['id'] ?>" style="color:var(--text-secondary); text-decoration:none; margin-right:0.5rem;">Edit</a>
                      <a href="news?slug=<?= e($n['slug']) ?>" style="color:var(--text-dim); text-decoration:none;">View</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>

        <?php elseif ($section === 'users'): ?>
          <?php
          $users = $db->query('
              SELECT u.*, t.name as title_name, g.name as guild_name,
                (SELECT COUNT(*) FROM comments WHERE user_id = u.id) as comment_count
              FROM users u
              LEFT JOIN titles t ON u.title_id = t.id
              LEFT JOIN guilds g ON u.guild_id = g.id
              ORDER BY u.created_at DESC
          ')->fetchAll();
          ?>
          <table class="admin-table">
            <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Title</th><th>Guild</th><th>Comments</th><th>Joined</th></tr></thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr onclick="event.stopPropagation();" style="cursor:default;">
                  <td><?= getAvatarSymbol($u['avatar']) ?> <?= e($u['display_name'] ?? $u['username']) ?></td>
                  <td style="font-size:0.85rem;"><?= e($u['email']) ?></td>
                  <td><?= e($u['role']) ?></td>
                  <td><?= e($u['title_name'] ?? '—') ?></td>
                  <td><?= e($u['guild_name'] ?? '—') ?></td>
                  <td><?= $u['comment_count'] ?></td>
                  <td><?= date('M j', strtotime($u['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

      </div>
    </div>

<?php
$csrfToken = csrfToken();
$extraJs = '<script>
document.addEventListener("DOMContentLoaded", () => {
  // Rich text buttons (shared for all editors on page)
  document.querySelectorAll(".rte-toolbar .rte-btn").forEach(btn => {
    btn.addEventListener("click", e => {
      e.preventDefault();
      const cmd = btn.dataset.cmd;
      if (cmd === "createLink") {
        const url = prompt("Enter URL:");
        if (url) document.execCommand(cmd, false, url);
      } else if (cmd === "formatBlock" && btn.dataset.val) {
        document.execCommand(cmd, false, btn.dataset.val);
      } else {
        document.execCommand(cmd, false, null);
      }
    });
  });

  // Admin news form
  document.getElementById("adminNewsForm")?.addEventListener("submit", async e => {
    e.preventDefault();
    const editor = document.getElementById("adminNewsEditor");
    if (editor) document.getElementById("adminNewsContent").value = editor.innerHTML;
    const fd = new FormData(e.target);
    try {
      const res = await fetch("api/admin.php", { method: "POST", body: fd, headers: { "X-Requested-With": "XMLHttpRequest" } });
      const data = await res.json();
      const msg = document.getElementById("newsFormMessages");
      if (data.success) {
        msg.innerHTML = "<div class=\"alert alert--success\">" + data.message + "</div>";
        setTimeout(() => location.href = "admin?section=news", 1000);
      } else {
        msg.innerHTML = `<div class="alert alert--error">${data.error}</div>`;
      }
    } catch (err) {
      document.getElementById("newsFormMessages").innerHTML = "<div class=\"alert alert--error\">Connection error.</div>";
    }
  });
});

// --- Inline thread viewer ---
let currentThreadId = null;

async function openThread(threadId) {
  currentThreadId = threadId;
  const panel = document.getElementById("threadPanel");
  const msgBox = document.getElementById("threadMessages");
  const vcBox = document.getElementById("threadVcDetails");
  const subjectEl = document.getElementById("threadSubject");

  // Show panel, scroll to it
  panel.classList.add("active");
  panel.scrollIntoView({ behavior: "smooth", block: "start" });

  // Highlight selected row
  document.querySelectorAll("#threadTable tr").forEach(r => r.style.background = "");
  const row = document.querySelector(`#threadTable tr[data-thread-id="${threadId}"]`);
  if (row) {
    row.style.background = "rgba(160,160,170,0.04)";
    row.classList.remove("msg-unread");
    const statusCell = row.querySelector("td:last-child");
    if (statusCell) statusCell.textContent = "Read";
  }

  // Fetch thread data
  try {
    const fd = new FormData();
    fd.append("action", "thread");
    fd.append("thread_id", threadId);
    fd.append("csrf_token", "' . $csrfToken . '");
    const res = await fetch("api/message.php", { method: "POST", body: fd, headers: { "X-Requested-With": "XMLHttpRequest" } });
    const data = await res.json();

    if (!data.success) { msgBox.innerHTML = "<p style=\"padding:1rem;color:#e08080;\">Error loading thread.</p>"; return; }

    subjectEl.textContent = data.thread.subject || "Message";
    document.getElementById("replyThreadId").value = threadId;

    // Visual request details
    vcBox.innerHTML = "";
    if (data.visual_request) {
      const vr = data.visual_request;
      let vcHtml = "<div style=\"padding:1rem 1.5rem; background:rgba(160,160,170,0.02); border-bottom:1px solid var(--border-card);\">";
      vcHtml += "<div style=\"font-family:var(--font-label); font-size:0.6rem; letter-spacing:0.2em; text-transform:uppercase; color:var(--text-secondary); margin-bottom:0.75rem;\">✦ Visual Creation Details</div>";
      if (vr.server) vcHtml += `<div class="vc-detail"><span class="vc-detail__label">Server</span><div class="vc-detail__value">${vr.server}</div></div>`;
      if (vr.game_handle) vcHtml += `<div class="vc-detail"><span class="vc-detail__label">Handle</span><div class="vc-detail__value">${vr.game_handle}</div></div>`;
      if (vr.house_name) vcHtml += `<div class="vc-detail"><span class="vc-detail__label">House</span><div class="vc-detail__value">${vr.house_name}</div></div>`;
      if (vr.status) vcHtml += `<div class="vc-detail"><span class="vc-detail__label">Status</span><div class="vc-detail__value"><span class="status-badge status-badge--${vr.status}">${vr.status.replace("_"," ")}</span></div></div>`;
      vcHtml += "</div>";
      vcBox.innerHTML = vcHtml;
    }

    // Messages
    let html = "";
    data.messages.forEach(m => {
      const isAdmin = m.sender_id == ' . ADMIN_USER_ID . ';
      html += `<div class="thread-msg ${isAdmin ? "thread-msg--admin" : ""}">
        <span class="thread-msg__sender">${isAdmin ? "✠ You (Admin)" : m.sender_name || "User"}</span>
        <span class="thread-msg__time">${new Date(m.created_at).toLocaleString()}</span>
        <div class="thread-msg__body">${m.content}</div>
      </div>`;
    });
    msgBox.innerHTML = html;

    // Scroll messages to bottom
    msgBox.scrollTop = msgBox.scrollHeight;

  } catch (err) {
    msgBox.innerHTML = "<p style=\"padding:1rem;color:#e08080;\">Connection error.</p>";
  }
}

function closeThread() {
  document.getElementById("threadPanel").classList.remove("active");
  document.querySelectorAll("#threadTable tr").forEach(r => r.style.background = "");
  currentThreadId = null;
}

async function sendReply() {
  const editor = document.getElementById("adminReplyEditor");
  const content = editor.innerHTML.trim();
  const threadId = document.getElementById("replyThreadId").value;
  const msgBox = document.getElementById("replyMessages");

  if (!content || content === "<br>") { msgBox.innerHTML = "<div class=\"alert alert--error\">Please write a reply.</div>"; return; }

  const fd = new FormData();
  fd.append("action", "reply");
  fd.append("thread_id", threadId);
  fd.append("content", content);
  fd.append("csrf_token", "' . $csrfToken . '");

  try {
    const res = await fetch("api/message.php", { method: "POST", body: fd, headers: { "X-Requested-With": "XMLHttpRequest" } });
    const data = await res.json();
    if (data.success) {
      editor.innerHTML = "";
      msgBox.innerHTML = "<div class=\"alert alert--success\">Reply sent.</div>";
      setTimeout(() => { msgBox.innerHTML = ""; openThread(threadId); }, 800);
    } else {
      msgBox.innerHTML = `<div class="alert alert--error">${data.error || "Failed to send."}</div>`;
    }
  } catch (err) {
    msgBox.innerHTML = "<div class=\"alert alert--error\">Connection error.</div>";
  }
}
</script>';
include __DIR__ . '/includes/footer.php';
?>
