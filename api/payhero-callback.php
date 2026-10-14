<?php
// api/payhero-callback.php - PayHero M-Pesa Callback Webhook
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payhero.php';

header('Content-Type: application/json; charset=utf-8');

// Ensure log directory exists
$logDir = dirname(__DIR__) . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/payhero.log';

// Allow GET for webhook testing / health check
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonResponse(['success' => true, 'message' => 'KaziLink PayHero Webhook Endpoint is active.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// Check callback secret token if configured
if (PAYHERO_CALLBACK_SECRET !== '' && !hash_equals(PAYHERO_CALLBACK_SECRET, (string)($_GET['token'] ?? ''))) {
    jsonResponse(['success' => false, 'message' => 'Invalid callback token.'], 403);
}

$raw = file_get_contents('php://input');
@file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] PAYHERO CALLBACK RECEIVED:\n" . $raw . "\n\n", FILE_APPEND);

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    if (!empty($_POST)) {
        $payload = $_POST;
    } else {
        jsonResponse(['success' => false, 'message' => 'Invalid callback payload.'], 400);
    }
}

// Extract payment details from PayHero webhook payload
$reference = trim((string)(payHeroFindValue($payload, ['external_reference', 'reference', 'merchant_reference', 'ExternalReference']) ?? ''));
$status = strtolower(trim((string)(payHeroFindValue($payload, ['status', 'payment_status', 'transaction_status', 'Status']) ?? '')));
$providerReference = trim((string)(payHeroFindValue($payload, ['receipt', 'receipt_number', 'transaction_id', 'provider_reference', 'mpesa_reference', 'mpesa_receipt', 'MpesaReceiptNumber']) ?? ''));
$callbackAmount = payHeroFindValue($payload, ['amount', 'paid_amount', 'transaction_amount', 'Amount']);
$resultCode = payHeroFindValue($payload, ['result_code', 'ResultCode', 'response_code', 'Resultcode']);
$resultDesc = (string)(payHeroFindValue($payload, ['result_desc', 'ResultDesc', 'description', 'message', 'error', 'errorMessage', 'status_message']) ?? '');

if ($reference === '') {
    @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ERROR: Missing external reference in payload\n", FILE_APPEND);
    jsonResponse(['success' => false, 'message' => 'Missing payment reference.'], 422);
}

$successfulStatuses = ['success', 'successful', 'paid', 'completed', 'complete', 'ok'];
$failedStatuses = ['failed', 'failure', 'cancelled', 'canceled', 'rejected', 'declined'];

// Safaricom ResultCode 0 or 000 is always success
$isSuccessCode = ($resultCode !== null && ((string)$resultCode === '0' || (string)$resultCode === '000'));
$isErrorCode = ($resultCode !== null && (string)$resultCode !== '' && (string)$resultCode !== '0' && (string)$resultCode !== '000');

if ($isSuccessCode || in_array($status, $successfulStatuses, true)) {
    $newStatus = 'paid';
} elseif ($isErrorCode || in_array($status, $failedStatuses, true) || str_contains(strtolower($resultDesc), 'cancel')) {
    $newStatus = 'failed';
} else {
    $newStatus = 'pending';
}

$db = getDB();
$db->beginTransaction();

try {
    $stmt = $db->prepare('SELECT * FROM payment_intents WHERE reference = ? FOR UPDATE');
    $stmt->execute([$reference]);
    $payment = $stmt->fetch();

    if (!$payment) {
        $db->rollBack();
        @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ERROR: Payment intent not found for reference: {$reference}\n", FILE_APPEND);
        jsonResponse(['success' => false, 'message' => 'Payment reference not found.'], 404);
    }

    if ($payment['status'] !== 'paid') {
        $update = $db->prepare("UPDATE payment_intents SET status = ?, provider_reference = ?, callback_payload = ?, paid_at = CASE WHEN ? = 'paid' THEN NOW() ELSE paid_at END WHERE id = ?");
        $update->execute([$newStatus, $providerReference ?: null, $raw, $newStatus, $payment['id']]);

        if ($newStatus === 'paid') {
            // Unlock the level for user
            $progressStmt = $db->prepare('SELECT id, status FROM user_level_progress WHERE user_id = ? AND level_id = ? FOR UPDATE');
            $progressStmt->execute([$payment['user_id'], $payment['level_id']]);
            $progress = $progressStmt->fetch();

            if (!$progress) {
                $db->prepare("INSERT INTO user_level_progress (user_id, level_id, status, unlocked_at) VALUES (?, ?, 'unlocked', NOW())")
                    ->execute([$payment['user_id'], $payment['level_id']]);
            } elseif ($progress['status'] === 'locked') {
                $db->prepare("UPDATE user_level_progress SET status = 'unlocked', unlocked_at = NOW() WHERE id = ?")
                    ->execute([$progress['id']]);
            }

            // Record transaction ledger debit/entry for level unlock
            $txRef = generateReference('LVL');
            $txStmt = $db->prepare("INSERT INTO transactions (user_id, reference, type, amount, direction, status, description, metadata) 
                                    VALUES (?, ?, 'level_unlock', ?, 'debit', 'completed', ?, ?)");
            $txStmt->execute([
                $payment['user_id'],
                $txRef,
                $payment['amount'],
                "Paid Level Unlock via M-Pesa ({$reference})",
                json_encode(['provider_ref' => $providerReference, 'intent_id' => $payment['id']])
            ]);

            @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] SUCCESS: Level {$payment['level_id']} unlocked for User {$payment['user_id']}\n", FILE_APPEND);
        }
    }

    $db->commit();
    jsonResponse(['success' => true, 'message' => 'Callback processed successfully.']);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
    jsonResponse(['success' => false, 'message' => 'Callback processing failed: ' . $e->getMessage()], 500);
}
