<?php
// logout.php - Logs out current session and redirects
require_once __DIR__ . '/includes/auth.php';

logoutUser();
header("Location: " . BASE_URL . "/login.php?logged_out=1");
exit;
