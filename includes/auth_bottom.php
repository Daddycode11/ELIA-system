</main>

<footer class="el-footer">
  <div class="el-wrap">
    <span>&copy; <?= date('Y') ?> <?= escape($site['office_name']) ?> Office, Occidental Mindoro State College.</span>
    <span>Need help? <a href="mailto:<?= escape($site['support_email']) ?>"><?= escape($site['support_email']) ?></a></span>
  </div>
</footer>

<script>
document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var input = document.getElementById(btn.dataset.togglePassword);
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.textContent = show ? 'Hide' : 'Show';
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
  });
});
</script>
<?php if (!empty($authExtraScript)) { echo $authExtraScript; } ?>
</body>
</html>