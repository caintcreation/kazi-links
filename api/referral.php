<?php
// api/referral.php - Fetches referral statistics and records referral validation
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$user = getLoggedInUser();
$db = getDB();

$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_invited,
        SUM(CASE WHEN status IN ('eligible', 'rewarded') THEN 1 ELSE 0 END) as total_eligible,
        SUM(CASE WHEN status = 'rewarded' THEN reward_amount ELSE 0 END) as total_earnings
    FROM referrals 
    WHERE referrer_id = ?
");
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();

jsonResponse([
    'success' => true,
    'referral_code' => $user['referral_code'],
    'referral_link' => BASE_URL . '/register.php?ref=' . urlencode($user['referral_code']),
    'total_invited' => (int)($stats['total_invited'] ?? 0),
    'total_eligible' => (int)($stats['total_eligible'] ?? 0),
    'total_earnings' => (float)($stats['total_earnings'] ?? 0.00),
    'formatted_earnings' => formatCurrency($stats['total_earnings'] ?? 0.00)
]);
