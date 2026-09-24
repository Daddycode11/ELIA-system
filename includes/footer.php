<?php
declare(strict_types=1);
$footerSite = app_config();
$footerEmail = (string) ($footerSite['support_email'] ?? '');
?>
        <footer class="page-footer mt-5 pt-3 small">
            <?= escape($footerSite['campus']) ?><br>
            <?= escape($footerSite['office_name']) ?>
            <?php if ($footerEmail !== ''): ?>
                <br><a href="mailto:<?= escape($footerEmail) ?>"><?= escape($footerEmail) ?></a>
            <?php endif; ?>
            <div class="mt-1">&copy; <?= date('Y') ?> Occidental Mindoro State College</div>
        </footer>
    </main>
</div>
<script src="<?= escape(url('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>" defer></script>
</body>
</html>