<?php
// api/payment-status.php - Payment status polling & active PayHero verification
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payhero.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Please log in to continue.'], 401);
}

$reference = trim($_GET['reference'] ?? '');
if ($reference === '') {
    jsonResponse(['success' => false, 'message' => 'Payment reference is required.'], 422);
}

$user = getLoggedInUser();
$db = getDB();

$stmt = $db->prepare('SELECT *, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age_seconds FROM payment_intents WHERE user_id = ? AND reference = ?');
$stmt->execute([$user['id'], $reference]);
$payment = $stmt->fetch();

if (!$payment) {
    jsonResponse(['success' => false, 'message' => 'Payment request not found.'], 404);
}

// 1. If already marked paid, return success immediately
if ($payment['status'] === 'paid') {
    jsonResponse([
        'success' => true,
        'status' => 'paid',
        'message' => 'Payment confirmed! Level unlocked.',
        'redirect' => BASE_URL . '/level.php?id=' . (int)$payment['level_id'],
    ]);
}

// 2. If marked failed (e.g. via PayHero webhook callback), return detailed error
if ($payment['status'] === 'failed') {
    $failure = payHeroParseFailureDetails($payment['callback_payload'] ?? '');
    jsonResponse([
        'success' => false,
        'status' => 'failed',
        'reason' => $failure['reason'],
        'title' => $failure['title'],
        'message' => $failure['message'],
        'redirect' => null,
    ]);
}

// Calculate elapsed seconds safely via MySQL's TIMESTAMPDIFF
$elapsedSeconds = isset($payment['age_seconds']) && $payment['age_seconds'] !== null
    ? (int)$payment['age_seconds']
    : (time() - strtotime($payment['created_at']));

// Prevent negative elapsed seconds if server clocks vary slightly
if ($elapsedSeconds < 0) {
    $elapsedSeconds = 0;
}

$isManualVerify = !empty($_GET['manual']) || !empty($_GET['force_check']);

// 3. Active verification: Query PayHero API directly if manual button clicked or periodically after initial 6s
if ($isManualVerify || ($elapsedSeconds >= 6 && ($elapsedSeconds % 6 === 0 || $elapsedSeconds > 25))) {
    $verification = payHeroVerifyTransaction($reference, $payment['phone'] ?? '', (float)($payment['amount'] ?? 0));

    if (!empty($verification['verified'])) {
        $providerRef = $verification['provider_reference'] ?? '';

        $db->beginTransaction();
        try {
            // Mark payment intent as paid
            $db->prepare("UPDATE payment_intents SET status = 'paid', provider_reference = ?, paid_at = NOW() WHERE id = ?")
               ->execute([$providerRef, $payment['id']]);

            // Unlock user level
            $progStmt = $db->prepare('SELECT id, status FROM user_level_progress WHERE user_id = ? AND level_id = ? FOR UPDATE');
            $progStmt->execute([$payment['user_id'], $payment['level_id']]);
            $progress = $progStmt->fetch();

            if (!$progress) {
                $db->prepare("INSERT INTO user_level_progress (user_id, level_id, status, unlocked_at) VALUES (?, ?, 'unlocked', NOW())")
                   ->execute([$payment['user_id'], $payment['level_id']]);
            } elseif ($progress['status'] === 'locked') {
                $db->prepare("UPDATE user_level_progress SET status = 'unlocked', unlocked_at = NOW() WHERE id = ?")
                   ->execute([$progress['id']]);
            }

            // Record transaction ledger debit for level unlock
            $txRef = generateReference('LVL');
            $txStmt = $db->prepare("INSERT INTO transactions (user_id, reference, type, amount, direction, status, description, metadata) 
                                    VALUES (?, ?, 'level_unlock', ?, 'debit', 'completed', ?, ?)");
            $txStmt->execute([
                $payment['user_id'],
                $txRef,
                $payment['amount'],
                "Paid Level Unlock via M-Pesa ({$reference})",
                json_encode(['provider_ref' => $providerRef, 'intent_id' => $payment['id']])
            ]);

            $db->commit();

            jsonResponse([
                'success' => true,
                'status' => 'paid',
                'message' => 'Payment confirmed! Level unlocked.',
                'redirect' => BASE_URL . '/level.php?id=' . (int)$payment['level_id'],
            ]);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        }
    }
}

// 4. Check for timeout (gives generous humble time of 120 seconds before timing out)
if ($elapsedSeconds > 120) {
    $db->prepare("UPDATE payment_intents SET status = 'failed' WHERE id = ? AND status = 'pending'")
       ->execute([$payment['id']]);

    jsonResponse([
        'success' => false,
        'status' => 'failed',
        'reason' => 'timeout',
        'title' => 'Payment Timed Out',
        'message' => 'No payment was detected within 2 minutes. If you did not enter your PIN or cancelled the prompt, please click Try Again.',
        'redirect' => null,
    ]);
}

// 5. Payment is still pending user entering their PIN on phone
jsonResponse([
    'success' => true,
    'status' => 'pending',
    'seconds_elapsed' => $elapsedSeconds,
    'seconds_remaining' => max(0, 120 - $elapsedSeconds),
    'message' => 'Waiting for you to enter your M-Pesa PIN on your phone...',
    'redirect' => null,
]);
