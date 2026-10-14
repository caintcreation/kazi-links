<?php
// api/register.php - AJAX Registration Endpoint with referral-tiered welcome bonus and automated email sending
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// CSRF check
$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    jsonResponse(['success' => false, 'message' => 'Session expired or invalid CSRF security token. Please refresh.'], 403);
}

try {
    ensureRegistrationSchema();
} catch (Throwable $e) {
    error_log("Registration Schema Migration Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'The account database needs a schema update before registrations can proceed. Please contact support.'], 500);
}

// Check if registration is enabled
if (getSetting('registration_enabled', '1') !== '1') {
    jsonResponse(['success' => false, 'message' => 'New user registrations are currently disabled by system administrator.'], 403);
}

$name = trim($_POST['name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$refCode = strtoupper(trim($_POST['ref'] ?? ''));

// Validation
$errors = [];

if (empty($name) || strlen($name) < 2) {
    $errors[] = "Please provide your full name (at least 2 characters).";
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Please provide a valid email address.";
}

$normalizedPhone = normalizeKenyanPhone($phone);
if (!$normalizedPhone) {
    $errors[] = "Please enter a valid Kenyan phone number (e.g., 0712345678 or 0112345678).";
}

if (strlen($password) < 6) {
    $errors[] = "Password must be at least 6 characters long.";
}

if ($password !== $confirmPassword) {
    $errors[] = "Passwords do not match.";
}

if (!empty($errors)) {
    jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);
}

$db = getDB();

// Check uniqueness
$stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
$stmt->execute([$email, $normalizedPhone]);
if ($stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'An account with that email or M-Pesa phone number already exists.'], 409);
}

// Validate referrer if supplied
$referrerId = null;
if (!empty($refCode)) {
    $refStmt = $db->prepare("SELECT id FROM users WHERE referral_code = ?");
    $refStmt->execute([$refCode]);
    $referrer = $refStmt->fetch();
    if ($referrer) {
        $referrerId = (int)$referrer['id'];
    }
}

// Determine welcome bonus amount based on referral code usage
if ($referrerId) {
    $welcomeAmount = (float)getSetting('welcome_reward_referred', '300.00');
    $welcomeDesc = "Welcome Bonus (Joined with Referral Code {$refCode})";
} else {
    $welcomeAmount = (float)getSetting('welcome_reward', '100.00');
    $welcomeDesc = "Welcome Registration Bonus";
}

// Generate unique referral code for this user
do {
    $myReferralCode = generateReferralCode();
    $codeCheck = $db->prepare("SELECT id FROM users WHERE referral_code = ?");
    $codeCheck->execute([$myReferralCode]);
} while ($codeCheck->fetch());

// Generate 6-digit verification code and verification token
$verifyCode = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
$verifyToken = bin2hex(random_bytes(32));

$passwordHash = password_hash($password, PASSWORD_BCRYPT);

try {
    $db->beginTransaction();

    // 1. Create user with unverified email and 6-digit activation code
    $insertUser = $db->prepare("INSERT INTO users (name, email, phone, password_hash, referral_code, email_verified, verification_code, verification_token, verification_expires_at, status) VALUES (?, ?, ?, ?, ?, 0, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), 'active')");
    $insertUser->execute([$name, $email, $normalizedPhone, $passwordHash, $myReferralCode, $verifyCode, $verifyToken]);
    $userId = (int)$db->lastInsertId();

    // 2. Initialize Wallet
    $db->prepare("INSERT INTO wallets (user_id, balance, pending_balance, total_earned, total_withdrawn) VALUES (?, 0.00, 0.00, 0.00, 0.00)")
       ->execute([$userId]);

    // 3. Welcome Bonus Promotion
    if ($welcomeAmount > 0) {
        $ref = generateReference('WB');
        $db->prepare("INSERT INTO transactions (user_id, reference, type, amount, direction, status, description) VALUES (?, ?, 'welcome_bonus', ?, 'credit', 'completed', ?)")
           ->execute([$userId, $ref, $welcomeAmount, $welcomeDesc]);

        $db->prepare("UPDATE wallets SET balance = balance + ?, total_earned = total_earned + ? WHERE user_id = ?")
           ->execute([$welcomeAmount, $welcomeAmount, $userId]);
    }

    // 4. Record Referral Relationship if applicable
    if (getSetting('referrals_enabled', '1') === '1' && $referrerId && $referrerId !== $userId) {
        $db->prepare("INSERT INTO referrals (referrer_id, referred_id, reward_amount, status, rewarded_at) VALUES (?, ?, ?, 'rewarded', NOW())")
           ->execute([$referrerId, $userId, (float)getSetting('referral_reward', '100.00')]);

        $referralReward = (float)getSetting('referral_reward', '100.00');
        if ($referralReward > 0) {
            $referralReference = recordTransaction(
                $referrerId,
                'referral_reward',
                $referralReward,
                'credit',
                "Referral Bonus for inviting user #{$userId}",
                'completed',
                json_encode(['referred_id' => $userId])
            );

            if (!$referralReference) {
                throw new RuntimeException('Unable to credit referral reward.');
            }
        }
    }

    $db->commit();

    // Send automated account activation email immediately with welcome message
    sendAccountVerificationEmail($email, $verifyCode, $verifyToken, $myReferralCode, $welcomeAmount, $name);

    // Save session data
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = $name;
    $_SESSION['pending_verification_email'] = $email;
    $_SESSION['flash_verify'] = "Welcome to KaziLink Jobs! A 6-digit activation code has been sent to {$email}. Please check your inbox and spam folder.";

    jsonResponse([
        'success' => true,
        'message' => "Account created! A 6-digit activation code was sent to {$email}. Redirecting...",
        'redirect' => BASE_URL . '/verify.php?email=' . urlencode($email)
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Registration Exception: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'An error occurred while creating your account. Please try again.'], 500);
}
