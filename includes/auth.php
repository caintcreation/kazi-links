<?php
// includes/auth.php - User authentication and session verification

require_once __DIR__ . '/functions.php';


function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function getLoggedInUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    static $currentUser = null;
    if ($currentUser === null) {
        ensureRegistrationSchema();
        $db = getDB();
        $stmt = $db->prepare("SELECT id, name, email, phone, referral_code, email_verified, status, created_at FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user || $user['status'] === 'suspended') {
            logoutUser();
            return null;
        }
        $currentUser = $user;
    }
    return $currentUser;
}

function requireAuth(bool $requireVerified = false): void {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    $user = getLoggedInUser();
    if (!$user || $user['status'] === 'suspended') {
        logoutUser();
        header("Location: " . BASE_URL . "/login.php?error=suspended");
        exit;
    }
    if ($requireVerified && empty($user['email_verified'])) {
        header("Location: " . BASE_URL . "/verify.php");
        exit;
    }
}

function logoutUser(): void {
    unset($_SESSION['user_id']);
    unset($_SESSION['user_email']);
    session_regenerate_id(true);
}
