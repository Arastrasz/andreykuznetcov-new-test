<?php
require_once __DIR__ . '/config/db.php';
$user = currentUser();
$db = getDB();
$isAdmin = $user && $user['role'] === 'admin';

$slug = $_GET['slug'] ?? null;
$editMode = isset($_GET['edit']) && $isAdmin;

$pageTitle = 'News — ' . SITE_NAME;
$pageDesc = 'Latest updates from the Archives of Clan Lar.';
$extraCss = '
    .news-container { max-width:740px; margin:2rem auto; padding:0 1.5rem; }
    .news-list { list-style:none; }
    .news-item { display:flex; gap:1.5rem; padding:1.5rem 0; border-bottom:1px solid var(--border-card); }
    .news-item__img { width:200px; height:130px; object-fit:cover; flex-shrink:0; opacity:0.85; }
    .news-item__content { flex:1; }
    .news-item__title { font-family:var(--font-display); font-size:1.2rem; color:var(--text-primary); text-decoration:none; transition:color 0.3s; display:block; }
    .news-item__title:hover { color:var(--text-secondary); }
    .news-item__excerpt { font-family:var(--font-body); color:var(--text-secondary); font-size:1.05rem; margin-top:0.5rem; line-height:1.65; }
    .news-item__meta { font-family:var(--font-label); font-size:0.6rem; letter-spacing:0.2em; color:var(--text-dim); text-transform:uppercase; margin-top:0.5rem; }
    .news-hero { position:relative; margin-bottom:2rem; }
    .news-hero__img { width:100%; height:300px; object-fit:cover; opacity:0.7; }
    .news-hero__overlay { position:absolute; bottom:0; left:0; right:0; padding:2rem; background:linear-gradient(transparent,rgba(6,6,8,0.9)); }
    .news-body { font-family:var(--font-body); color:var(--text-secondary); line-height:1.85; font-size:1.1rem; }
    .news-body p { margin-bottom:1.1rem; }
    .news-body strong { color:var(--text-primary); }
    .news-body em { color:var(--text-secondary); }
    .comment { display:flex; gap:1rem; padding:1.1rem 0; border-bottom:1px solid rgba(30,29,38,0.3); }
    .comment__avatar { font-size:1.5rem; flex-shrink:0; }
    .comment__content { flex:1; }
    .comment__header { display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; }
    .comment__author { font-family:var(--font-label); font-size:0.65rem; letter-spacing:0.15em; color:var(--text-secondary); }
    .comment__time { font-family:var(--font-mono); font-size:0.8rem; color:var(--text-dim); }
    .comment__text { font-family:var(--font-body); color:var(--text-primary); margin-top:0.35rem; line-height:1.7; font-size:1.05rem; }
    .admin-bar { display:flex; gap:0.5rem; margin-bottom:1rem; padding:0.75rem; background:rgba(200,80,80,0.05); border:1px solid rgba(200,80,80,0.15); }
    @media (max-width:600px) { .news-item { flex-direction:column; } .news-item__img { width:100%; height:180px; } }
';
include __DIR__ . '/includes/header.php';

