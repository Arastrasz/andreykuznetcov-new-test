<?php
require_once __DIR__ . '/config/db.php';

// Already logged in? Go to cabinet
if (currentUser()) {
    header('Location: /cabinet');
    exit;
}

$pageTitle = 'Enter the Archives — ' . SITE_NAME;
$pageDesc = 'Login or register to join the Archives of Clan Lar.';
$extraCss = '
    .auth-container { max-width:440px; margin:3rem auto; padding:0 1.5rem; }
    .auth-tabs { display:flex; gap:0; margin-bottom:2rem; border-bottom:1px solid var(--border-card); }
    .auth-tab { flex:1; text-align:center; padding:0.85rem; font-family:var(--font-label); font-size:0.68rem;
      letter-spacing:0.22em; text-transform:uppercase; color:var(--text-dim); cursor:pointer;
      border-bottom:2px solid transparent; transition:all 0.3s ease; background:none; border-top:none; border-left:none; border-right:none; }
    .auth-tab.active { color:var(--text-primary); border-bottom-color:var(--text-secondary); }
    .auth-panel { display:none; }
    .auth-panel.active { display:block; }
    .auth-divider { text-align:center; padding:1.5rem 0; color:var(--text-dim); font-family:var(--font-label); font-size:0.6rem; letter-spacing:0.3em; }
    .password-requirements { font-family:var(--font-body); font-size:0.9rem; color:var(--text-dim); font-style:italic; margin-top:0.35rem; }
    #formMessages { min-height:1rem; }
';
include __DIR__ . '/includes/header.php';

$step = $_GET['step'] ?? 'login';
$infoMsg = '';
if (isset($_GET['unverified'])) {
    $infoMsg = 'Your account has not been verified yet. Please check your email for the verification code.';
}
?>

    <div class="page-content">
      <div class="auth-container">
        <div style="text-align:center; margin-bottom:2rem;">
          <div class="divider"><span>◆ — ◇ — ◆</span></div>
          <h1 class="section-title" style="font-size:clamp(1.2rem,2.5vw,1.6rem);">Enter the Archives</h1>
          <div class="divider"><span>◇ — ◆ — ◇</span></div>
        </div>

        <div id="formMessages">
          <?php if ($infoMsg): ?>
            <div class="alert alert--info"><?= e($infoMsg) ?></div>
          <?php endif; ?>
        </div>

        <!-- Tabs: Login & Register only -->
        <div class="auth-tabs" id="authTabs">
          <button class="auth-tab <?= $step === 'login' ? 'active' : '' ?>" data-tab="login">Login</button>
          <button class="auth-tab <?= $step === 'register' ? 'active' : '' ?>" data-tab="register">Register</button>
        </div>

        <!-- LOGIN -->
        <div class="auth-panel <?= $step === 'login' ? 'active' : '' ?>" id="panel-login">
          <form id="loginForm">
            <?= csrfField() ?>
            <div class="form-group">
              <label class="form-label">Username or Email</label>
              <input type="text" name="login" class="form-input" required autocomplete="username">
            </div>
            <div class="form-group">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-input" required autocomplete="current-password">
            </div>
            <div style="text-align:center; margin-top:2rem;">
              <button type="submit" class="btn btn--primary">Enter →</button>
            </div>
          </form>
        </div>

        <!-- REGISTER -->
        <div class="auth-panel <?= $step === 'register' ? 'active' : '' ?>" id="panel-register">
          <form id="registerForm">
            <?= csrfField() ?>
            <div class="form-group">
              <label class="form-label">Username</label>
              <input type="text" name="username" class="form-input" required minlength="3" maxlength="50" autocomplete="username" pattern="[a-zA-Z0-9_-]+">
              <div class="password-requirements">Letters, numbers, hyphens, underscores. 3–50 characters.</div>
            </div>
            <div class="form-group">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-input" required autocomplete="email">
            </div>
            <div class="form-group">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-input" required minlength="8" autocomplete="new-password">
              <div class="password-requirements">At least 8 characters.</div>
            </div>
            <div class="form-group">
              <label class="form-label">Confirm Password</label>
              <input type="password" name="password_confirm" class="form-input" required autocomplete="new-password">
            </div>
            <div style="text-align:center; margin-top:2rem;">
              <button type="submit" class="btn btn--primary">Register →</button>
            </div>
          </form>
        </div>

        <div class="auth-divider">◇ — — ◆ — — ◇</div>
      </div>
    </div>

<?php
$extraJs = '<script>
document.addEventListener("DOMContentLoaded", () => {
  const tabs = document.querySelectorAll(".auth-tab");
  const panels = document.querySelectorAll(".auth-panel");
  const msg = document.getElementById("formMessages");

  function showTab(name) {
    tabs.forEach(t => t.classList.toggle("active", t.dataset.tab === name));
    panels.forEach(p => p.classList.toggle("active", p.id === "panel-" + name));
  }
  tabs.forEach(t => t.addEventListener("click", () => showTab(t.dataset.tab)));

  function showMsg(text, type) {
    msg.innerHTML = `<div class="alert alert--${type}">${text}</div>`;
    msg.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }

  async function submitForm(form, url) {
    const fd = new FormData(form);
    try {
      const res = await fetch(url, { method: "POST", body: fd, headers: { "X-Requested-With": "XMLHttpRequest" } });
      const data = await res.json();
      if (data.success) {
        showMsg(data.message, "success");
        if (data.redirect) setTimeout(() => window.location.href = data.redirect, 1000);
      } else {
        const errText = data.errors ? data.errors.join("<br>") : data.error;
        showMsg(errText, "error");
        if (data.redirect) setTimeout(() => { window.location.href = data.redirect; }, 1500);
      }
    } catch (e) { showMsg("Connection error. Try again.", "error"); }
  }

  document.getElementById("loginForm")?.addEventListener("submit", e => { e.preventDefault(); submitForm(e.target, "api/login.php"); });
  document.getElementById("registerForm")?.addEventListener("submit", e => { e.preventDefault(); submitForm(e.target, "api/register.php"); });
});
</script>';
include __DIR__ . '/includes/footer.php';
?>
