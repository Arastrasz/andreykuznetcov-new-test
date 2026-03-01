<?php
/* ============================================================
   THE ARCHIVES OF CLAN LAR — Contact
   Progressive form with category-specific field reveal
   ============================================================ */
require_once __DIR__ . '/config/db.php';

$user = currentUser();
$success = null;
$error = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $category = trim($_POST['category'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $gameId = trim($_POST['game_id'] ?? '');
        $senderName = $user ? $user['username'] : trim($_POST['sender_name'] ?? '');
        $senderEmail = $user ? $user['email'] : trim($_POST['sender_email'] ?? '');

        // Validate game ID (@ prefix)
        if ($gameId) {
            try {
                $gameId = validateGameId($gameId);
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }

        // Validate required fields
        if (!$error) {
            if (empty($category)) {
                $error = 'Please select a category.';
            } elseif (empty($subject) || mb_strlen($subject) < 3) {
                $error = 'Subject must be at least 3 characters.';
            } elseif (empty($message) || mb_strlen($message) < 10) {
                $error = 'Message must be at least 10 characters.';
            } elseif (!$user && empty($senderName)) {
                $error = 'Please enter your name.';
            } elseif (!$user && (empty($senderEmail) || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL))) {
                $error = 'Please enter a valid email address.';
            }
        }

        // Build Visual Creation metadata
        $vcMeta = null;
        if (!$error && $category === 'visual_creation') {
            $vcServer = trim($_POST['vc_server'] ?? '');
            $vcHouse = trim($_POST['vc_house'] ?? '');
            $vcCamera = trim($_POST['vc_camera'] ?? '');
            $vcAreas = trim($_POST['vc_areas'] ?? '');
            $vcDuration = trim($_POST['vc_duration'] ?? '');
            $vcSecret = trim($_POST['vc_secret'] ?? '');
            $vcReshade = isset($_POST['vc_no_reshade']) ? 'disabled' : 'default';

            if (empty($vcServer)) {
                $error = 'Please select your server for Visual Creation requests.';
            } elseif (empty($vcHouse)) {
                $error = 'Please enter the house name.';
            }

            if (!$error) {
                $vcMeta = json_encode([
                    'server' => $vcServer,
                    'house' => $vcHouse,
                    'camera' => $vcCamera ?: null,
                    'areas' => $vcAreas ?: null,
                    'duration' => $vcDuration ?: null,
                    'secret_room' => $vcSecret ?: null,
                    'reshade' => $vcReshade,
                ]);
            }
        }

        // Save message
        if (!$error) {
            $db = getDB();
            $stmt = $db->prepare('
                INSERT INTO messages (sender_id, receiver_id, sender_name, sender_email, game_id, category, subject, content, vc_meta, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
            ');
            $stmt->execute([
                $user ? $user['id'] : null,
                ADMIN_USER_ID,
                $senderName,
                $senderEmail,
                $gameId ?: null,
                $category,
                $subject,
                $message,
                $vcMeta,
            ]);

            // Notify admin
            sendMessageNotificationEmail(
                ADMIN_EMAIL,
                $senderName ?: 'Anonymous',
                $subject,
                $message
            );

            $success = 'Your message has been sent. Expect a response within 1–2 days.';
        }
    }
}

$pageTitle = 'Contact Andrey — ' . SITE_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<section class="page-section">
  <div class="page-container">

    <!-- Page Header -->
    <div class="page-header" style="text-align: center; margin-bottom: 3rem;">
      <div class="ornament" style="color: var(--text-faint); font-size: 0.7rem; letter-spacing: 0.5em; margin-bottom: 1rem;">◆ ━━━ ◇ ━━━ ◆</div>
      <h1 style="font-family: var(--font-display); font-size: clamp(1.2rem, 2.5vw, 1.8rem); letter-spacing: 0.2em; text-transform: uppercase; color: var(--text-primary); font-weight: 400;">Contact Andrey</h1>
      <p style="font-family: var(--font-body); font-style: italic; color: var(--text-dim); font-size: 0.95rem; margin-top: 0.8rem; line-height: 1.8;">Questions, requests, collaboration — the Archives are open.</p>
      <div class="ornament" style="color: var(--text-faint); font-size: 0.7rem; letter-spacing: 0.5em; margin-top: 1rem;">◆ ━━━ ◇ ━━━ ◆</div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert--success" style="max-width: 600px; margin: 0 auto 2rem; padding: 1.2rem 1.5rem; background: rgba(106,154,106,0.08); border: 1px solid rgba(106,154,106,0.2); border-radius: 4px; text-align: center;">
        <p style="color: var(--wyrd-green, #6a9a6a); margin: 0; font-size: 0.95rem;">✓ <?= e($success) ?></p>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert--error" style="max-width: 600px; margin: 0 auto 2rem; padding: 1.2rem 1.5rem; background: rgba(212,85,106,0.06); border: 1px solid rgba(212,85,106,0.2); border-radius: 4px; text-align: center;">
        <p style="color: var(--crimson, #d4556a); margin: 0; font-size: 0.95rem;">⚠ <?= e($error) ?></p>
      </div>
    <?php endif; ?>

    <!-- Contact Form -->
    <form class="contact-form" method="POST" action="" style="max-width: 600px; margin: 0 auto;">
      <?= csrfField() ?>

      <!-- STEP 0: Category Selection (always visible) -->
      <div class="form-group" style="margin-bottom: 1.5rem;">
        <label style="display: block; font-family: var(--font-display); font-size: 0.65rem; letter-spacing: 0.25em; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 0.5rem;">Category</label>
        <select name="category" id="categorySelect" required style="width: 100%; padding: 0.7rem 1rem; background: var(--bg-card, #12131a); border: 1px solid rgba(160,160,170,0.1); color: var(--text-primary); font-family: var(--font-body); font-size: 0.95rem; border-radius: 3px; transition: border-color 0.3s;">
          <option value="">— Select a category —</option>
          <option value="review" <?= ($_POST['category'] ?? '') === 'review' ? 'selected' : '' ?>>☙ Review</option>
          <option value="problem" <?= ($_POST['category'] ?? '') === 'problem' ? 'selected' : '' ?>>⚠ Problem</option>
          <option value="visual_creation" <?= ($_POST['category'] ?? '') === 'visual_creation' ? 'selected' : '' ?>>✦ Visual Creation Request</option>
          <option value="collaboration" <?= ($_POST['category'] ?? '') === 'collaboration' ? 'selected' : '' ?>>◇ Collaboration</option>
          <option value="other" <?= ($_POST['category'] ?? '') === 'other' ? 'selected' : '' ?>>◆ Other</option>
        </select>
      </div>

      <!-- STEP 1: Common Fields (revealed after category selection) -->
      <div class="form-step" id="step-common" style="display: none;">

        <?php if (!$user): ?>
        <!-- Guest fields -->
        <div class="form-group" style="margin-bottom: 1.2rem;">
          <label style="display: block; font-family: var(--font-display); font-size: 0.65rem; letter-spacing: 0.25em; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 0.5rem;">Your Name</label>
          <input type="text" name="sender_name" required value="<?= e($_POST['sender_name'] ?? '') ?>" placeholder="How should we address you?"
                 style="width: 100%; padding: 0.7rem 1rem; background: var(--bg-card, #12131a); border: 1px solid rgba(160,160,170,0.1); color: var(--text-primary); font-family: var(--font-body); font-size: 0.95rem; border-radius: 3px;">
        </div>
        <div class="form-group" style="margin-bottom: 1.2rem;">
          <label style="display: block; font-family: var(--font-display); font-size: 0.65rem; letter-spacing: 0.25em; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 0.5rem;">Email</label>
          <input type="email" name="sender_email" required value="<?= e($_POST['sender_email'] ?? '') ?>" placeholder="For reply purposes only"
                 style="width: 100%; padding: 0.7rem 1rem; background: var(--bg-card, #12131a); border: 1px solid rgba(160,160,170,0.1); color: var(--text-primary); font-family: var(--font-body); font-size: 0.95rem; border-radius: 3px;">
        </div>
        <?php endif; ?>

        <div class="form-group" style="margin-bottom: 1.2rem;">
          <label style="display: block; font-family: var(--font-display); font-size: 0.65rem; letter-spacing: 0.25em; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 0.5rem;">Subject</label>
          <input type="text" name="subject" required value="<?= e($_POST['subject'] ?? '') ?>" placeholder="Brief description of your message"
                 style="width: 100%; padding: 0.7rem 1rem; background: var(--bg-card, #12131a); border: 1px solid rgba(160,160,170,0.1); color: var(--text-primary); font-family: var(--font-body); font-size: 0.95rem; border-radius: 3px;">
        </div>

        <div class="form-group" style="margin-bottom: 1.2rem;">
          <label style="display: block; font-family: var(--font-display); font-size: 0.65rem; letter-spacing: 0.25em; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 0.5rem;">In-Game ID</label>
          <input type="text" name="game_id" id="gameIdInput" value="<?= e($_POST['game_id'] ?? '') ?>" placeholder="@YourName"
                 style="width: 100%; padding: 0.7rem 1rem; background: var(--bg-card, #12131a); border: 1px solid rgba(160,160,170,0.1); color: var(--text-primary); font-family: var(--font-body); font-size: 0.95rem; border-radius: 3px;">
          <div style="color: var(--text-dim, #5a5650); font-size: 0.7rem; letter-spacing: 0.05em; margin-top: 0.3rem;">Must start with @ — your ESO in-game handle</div>
        </div>

        <div class="form-group" style="margin-bottom: 1.2rem;">
          <label style="display: block; font-family: var(--font-display); font-size: 0.65rem; letter-spacing: 0.25em; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 0.5rem;">Message</label>
          <textarea name="message" rows="6" required placeholder="Tell us more..."
                    style="width: 100%; padding: 0.7rem 1rem; background: var(--bg-card, #12131a); border: 1px solid rgba(160,160,170,0.1); color: var(--text-primary); font-family: var(--font-body); font-size: 0.95rem; border-radius: 3px; resize: vertical; line-height: 1.7;"><?= e($_POST['message'] ?? '') ?></textarea>
        </div>
      </div>

      <!-- STEP 2: Visual Creation Fields (only for visual_creation category) -->
      <div class="form-step" id="step-visual" style="display: none;">
        <div style="text-align: center; color: var(--ayleid-blue, #5a8a9e); font-size: 0.65rem; letter-spacing: 0.35em; text-transform: uppercase; margin: 1.5rem 0 1rem;">✦ ━━━ Visual Creation Details ━━━ ✦</div>

        <div style="padding: 1rem 1.2rem; background: rgba(90,138,158,0.04); border: 1px solid rgba(90,138,158,0.1); border-radius: 4px; margin-bottom: 1.5rem;">
          <p style="color: var(--text-secondary); font-size: 0.9rem; font-style: italic; line-height: 1.8; margin: 0;">
            Recording and editing takes time — please specify all your wishes upfront so the result matches your vision.
          </p>
        </div>

        <div class="form-group" style="margin-bottom: 1.2rem;">
          <label style="display: block; font-family: var(--font-display); font-size: 0.65rem; letter-spacing: 0.25em; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 0.5rem;">Server <span style="color: var(--crimson);">*</span></label>
          <select name="vc_server" style="width: 100%; padding: 0.7rem 1rem; background: var(--bg-card, #12131a); border: 1px solid rgba(160,160,170,0.1); color: var(--text-primary); font-family: var(--font-body); font-size: 0.95rem; border-radius: 3px;">
            <option value="">— Select server —</option>
            <option value="PC-EU" <?= ($_POST['vc_server'] ?? '') === 'PC-EU' ? 'selected' : '' ?>>PC-EU</option>
            <option value="PC-NA" <?= ($_POST['vc_server'] ?? '') === 'PC-NA' ? 'selected' : '' ?>>PC-NA</option>
          </select>
          <div style="color: var(--text-dim); font-size: 0.7rem; margin-top: 0.3rem;">Visual creation is available for PC-EU and PC-NA only</div>
        </div>

        <div class="form-group" style="margin-bottom: 1.2rem;">
          <label style="display: block; font-family: var(--font-display); font-size: 0.65rem; letter-spacing: 0.25em; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 0.5rem;">House Name <span style="color: var(--crimson);">*</span></label>
          <input type="text" name="vc_house" value="<?= e($_POST['vc_house'] ?? '') ?>" placeholder="e.g. Autumn's Gate"
                 style="width: 100%; padding: 0.7rem 1rem; background: var(--bg-card, #12131a); border: 1px solid rgba(160,160,170,0.1); color: var(--text-primary); font-family: var(--font-body); font-size: 0.95rem; border-radius: 3px;">
        </div>

        <div class="form-group" style="margin-bottom: 1.2rem;">
          <label style="display: block; font-family: var(--font-display); font-size: 0.65rem; letter-spacing: 0.25em; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 0.5rem;">Camera Perspective <span style="color: var(--text-dim); font-size: 0.6rem; font-style: italic; text-transform: none;">(optional)</span></label>
          <select name="vc_camera" style="width: 100%; padding: 0.7rem 1rem; background: var(--bg-card, #12131a); border: 1px solid rgba(160,160,170,0.1); color: var(--text-primary); font-family: var(--font-body); font-size: 0.95rem; border-radius: 3px;">
            <option value="">— No preference —</option>
            <option value="first" <?= ($_POST['vc_camera'] ?? '') === 'first' ? 'selected' : '' ?>>First Person</option>
            <option value="third" <?= ($_POST['vc_camera'] ?? '') === 'third' ? 'selected' : '' ?>>Third Person</option>
          </select>
        </div>

        <div class="form-group" style="margin-bottom: 1.2rem;">
          <label style="display: block; font-family: var(--font-display); font-size: 0.65rem; letter-spacing: 0.25em; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 0.5rem;">Specific Areas to Capture <span style="color: var(--text-dim); font-size: 0.6rem; font-style: italic; text-transform: none;">(optional)</span></label>
          <textarea name="vc_areas" rows="3" placeholder="E.g. 'Focus on the garden area and the hidden library'"
                    style="width: 100%; padding: 0.7rem 1rem; background: var(--bg-card, #12131a); border: 1px solid rgba(160,160,170,0.1); color: var(--text-primary); font-family: var(--font-body); font-size: 0.95rem; border-radius: 3px; resize: vertical;"><?= e($_POST['vc_areas'] ?? '') ?></textarea>
        </div>

        <div class="form-group" style="margin-bottom: 1.2rem;">
          <label style="display: block; font-family: var(--font-display); font-size: 0.65rem; letter-spacing: 0.25em; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 0.5rem;">Tour Duration <span style="color: var(--text-dim); font-size: 0.6rem; font-style: italic; text-transform: none;">(optional)</span></label>
          <select name="vc_duration" style="width: 100%; padding: 0.7rem 1rem; background: var(--bg-card, #12131a); border: 1px solid rgba(160,160,170,0.1); color: var(--text-primary); font-family: var(--font-body); font-size: 0.95rem; border-radius: 3px;">
            <option value="">— Default —</option>
            <option value="short" <?= ($_POST['vc_duration'] ?? '') === 'short' ? 'selected' : '' ?>>Shorter</option>
            <option value="normal" <?= ($_POST['vc_duration'] ?? '') === 'normal' ? 'selected' : '' ?>>Normal</option>
            <option value="long" <?= ($_POST['vc_duration'] ?? '') === 'long' ? 'selected' : '' ?>>Longer (max ~1 minute)</option>
          </select>
        </div>

        <div class="form-group" style="margin-bottom: 1.2rem;">
          <label style="display: block; font-family: var(--font-display); font-size: 0.65rem; letter-spacing: 0.25em; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 0.5rem;">Secret Room? <span style="color: var(--text-dim); font-size: 0.6rem; font-style: italic; text-transform: none;">(optional)</span></label>
          <input type="text" name="vc_secret" value="<?= e($_POST['vc_secret'] ?? '') ?>" placeholder="If yes, describe its location"
                 style="width: 100%; padding: 0.7rem 1rem; background: var(--bg-card, #12131a); border: 1px solid rgba(160,160,170,0.1); color: var(--text-primary); font-family: var(--font-body); font-size: 0.95rem; border-radius: 3px;">
        </div>

        <div class="form-group" style="margin-bottom: 1.2rem;">
          <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-family: var(--font-body); font-size: 0.9rem; color: var(--text-secondary);">
            <input type="checkbox" name="vc_no_reshade" value="1" <?= isset($_POST['vc_no_reshade']) ? 'checked' : '' ?>
                   style="accent-color: var(--ayleid-blue, #5a8a9e);">
            Disable reshade (mild reshade is used by default)
          </label>
        </div>

        <!-- Important Notes -->
        <div style="margin-top: 1.5rem; padding: 1.2rem 1.5rem; background: rgba(18,19,26,0.6); border: 1px solid rgba(90,138,158,0.12); border-radius: 4px;">
          <div style="color: var(--ayleid-blue, #5a8a9e); font-size: 0.6rem; letter-spacing: 0.2em; text-transform: uppercase; margin-bottom: 0.6rem;">✦ Important Notes</div>
          <ul style="color: var(--text-dim); font-size: 0.85rem; line-height: 2; padding-left: 1.2rem; margin: 0;">
            <li>Available for <strong style="color: var(--text-secondary);">PC-EU</strong> and <strong style="color: var(--text-secondary);">PC-NA</strong> only</li>
            <li>Custom EHT effects, labels, and triggers are not supported</li>
            <li>For specific time of day — use Time of Day Control furnishings</li>
            <li>Weather control should be set in advance</li>
            <li>Please credit when posting: <em style="color: var(--ayleid-blue);">"GIF by kitsun.dark"</em></li>
            <li>No limit on requests — but one GIF request per message</li>
            <li>Turnaround: usually 1–2 days, up to a week</li>
            <li>This service is <strong style="color: var(--text-secondary);">entirely free</strong></li>
          </ul>
        </div>

        <!-- Example Request -->
        <div style="margin-top: 1rem; padding: 1rem 1.2rem; background: rgba(18,19,26,0.35); border-left: 2px solid rgba(90,138,158,0.25);">
          <div style="color: var(--text-dim); font-size: 0.6rem; letter-spacing: 0.15em; text-transform: uppercase; margin-bottom: 0.4rem;">Example Request</div>
          <p style="color: var(--text-secondary); font-size: 0.9rem; font-style: italic; line-height: 1.7; margin: 0;">
            "Hey kitsun! I'd love a GIF for my PC/EU Autumn's Gate, @kitsun.dark. Could you capture the area outside the house in third person? I'm also really proud of my bee houses — please get those too. Thank you!"
          </p>
        </div>
      </div>

      <!-- STEP 3: Submit (revealed with common fields) -->
      <div class="form-step" id="step-submit" style="display: none;">
        <div style="margin-top: 1.5rem; text-align: center;">
          <button type="submit" style="display: inline-block; font-family: var(--font-display); font-size: 0.6rem; letter-spacing: 0.3em; text-transform: uppercase; color: var(--text-primary); background: transparent; padding: 0.8em 3em; border: 1px solid rgba(160,160,170,0.2); cursor: pointer; transition: all 0.4s ease; border-radius: 2px;">
            Send Message
          </button>
        </div>
      </div>

    </form>

  </div>
</section>

<!-- Progressive Form + Validation Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    // ── Progressive Field Reveal ──────────────────────────────
    const categorySelect = document.getElementById('categorySelect');
    const allSteps = document.querySelectorAll('.form-step');
    const formSteps = {
        'review':           ['#step-common', '#step-submit'],
        'problem':          ['#step-common', '#step-submit'],
        'visual_creation':  ['#step-common', '#step-visual', '#step-submit'],
        'collaboration':    ['#step-common', '#step-submit'],
        'other':            ['#step-common', '#step-submit'],
    };

    // Style transitions
    allSteps.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(12px)';
        el.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
    });

    // Track hide timeouts so we can cancel them
    const hideTimers = new Map();

    function showStep(el) {
        // Cancel any pending hide timeout for this element
        if (hideTimers.has(el)) {
            clearTimeout(hideTimers.get(el));
            hideTimers.delete(el);
        }
        el.style.display = '';
        el.offsetHeight; // reflow
        el.style.opacity = '1';
        el.style.transform = 'translateY(0)';
    }

    function hideStep(el) {
        // Cancel any previous pending hide for this element
        if (hideTimers.has(el)) {
            clearTimeout(hideTimers.get(el));
        }
        el.style.opacity = '0';
        el.style.transform = 'translateY(12px)';
        const timer = setTimeout(() => {
            el.style.display = 'none';
            hideTimers.delete(el);
        }, 400);
        hideTimers.set(el, timer);
    }

    categorySelect.addEventListener('change', function() {
        const cat = this.value;

        // Hide all
        allSteps.forEach(el => hideStep(el));

        // Show relevant (delay lets hide animation start first)
        if (cat && formSteps[cat]) {
            formSteps[cat].forEach((sel, i) => {
                const el = document.querySelector(sel);
                if (el) setTimeout(() => showStep(el), 150 * (i + 1));
            });
        }
    });

    // If form was submitted with errors, re-show the fields
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$success): ?>
    categorySelect.dispatchEvent(new Event('change'));
    <?php endif; ?>


    // ── In-Game ID @ Validation ───────────────────────────────
    const gameId = document.getElementById('gameIdInput');

    if (gameId) {
        gameId.addEventListener('focus', function() {
            if (!this.value) this.value = '@';
        });

        gameId.addEventListener('input', function() {
            let v = this.value.replace(/@/g, '');
            this.value = '@' + v;
        });

        gameId.addEventListener('blur', function() {
            if (this.value.trim() === '@') this.value = '';
            const v = this.value.trim();
            const errEl = this.parentNode.querySelector('.field-error');
            if (errEl) errEl.remove();
            this.style.borderColor = '';

            if (v && v.length < 2) {
                this.style.borderColor = 'var(--crimson, #d4556a)';
                const err = document.createElement('div');
                err.className = 'field-error';
                err.textContent = 'In-game ID must start with @ followed by your name';
                err.style.cssText = 'color: var(--crimson, #d4556a); font-size: 0.75rem; margin-top: 0.3rem; font-style: italic;';
                this.parentNode.appendChild(err);
            }
        });
    }


    // ── Form Submit Validation ────────────────────────────────
    document.querySelector('.contact-form').addEventListener('submit', function(e) {
        if (gameId) {
            const v = gameId.value.trim();
            if (v && !v.startsWith('@')) gameId.value = '@' + v;
            if (v && v.length < 2) {
                e.preventDefault();
                gameId.focus();
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
