<?php
// api/unlock-level.php - Unlocks paid level using wallet balance or activates free level
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payhero.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Please log in to continue.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid method.'], 405);
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    jsonResponse(['success' => false, 'message' => 'Security token expired. Please refresh.'], 403);
}

$user = getLoggedInUser();
$levelId = (int)($_POST['level_id'] ?? 0);

if ($levelId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid level specified.'], 422);
}

$db = getDB();

// Fetch level details
$stmt = $db->prepare("SELECT l.*, t.name as task_name, t.slug as task_slug 
                      FROM levels l 
                      JOIN tasks t ON l.task_id = t.id 
                      WHERE l.id = ? AND l.status = 'active'");
$stmt->execute([$levelId]);
$level = $stmt->fetch();

if (!$level) {
    jsonResponse(['success' => false, 'message' => 'Level not found or inactive.'], 404);
}

// Check existing user progress on this level
$progStmt = $db->prepare("SELECT * FROM user_level_progress WHERE user_id = ? AND level_id = ?");
$progStmt->execute([$user['id'], $levelId]);
$progress = $progStmt->fetch();

if ($progress && in_array($progress['status'], ['unlocked', 'completed'])) {
    jsonResponse(['success' => true, 'message' => 'Level is already unlocked.', 'redirect' => BASE_URL . "/level.php?id={$levelId}"]);
}

// If level_number > 1, verify prerequisite (previous level must be completed)
if ($level['level_number'] > 1) {
    $prevLevelStmt = $db->prepare("SELECT id FROM levels WHERE task_id = ? AND level_number = ?");
    $prevLevelStmt->execute([$level['task_id'], $level['level_number'] - 1]);
    $prevLevelId = $prevLevelStmt->fetchColumn();

    if ($prevLevelId) {
        $prevCompStmt = $db->prepare("SELECT status FROM user_level_progress WHERE user_id = ? AND level_id = ?");
        $prevCompStmt->execute([$user['id'], $prevLevelId]);
        $prevProgress = $prevCompStmt->fetch();
        if (!$prevProgress || $prevProgress['status'] !== 'completed') {
            jsonResponse(['success' => false, 'message' => "You must complete Level " . ($level['level_number'] - 1) . " before unlocking Level {$level['level_number']}."], 400);
        }
    }
}

$price = (float)$level['price'];

// If free level (price == 0), unlock directly
if ($price <= 0) {
    if ($progress) {
        $db->prepare("UPDATE user_level_progress SET status = 'unlocked', unlocked_at = NOW() WHERE id = ?")
           ->execute([$progress['id']]);
    } else {
        $db->prepare("INSERT INTO user_level_progress (user_id, level_id, status, unlocked_at) VALUES (?, ?, 'unlocked', NOW())")
           ->execute([$user['id'], $levelId]);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Free level activated successfully!',
        'redirect' => BASE_URL . "/level.php?id={$levelId}"
    ]);
}

// Paid levels are confirmed by PayHero callback before being unlocked.
$phone = normalizeKenyanPhone($user['phone']);
if ($phone === false) {
    jsonResponse(['success' => false, 'message' => 'Your account does not have a valid Kenyan M-Pesa phone number.'], 422);
}

// Auto-expire any previous pending intents older than 120 seconds
$db->prepare("UPDATE payment_intents SET status = 'failed' WHERE user_id = ? AND level_id = ? AND status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 120 SECOND)")
   ->execute([$user['id'], $levelId]);

$forceResend = !empty($_POST['force_resend']);
if (!$forceResend) {
    $pendingStmt = $db->prepare("SELECT reference FROM payment_intents WHERE user_id = ? AND level_id = ? AND status = 'pending' AND created_at >= DATE_SUB(NOW(), INTERVAL 45 SECOND) ORDER BY id DESC LIMIT 1");
    $pendingStmt->execute([$user['id'], $levelId]);
    $pendingReference = $pendingStmt->fetchColumn();
    if ($pendingReference) {
        jsonResponse([
            'success' => true, 
            'status' => 'pending', 
            'reference' => $pendingReference, 
            'message' => 'An M-Pesa payment prompt was recently sent. Please enter your PIN on your phone.'
        ]);
    }
} else {
    // If explicitly forcing resend, mark any existing pending intents as failed
    $db->prepare("UPDATE payment_intents SET status = 'failed' WHERE user_id = ? AND level_id = ? AND status = 'pending'")
       ->execute([$user['id'], $levelId]);
}

$reference = generateReference('LVL');
$callbackUrl = BASE_URL . '/api/payhero-callback.php';
if (PAYHERO_CALLBACK_SECRET !== '') {
    $callbackUrl .= '?token=' . rawurlencode(PAYHERO_CALLBACK_SECRET);
}
$insert = $db->prepare("INSERT INTO payment_intents (user_id, level_id, reference, amount, phone, status) VALUES (?, ?, ?, ?, ?, 'pending')");
$insert->execute([$user['id'], $levelId, $reference, $price, $phone]);

$payment = payHeroStartStkPush($price, $phone, $reference, $callbackUrl);
if (!$payment['success']) {
    $db->prepare("UPDATE payment_intents SET status = 'failed', callback_payload = ? WHERE reference = ?")
       ->execute([json_encode($payment), $reference]);
    jsonResponse(['success' => false, 'message' => $payment['message']], 502);
}

$db->prepare("UPDATE payment_intents SET provider_reference = ?, callback_payload = ? WHERE reference = ?")
   ->execute([
       (string)(payHeroFindValue($payment['data'], ['reference', 'transaction_id', 'checkout_request_id']) ?? ''),
       json_encode($payment['data']),
       $reference
   ]);

jsonResponse([
    'success' => true,
    'status' => 'pending',
    'reference' => $reference,
    'message' => "A KSh " . number_format($price, 2) . " M-Pesa prompt has been sent to {$phone}. Enter your M-Pesa PIN to continue."
]);
