<?php
require_once __DIR__ . '/config/db.php';

// Already verified and logged in? Go to cabinet
$user = currentUser();
if ($user && $user['verified']) {
    header('Location: /cabinet');
    exit;
}

// Must have a pending user in session (from registration or unverified login)
if (empty($_SESSION['pending_user_id'])) {
    header('Location: /login');
    exit;
}

$pageTitle = 'Verify Your Account — ' . SITE_NAME;
$pageDesc = 'Enter your verification code to complete registration.';
$extraCss = '
    .verify-container { max-width:460px; margin:3rem auto; padding:0 1.5rem; }
    .verify-code-inputs { display:flex; gap:0.6rem; justify-content:center; margin:1.5rem 0; }
    .verify-code-inputs input { width:3.2rem; height:3.8rem; text-align:center; font-family:var(--font-label);
      font-size:1.6rem; letter-spacing:0; background:rgba(16,16,20,0.8); border:1px solid var(--border-card);
      color:var(--text-primary); outline:none; transition:border-color 0.3s; }
    .verify-code-inputs input:focus { border-color:rgba(160,160,170,0.4); }
    .resend-link { background:none; border:none; color:var(--text-dim); font-family:var(--font-body); font-size:1rem;
      font-style:italic; cursor:pointer; text-decoration:underline; transition:color 0.3s; }
    .resend-link:hover { color:var(--text-secondary); }
    .verify-illustration { text-align:center; font-size:3rem; color:var(--text-dim); margin-bottom:1rem; letter-spacing:0.3em; }
    #formMessages { min-height:1rem; }
';
include __DIR__ . '/includes/header.php';
?>

    <div class="page-content">
      <div class="verify-container">
        <div style="text-align:center; margin-bottom:2rem;">
          <div class="divider"><span>◆ — ◇ — ◆</span></div>
          <h1 class="section-title" style="font-size:clamp(1.2rem,2.5vw,1.6rem);">One Last Step</h1>
          <div class="divider"><span>◇ — ◆ — ◇</span></div>
        </div>

        <div class="verify-illustration">◆ ◇ ◆</div>

        <div id="formMessages"></div>

        <div style="text-align:center; margin-bottom:2rem;">
          <p style="font-family:var(--font-body); color:var(--text-secondary); font-size:1.1rem; line-height:1.75; font-style:italic;">
            A verification code has been sent to your email. Enter it below to unlock the Archives.
          </p>
          <?php if (!empty($_SESSION['pending_email'])): ?>
            <p style="font-family:var(--font-label); font-size:0.68rem; letter-spacing:0.15em; color:var(--text-dim); margin-top:0.5rem;">
              Sent to: <?= e(substr($_SESSION['pending_email'], 0, 3)) ?>***<?= e(strstr($_SESSION['pending_email'], '@')) ?>
            </p>
          <?php endif; ?>
        </div>

        <form id="verifyForm">
          <?= csrfField() ?>
          <div class="verify-code-inputs" id="codeInputs">
            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
          </div>
          <input type="hidden" name="code" id="codeHidden">
          <div style="text-align:center;">
            <button type="submit" class="btn btn--primary">Verify →</button>
          </div>
        </form>

        <div style="text-align:center; margin-top:2rem;">
          <button class="resend-link" id="resendBtn">Didn't receive a code? Resend</button>
        </div>

        <div style="text-align:center; padding:1.5rem 0; color:var(--text-dim); font-family:var(--font-label); font-size:0.55rem; letter-spacing:0.3em;">
          ◇ — — ◆ — — ◇
        </div>
      </div>
    </div>

<?php
$extraJs = '<script>
document.addEventListener("DOMContentLoaded", () => {
  const msg = document.getElementById("formMessages");
  const codeInputs = document.querySelectorAll("#codeInputs input");

  function showMsg(text, type) {
    msg.innerHTML = `<div class="alert alert--${type}">${text}</div>`;
  }

  // Auto-advance code inputs
  codeInputs.forEach((inp, i) => {
    inp.addEventListener("input", e => {
      if (e.target.value && i < codeInputs.length - 1) codeInputs[i + 1].focus();
    });
    inp.addEventListener("keydown", e => {
      if (e.key === "Backspace" && !e.target.value && i > 0) codeInputs[i - 1].focus();
    });
    inp.addEventListener("paste", e => {
      const text = (e.clipboardData || window.clipboardData).getData("text").trim();
      if (/^[0-9]{6}$/.test(text)) {
        e.preventDefault();
        [...text].forEach((ch, j) => { if (codeInputs[j]) codeInputs[j].value = ch; });
        codeInputs[5].focus();
      }
    });
  });

  document.getElementById("verifyForm")?.addEventListener("submit", async e => {
    e.preventDefault();
    const code = [...codeInputs].map(i => i.value).join("");
    if (code.length !== 6) { showMsg("Please enter the full 6-digit code.", "error"); return; }
    document.getElementById("codeHidden").value = code;
    const fd = new FormData(e.target);
    try {
      const res = await fetch("api/verify.php", { method: "POST", body: fd, headers: { "X-Requested-With": "XMLHttpRequest" } });
      const data = await res.json();
      if (data.success) {
        showMsg(data.message, "success");
        setTimeout(() => window.location.href = data.redirect || "/cabinet", 1000);
      } else {
        showMsg(data.error || "Invalid code.", "error");
      }
    } catch (err) { showMsg("Connection error. Try again.", "error"); }
  });

  document.getElementById("resendBtn")?.addEventListener("click", async () => {
    const btn = document.getElementById("resendBtn");
    btn.disabled = true;
    btn.textContent = "Sending...";
    try {
      const res = await fetch("api/resend.php", { method: "POST", headers: { "X-Requested-With": "XMLHttpRequest" } });
      const data = await res.json();
      showMsg(data.message || data.error, data.success ? "success" : "error");
    } catch (e) { showMsg("Failed to resend.", "error"); }
    setTimeout(() => { btn.disabled = false; btn.textContent = "Didn\'t receive a code? Resend"; }, 30000);
  });
});
</script>';
include __DIR__ . '/includes/footer.php';
?>
