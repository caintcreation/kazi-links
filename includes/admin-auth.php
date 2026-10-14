<?php
// includes/admin-auth.php - Admin authentication and role verification

require_once __DIR__ . '/functions.php';

function isAdminLoggedIn(): bool {
    return !empty($_SESSION['admin_id']);
}

function getLoggedInAdmin(): ?array {
    if (!isAdminLoggedIn()) {
        return null;
    }
    static $currentAdmin = null;
    if ($currentAdmin === null) {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, username, email, role, status FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();
        if (!$admin || $admin['status'] === 'inactive') {
            logoutAdmin();
            return null;
        }
        $currentAdmin = $admin;
    }
    return $currentAdmin;
}

function requireAdminAuth(): void {
    if (!isAdminLoggedIn()) {
        header("Location: " . BASE_URL . "/admin/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    $admin = getLoggedInAdmin();
    if (!$admin || $admin['status'] === 'inactive') {
        logoutAdmin();
        header("Location: " . BASE_URL . "/admin/login.php?error=inactive");
        exit;
    }
}

function logoutAdmin(): void {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_role']);
    session_regenerate_id(true);
}
