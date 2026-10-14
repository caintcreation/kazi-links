<?php
// 403.php - Forbidden / Access Denied
require_once __DIR__ . '/includes/auth.php';
http_response_code(403);
$pageTitle = "Access Denied";
require_once __DIR__ . '/includes/header.php';
?>
<div class="container" style="text-align: center; padding: 60px 16px;">
  <div style="font-size: 4rem; color: var(--warning); font-weight: 800;">403</div>
  <h1 style="font-size: 1.8rem; font-weight: 800; color: var(--dark); margin: 10px 0;">Access Denied</h1>
  <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto 24px;">You do not have permission to view this resource.</p>
  <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary"><i class="bi bi-house"></i> Return Home</a>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
