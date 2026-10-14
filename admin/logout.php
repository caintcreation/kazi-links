<?php
// admin/logout.php - Admin Logout
require_once __DIR__ . '/../includes/admin-auth.php';

logoutAdmin();
header("Location: " . BASE_URL . "/admin/login.php?logged_out=1");
exit;
