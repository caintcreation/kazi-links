<?php
// api/login.php - AJAX Login Endpoint
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// CSRF check
$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    jsonResponse(['success' => false, 'message' => 'Session expired. Please refresh the page.'], 403);
}

$emailOrPhone = trim($_POST['email_or_phone'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($emailOrPhone) || empty($password)) {
    jsonResponse(['success' => false, 'message' => 'Please enter your email/phone and password.'], 422);
}

$db = getDB();

// Normalize if entered phone format
$normalizedPhone = normalizeKenyanPhone($emailOrPhone);

if ($normalizedPhone) {
    $stmt = $db->prepare("SELECT id, email, phone, password_hash, status FROM users WHERE phone = ? OR email = ?");
    $stmt->execute([$normalizedPhone, $emailOrPhone]);
} else {
    $stmt = $db->prepare("SELECT id, email, phone, password_hash, status FROM users WHERE email = ?");
    $stmt->execute([$emailOrPhone]);
}

$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid login credentials. Please check your details.'], 401);
}

if ($user['status'] === 'suspended') {
    jsonResponse(['success' => false, 'message' => 'Your account is suspended. Please contact support.'], 403);
}

// Successful login
session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_email'] = $user['email'];

jsonResponse([
    'success' => true,
    'message' => 'Welcome back!',
    'redirect' => BASE_URL . '/dashboard.php'
]);
