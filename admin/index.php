<?php
// admin/index.php - Redirects to dashboard or login
require_once __DIR__ . '/../includes/admin-auth.php';

if (isAdminLoggedIn()) {
    header("Location: " . BASE_URL . "/admin/dashboard.php");
} else {
    header("Location: " . BASE_URL . "/admin/login.php");
}
exit;