// Single news view
if ($slug) {
    $stmt = $db->prepare('SELECT n.*, u.display_name as author_name, u.avatar as author_avatar FROM news n JOIN users u ON n.author_id = u.id WHERE n.slug = ? AND n.published = 1');
    $stmt->execute([$slug]);
    $post = $stmt->fetch();
    if (!$post) { echo '<div class="page-content"><div class="news-container"><p>Post not found.</p></div></div>'; include __DIR__ . '/includes/footer.php'; exit; }

    // Get comments
    $cstmt = $db->prepare('
        SELECT c.*, u.display_name, u.username, u.avatar,
               t.name as title_name, t.color as title_color, t.icon as title_icon
        FROM comments c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN titles t ON u.title_id = t.id
        WHERE c.news_id = ?
        ORDER BY c.created_at ASC
    ');
    $cstmt->execute([$post['id']]);
    $comments = $cstmt->fetchAll();
?>

    <div class="page-content">
      <div class="news-container">
        <?php if ($isAdmin): ?>
          <div class="admin-bar">
            <a href="news?slug=<?= e($post['slug']) ?>&edit=1" class="btn btn--small">✎ Edit Post</a>
            <a href="admin?section=news" class="btn btn--small">All News</a>
          </div>
        <?php endif; ?>

        <?php if ($editMode && $isAdmin): ?>
          <!-- EDIT MODE -->
          <div id="editMessages"></div>
          <form id="editNewsForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_news">
            <input type="hidden" name="news_id" value="<?= $post['id'] ?>">
            <div class="form-group">
              <label class="form-label">Title</label>
              <input type="text" name="title" class="form-input" value="<?= e($post['title']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Excerpt</label>
              <textarea name="excerpt" class="form-textarea" style="min-height:60px;"><?= e($post['excerpt'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Hero Image Path</label>
              <input type="text" name="image" class="form-input" value="<?= e($post['image'] ?? '') ?>" placeholder="img/filename.jpg">
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
              <div class="rte-editor" id="newsEditor" contenteditable="true" style="min-height:300px;"><?= $post['content'] ?></div>
              <input type="hidden" name="content" id="newsContentHidden">
            </div>
            <div class="form-group">
              <label class="form-label" style="display:flex; align-items:center; gap:0.5rem;">
                <input type="checkbox" name="published" <?= $post['published'] ? 'checked' : '' ?> style="accent-color:var(--text-secondary);">
                Published
              </label>
            </div>
            <div style="display:flex; gap:1rem; margin-top:1.5rem;">
              <a href="news?slug=<?= e($post['slug']) ?>" class="btn">Cancel</a>
              <button type="submit" class="btn btn--primary">Save Changes</button>
            </div>
          </form>
        <?php else: ?>
          <!-- VIEW MODE -->
          <?php if ($post['image']): ?>
            <div class="news-hero">
              <img class="news-hero__img" src="<?= e($post['image']) ?>" alt="<?= e($post['title']) ?>">
              <div class="news-hero__overlay">
                <h1 style="font-family:var(--font-display); font-size:clamp(1.3rem,3vw,1.8rem); color:var(--text-primary);"><?= e($post['title']) ?></h1>
                <div style="font-family:var(--font-label); font-size:0.5rem; letter-spacing:0.2em; color:var(--text-dim); text-transform:uppercase; margin-top:0.5rem;">
                  <?= e($post['author_name']) ?> · <?= date('F j, Y', strtotime($post['created_at'])) ?>
                  <?php if ($post['updated_at'] !== $post['created_at']): ?>
                    · Updated <?= timeAgo($post['updated_at']) ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php else: ?>
            <h1 style="font-family:var(--font-display); font-size:clamp(1.3rem,3vw,1.8rem); color:var(--text-primary); margin-bottom:1rem;"><?= e($post['title']) ?></h1>
          <?php endif; ?>

          <div class="news-body"><?= $post['content'] ?></div>

          <!-- Comments -->
          <div style="margin-top:3rem; border-top:1px solid var(--border-card); padding-top:2rem;">
            <h3 style="font-family:var(--font-label); font-size:0.6rem; letter-spacing:0.25em; text-transform:uppercase; color:var(--text-dim); margin-bottom:1rem;">
              Comments (<?= count($comments) ?>)
            </h3>

            <div id="commentsList">
              <?php foreach ($comments as $c): ?>
                <div class="comment">
                  <span class="comment__avatar"><?= getAvatarSymbol($c['avatar']) ?></span>
                  <div class="comment__content">
                    <div class="comment__header">
                      <span class="comment__author"><?= e($c['display_name'] ?? $c['username']) ?></span>
                      <?php if ($c['title_name']): ?>
                        <span class="title-badge" style="font-size:0.5rem; padding:0.15em 0.6em; color:<?= e($c['title_color']) ?>; border-color:<?= e($c['title_color']) ?>;"><?= e($c['title_icon']) ?> <?= e($c['title_name']) ?></span>
                      <?php endif; ?>
                      <span class="comment__time"><?= timeAgo($c['created_at']) ?></span>
                    </div>
                    <div class="comment__text"><?= e($c['content']) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <?php if ($user): ?>
              <form id="commentForm" style="margin-top:1.5rem;">
                <?= csrfField() ?>
                <input type="hidden" name="news_id" value="<?= $post['id'] ?>">
                <div class="form-group">
                  <textarea name="content" class="form-textarea" style="min-height:60px;" placeholder="Leave a comment..." required minlength="2" maxlength="2000"></textarea>
                </div>
                <button type="submit" class="btn btn--primary btn--small">Post Comment</button>
              </form>
            <?php else: ?>
              <p style="font-family:var(--font-body); color:var(--text-dim); font-style:italic; margin-top:1rem;">
                <a href="login" style="color:var(--text-secondary);">Log in</a> to leave a comment.
              </p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

<?php
} else {
    // News list
    $posts = $db->query('SELECT n.*, u.display_name as author_name FROM news n JOIN users u ON n.author_id = u.id WHERE n.published = 1 ORDER BY n.created_at DESC')->fetchAll();
?>
    <div class="page-content">
      <div class="news-container">
        <div style="text-align:center; margin-bottom:2.5rem;">
          <div class="divider"><span>◆ — ◇ — ◆</span></div>
          <h1 class="section-title" style="font-size:clamp(1.2rem,2.5vw,1.6rem);">News & Updates</h1>
          <div class="divider"><span>◇ — ◆ — ◇</span></div>
        </div>

        <?php if ($isAdmin): ?>
          <div class="admin-bar">
            <a href="admin?section=news&new=1" class="btn btn--small">+ New Post</a>
            <a href="admin?section=news" class="btn btn--small">Manage All</a>
          </div>
        <?php endif; ?>

        <?php if (empty($posts)): ?>
          <p style="text-align:center; color:var(--text-dim); font-style:italic;">No news yet. The archives are quiet.</p>
        <?php else: ?>
          <ul class="news-list">
            <?php foreach ($posts as $p): ?>
              <li class="news-item">
                <?php if ($p['image']): ?>
                  <img class="news-item__img" src="<?= e($p['image']) ?>" alt="" loading="lazy">
                <?php endif; ?>
                <div class="news-item__content">
                  <a class="news-item__title" href="news?slug=<?= e($p['slug']) ?>"><?= e($p['title']) ?></a>
                  <?php if ($p['excerpt']): ?>
                    <p class="news-item__excerpt"><?= e($p['excerpt']) ?></p>
                  <?php endif; ?>
                  <div class="news-item__meta"><?= e($p['author_name']) ?> · <?= date('M j, Y', strtotime($p['created_at'])) ?></div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
<?php } ?>

<?php
$csrfToken = csrfToken();
$extraJs = '<script>
document.addEventListener("DOMContentLoaded", () => {
  const csrf = "' . $csrfToken . '";

  // Rich text editor buttons
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

  // Comment form
  document.getElementById("commentForm")?.addEventListener("submit", async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
      const res = await fetch("api/comment.php", { method: "POST", body: fd, headers: { "X-Requested-With": "XMLHttpRequest" } });
      const data = await res.json();
      if (data.success) {
        const c = data.comment;
        const html = `<div class="comment">
          <span class="comment__avatar">${c.avatar}</span>
          <div class="comment__content">
            <div class="comment__header">
              <span class="comment__author">${esc(c.display_name)}</span>
              ${c.title_name ? `<span class="title-badge" style="font-size:0.5rem;padding:0.15em 0.6em;color:${c.title_color};border-color:${c.title_color};">${c.title_name}</span>` : ""}
              <span class="comment__time">${c.time}</span>
            </div>
            <div class="comment__text">${esc(c.content)}</div>
          </div>
        </div>`;
        document.getElementById("commentsList").insertAdjacentHTML("beforeend", html);
        e.target.querySelector("textarea").value = "";
      }
    } catch (err) { console.error(err); }
  });

  // Edit news form
  document.getElementById("editNewsForm")?.addEventListener("submit", async e => {
    e.preventDefault();
    document.getElementById("newsContentHidden").value = document.getElementById("newsEditor").innerHTML;
    const fd = new FormData(e.target);
    try {
      const res = await fetch("api/admin.php", { method: "POST", body: fd, headers: { "X-Requested-With": "XMLHttpRequest" } });
      const data = await res.json();
      const msg = document.getElementById("editMessages");
      if (data.success) {
        msg.innerHTML = "<div class=\"alert alert--success\">Saved! Redirecting...</div>";
        setTimeout(() => location.href = "news?slug=" + fd.get("title").toLowerCase().replace(/[^a-z0-9]+/g, "-"), 1000);
      } else {
        msg.innerHTML = `<div class="alert alert--error">${data.error}</div>`;
      }
    } catch (err) {
      document.getElementById("editMessages").innerHTML = "<div class=\"alert alert--error\">Connection error.</div>";
    }
  });

  function esc(str) { const d = document.createElement("div"); d.textContent = str; return d.innerHTML; }
});
</script>';
include __DIR__ . '/includes/footer.php';
?>
