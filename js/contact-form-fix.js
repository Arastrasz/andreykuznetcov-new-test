/* ============================================================
   CONTACT FORM — Progressive Field Reveal + @ Validation
   Add this to contact.php inside a <script> tag before </body>
   ============================================================ */

document.addEventListener('DOMContentLoaded', function() {

    // ── Progressive Field Reveal ──────────────────────────────
    // Hide all category-specific field groups initially
    const categorySelect = document.querySelector('select[name="category"]');
    const fieldGroups = document.querySelectorAll('.form-step');
    const formSteps = {
        // Map category values to their step containers
        'review':           ['#step-common', '#step-submit'],
        'problem':          ['#step-common', '#step-submit'],
        'visual_creation':  ['#step-common', '#step-visual', '#step-submit'],
        'collaboration':    ['#step-common', '#step-submit'],
        'other':            ['#step-common', '#step-submit'],
    };

    // Hide all steps initially
    fieldGroups.forEach(el => {
        el.style.display = 'none';
        el.style.opacity = '0';
        el.style.transform = 'translateY(10px)';
        el.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
    });

    function showStep(el) {
        el.style.display = '';
        // Force reflow before transition
        el.offsetHeight;
        el.style.opacity = '1';
        el.style.transform = 'translateY(0)';
    }

    function hideStep(el) {
        el.style.opacity = '0';
        el.style.transform = 'translateY(10px)';
        setTimeout(() => { el.style.display = 'none'; }, 400);
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            const category = this.value;

            // Hide all steps first
            fieldGroups.forEach(el => hideStep(el));

            if (category && formSteps[category]) {
                // Reveal steps sequentially with stagger
                formSteps[category].forEach((selector, i) => {
                    const el = document.querySelector(selector);
                    if (el) {
                        setTimeout(() => showStep(el), 150 * (i + 1));
                    }
                });
            }
        });

        // If category is pre-selected (e.g. from failed validation), show fields
        if (categorySelect.value) {
            categorySelect.dispatchEvent(new Event('change'));
        }
    }


    // ── In-Game ID @ Prefix Validation ────────────────────────
    const gameIdInput = document.querySelector('input[name="game_id"]');

    if (gameIdInput) {
        // Set placeholder hint
        if (!gameIdInput.placeholder) {
            gameIdInput.placeholder = '@YourName';
        }

        // Auto-prepend @ on focus if empty
        gameIdInput.addEventListener('focus', function() {
            if (this.value === '') {
                this.value = '@';
            }
        });

        // Ensure @ stays at the beginning
        gameIdInput.addEventListener('input', function() {
            let val = this.value;

            // Remove all @ symbols, then prepend one
            val = val.replace(/@/g, '');
            this.value = '@' + val;
        });

        // Validate on blur
        gameIdInput.addEventListener('blur', function() {
            const val = this.value.trim();

            // Clear if only @ with nothing after it
            if (val === '@') {
                this.value = '';
                this.classList.remove('input--error');
                removeError(this);
                return;
            }

            // Validate format
            if (val && !val.startsWith('@')) {
                this.value = '@' + val;
            }

            if (val && val.length < 2) {
                this.classList.add('input--error');
                showError(this, 'In-game ID must start with @ followed by your name');
            } else {
                this.classList.remove('input--error');
                removeError(this);
            }
        });
    }


    // ── Form Validation on Submit ─────────────────────────────
    const contactForm = document.querySelector('.contact-form, form[action*="message"], form[action*="contact"]');

    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            let isValid = true;

            // Validate @ prefix on game_id
            if (gameIdInput) {
                const val = gameIdInput.value.trim();
                if (val && !val.startsWith('@')) {
                    e.preventDefault();
                    gameIdInput.value = '@' + val;
                }
                if (val && val.length < 2) {
                    e.preventDefault();
                    gameIdInput.classList.add('input--error');
                    showError(gameIdInput, 'In-game ID must start with @ followed by your name');
                    isValid = false;
                }
            }

            // Validate category is selected
            if (categorySelect && !categorySelect.value) {
                e.preventDefault();
                categorySelect.classList.add('input--error');
                showError(categorySelect, 'Please select a category');
                isValid = false;
            }

            if (!isValid) {
                // Scroll to first error
                const firstError = contactForm.querySelector('.input--error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }


    // ── Error Display Helpers ─────────────────────────────────
    function showError(input, message) {
        removeError(input);
        const errorEl = document.createElement('div');
        errorEl.className = 'field-error';
        errorEl.textContent = message;
        errorEl.style.cssText = 'color: var(--crimson, #d4556a); font-size: 0.75rem; margin-top: 0.3rem; font-style: italic; letter-spacing: 0.05em;';
        input.parentNode.appendChild(errorEl);
    }

    function removeError(input) {
        const existing = input.parentNode.querySelector('.field-error');
        if (existing) existing.remove();
    }
});
