<?php
// api/withdrawal.php - Process withdrawal request from user wallet with 8 completed task levels verification
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Please log in to continue.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    jsonResponse(['success' => false, 'message' => 'Security token expired. Please refresh.'], 403);
}

if (getSetting('withdrawals_enabled', '1') !== '1') {
    jsonResponse(['success' => false, 'message' => 'Withdrawals are currently paused for routine platform maintenance.'], 403);
}

$user = getLoggedInUser();
$db = getDB();

// 1. Enforce requirement: User must complete at least 8 project task levels before withdrawing
$minTasksRequired = (int)getSetting('withdrawal_min_tasks', '8');
$taskCountStmt = $db->prepare("SELECT COUNT(*) FROM user_level_progress WHERE user_id = ? AND status = 'completed'");
$taskCountStmt->execute([$user['id']]);
$completedTasksCount = (int)$taskCountStmt->fetchColumn();

if ($completedTasksCount < $minTasksRequired) {
    $remaining = $minTasksRequired - $completedTasksCount;
    jsonResponse([
        'success' => false,
        'message' => "To initiate an M-Pesa withdrawal, you must complete at least {$minTasksRequired} project task levels. You have completed {$completedTasksCount} of {$minTasksRequired} tasks ({$remaining} more required).",
        'tasks_completed' => $completedTasksCount,
        'tasks_required' => $minTasksRequired
    ], 400);
}

$amount = (float)($_POST['amount'] ?? 0);
$phone = trim($_POST['phone'] ?? $user['phone']);

// 2. Validate Phone
$normalizedPhone = normalizeKenyanPhone($phone);
if (!$normalizedPhone) {
    jsonResponse(['success' => false, 'message' => 'Please enter a valid Kenyan M-Pesa phone number (e.g. 07XXXXXXXX or 01XXXXXXXX).'], 422);
}

// 3. Minimum Withdrawal Threshold (Ksh 2,500)
$minWithdrawal = (float)getSetting('minimum_withdrawal', '2500.00');
if ($amount < $minWithdrawal) {
    jsonResponse(['success' => false, 'message' => "The minimum withdrawal amount is Sh " . number_format($minWithdrawal, 2) . "."], 422);
}

$wallet = getUserWallet($user['id']);
$balance = (float)$wallet['balance'];

if ($balance < $amount) {
    jsonResponse(['success' => false, 'message' => "Insufficient wallet balance. You have Sh " . number_format($balance, 2) . " available."], 400);
}

// Check for existing pending withdrawal to prevent spamming
$checkPending = $db->prepare("SELECT id FROM withdrawals WHERE user_id = ? AND status = 'pending'");
$checkPending->execute([$user['id']]);
if ($checkPending->fetch()) {
    jsonResponse(['success' => false, 'message' => 'You already have a pending withdrawal request under review. Please wait for it to be processed.'], 409);
}

// Calculate fees (0.00 default as per reference)
$feePercent = (float)getSetting('withdrawal_fee_percent', '0.00');
$fixedFee = (float)getSetting('withdrawal_fee_fixed', '0.00');
$fee = round(($amount * ($feePercent / 100)) + $fixedFee, 2);
$netAmount = round($amount - $fee, 2);

if ($netAmount <= 0) {
    jsonResponse(['success' => false, 'message' => 'Net withdrawal amount after fee must be greater than zero.'], 422);
}

$reference = generateReference('WD');

try {
    $desc = "Withdrawal request to M-Pesa " . $normalizedPhone;
    $txRef = recordTransaction(
        $user['id'],
        'withdrawal',
        $amount,
        'debit',
        $desc,
        'pending',
        json_encode(['phone' => $normalizedPhone, 'fee' => $fee, 'net_amount' => $netAmount, 'tasks_completed' => $completedTasksCount]),
        $reference
    );

    if (!$txRef) {
        jsonResponse(['success' => false, 'message' => 'Could not lock balance for withdrawal.'], 500);
    }

    $ins = $db->prepare("INSERT INTO withdrawals (user_id, reference, amount, fee, net_amount, phone, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $ins->execute([$user['id'], $reference, $amount, $fee, $netAmount, $normalizedPhone]);

    jsonResponse([
        'success' => true,
        'message' => "Withdrawal request for Sh " . number_format($netAmount, 2) . " submitted successfully! Funds will be disbursed directly to M-Pesa {$normalizedPhone}.",
        'reference' => $reference,
        'redirect' => BASE_URL . '/wallet.php'
    ]);

} catch (Exception $e) {
    error_log("Withdrawal Exception: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'An error occurred while creating your withdrawal request.'], 500);
}
