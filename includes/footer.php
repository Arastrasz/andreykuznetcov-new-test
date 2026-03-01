<?php
/* ============================================================
   THE ARCHIVES OF CLAN LAR — Footer
   ============================================================ */
?>
</main>

<footer class="site-footer">
    <div class="footer-inner">
        <div class="ornament" style="color:var(--text-faint); font-size:0.7rem; letter-spacing:0.5em; margin-bottom:0.75rem;">◆ ━━━ ◇ ━━━ ◆</div>
        <div style="font-family:var(--font-label); font-size:0.55rem; letter-spacing:0.35em; text-transform:uppercase; color:var(--text-faint);">
            <?= t('footer.archives') ?>
        </div>
        <div style="font-family:var(--font-body); font-size:0.75rem; color:var(--text-dim); margin-top:0.5rem; font-style:italic;">
            &copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>
        </div>
    </div>
</footer>

<?php if (!empty($extraJs)): ?>
    <?= $extraJs ?>
<?php endif; ?>

</body>
</html>
