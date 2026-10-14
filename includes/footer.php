<?php
// includes/footer.php - Global footer matching reference site
$supportEmail = getSetting('support_email', 'kazilinksupport@gmail.com');
?>
<footer style="border-top: 1px solid var(--border); padding: 2rem 1rem; text-align: center; font-size: 0.875rem; color: var(--muted-foreground); margin-top: 3rem;">
  <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">🤝</div>
  <div style="font-weight: 600; color: var(--foreground); margin-bottom: 0.25rem;">Together we earn</div>
  <div>&copy; <?= date('Y') ?> KaziLink &middot; <a href="<?= BASE_URL ?>/contact.php">Customer Care</a> &middot; <a href="<?= BASE_URL ?>/terms.php">Terms of Service</a> &middot; <?= e($supportEmail) ?></div>
</footer>

<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
