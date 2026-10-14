<?php
// api/contact.php - Customer-care message endpoint
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'Your session expired. Please refresh and try again.'], 403);
}

$name = trim($_POST['name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

$errors = [];
if ($name === '' || strlen($name) > 100) {
    $errors[] = 'Please enter your name.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191) {
    $errors[] = 'Please enter a valid email address.';
}
if ($subject === '' || strlen($subject) > 150) {
    $errors[] = 'Please enter a subject.';
}
if ($message === '' || strlen($message) > 5000) {
    $errors[] = 'Please enter a message of no more than 5,000 characters.';
}

if ($errors) {
    jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);
}

if (!sendCustomerCareEmail($email, $name, $subject, $message)) {
    jsonResponse([
        'success' => false,
        'message' => 'We could not send your message right now. Please try again later.'
    ], 502);
}

jsonResponse([
    'success' => true,
    'message' => 'Your message was sent to customer care. We will respond to ' . $email . '.'
]);
