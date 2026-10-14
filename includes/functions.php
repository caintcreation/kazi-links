<?php
// includes/functions.php - Core helper functions, wallet ledger, validation & security

require_once __DIR__ . '/db.php';

/**
 * Retrieve system settings from the database with cached memory lookup.
 */
function getSetting(string $key, $default = null): ?string {
    static $settingsCache = null;
    $db = getDB();

    if ($settingsCache === null) {
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
        $settingsCache = [];
        while ($row = $stmt->fetch()) {
            $settingsCache[$row['setting_key']] = $row['setting_value'];
        }
    }

    return $settingsCache[$key] ?? $default;
}

/**
 * Update or insert a system setting.
 */
function updateSetting(string $key, string $value): bool {
    $db = getDB();
    $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    $result = $stmt->execute([$value, $key]);
    return $result;
}

/**
 * Format currency with Kenyan standard (e.g. KSh 1,250.00).
 */
function formatCurrency($amount): string {
    $symbol = getSetting('currency_symbol', 'KSh');
    return $symbol . ' ' . number_format((float)$amount, 2);
}

/**
 * Format dates in a user-friendly East African format.
 */
function formatDate(?string $datetime, bool $showTime = true): string {
    if (!$datetime) return '—';
    $timestamp = strtotime($datetime);
    if ($showTime) {
        return date('M d, Y h:i A', $timestamp);
    }
    return date('M d, Y', $timestamp);
}

/**
 * Kenyan phone number validator and normalizer.
 * Supports: 07XXXXXXXX, 01XXXXXXXX, 2547XXXXXXXX, 2541XXXXXXXX, +254...
 * Returns normalized 10-digit format (07... or 01...) or false if invalid.
 */
function normalizeKenyanPhone(string $phone) {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($clean, '254') && strlen($clean) === 12) {
        $clean = '0' . substr($clean, 3);
    } elseif (str_starts_with($clean, '7') && strlen($clean) === 9) {
        $clean = '0' . $clean;
    } elseif (str_starts_with($clean, '1') && strlen($clean) === 9) {
        $clean = '0' . $clean;
    }

    // Must be 10 digits starting with 07 or 01
    if (preg_match('/^(07|01)[0-9]{8}$/', $clean)) {
        return $clean;
    }
    return false;
}

/**
 * Generate unique referral code (e.g., KZ7A91B).
 */
function generateReferralCode(): string {
    $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $code = 'KZ';
    for ($i = 0; $i < 5; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $code;
}

/**
 * Generate unique transaction / withdrawal reference (e.g. TX-20261013-894721)
 */
function generateReference(string $prefix = 'TX'): string {
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * CSRF Protection Helpers
 */
function csrfField(): string {
    $token = $_SESSION['csrf_token'] ?? '';
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrfToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Ensure registration and verification columns exist on legacy databases.
 * This keeps older installs compatible with the current account flow.
 */
if (!function_exists('ensureRegistrationSchema')) {
    function ensureRegistrationSchema(): void {
        static $schemaChecked = false;
        if ($schemaChecked) return;
        $db = getDB();

        $columnExists = function (string $column) use ($db): bool {
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'users'
                  AND COLUMN_NAME = ?
            ");
            $stmt->execute([$column]);
            return (int)$stmt->fetchColumn() > 0;
        };

        $indexExists = function (string $indexName) use ($db): bool {
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'users'
                  AND INDEX_NAME = ?
            ");
            $stmt->execute([$indexName]);
            return (int)$stmt->fetchColumn() > 0;
        };

        if (!$columnExists('name')) {
            $db->exec("ALTER TABLE users ADD COLUMN name VARCHAR(100) NULL AFTER id");
        }
        if (!$columnExists('email_verified')) {
            $db->exec("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER referral_code");
        }
    if (!$columnExists('verification_token')) {
        $db->exec("ALTER TABLE users ADD COLUMN verification_token VARCHAR(64) NULL AFTER email_verified");
    }
    if (!$columnExists('verification_code')) {
        $db->exec("ALTER TABLE users ADD COLUMN verification_code CHAR(6) NULL AFTER verification_token");
    }
    if (!$columnExists('verification_expires_at')) {
        $db->exec("ALTER TABLE users ADD COLUMN verification_expires_at DATETIME NULL AFTER verification_code");
    }
    if (!$indexExists('idx_users_verification_token')) {
        $db->exec("ALTER TABLE users ADD INDEX idx_users_verification_token (verification_token)");
    }

    $db->exec("
        INSERT INTO settings (setting_key, setting_value, setting_group, description)
        VALUES (
            'welcome_reward_referred',
            '300.00',
            'financial',
            'Welcome bonus for users who join with a valid referral code (KES)'
        )
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            setting_group = VALUES(setting_group),
            description = VALUES(description)
    ");

        $schemaChecked = true;
    }
}

/**
 * Output escaping helper
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Wallet Ledger Core Logic
 * All balance updates must proceed through this function with an accompanying transaction record.
 */
function recordTransaction(
    int $userId,
    string $type,
    float $amount,
    string $direction, // 'credit' or 'debit'
    string $description,
    string $status = 'completed',
    ?string $metadata = null,
    ?string $customRef = null
) {
    $db = getDB();
    $amount = round($amount, 2);

    if ($amount <= 0) {
        return false;
    }

    $reference = $customRef ?? generateReference();

    $manageTransaction = !$db->inTransaction();
    if ($manageTransaction) {
        $db->beginTransaction();
    }
    try {
        // Ensure wallet exists and lock for update
        $stmt = $db->prepare("SELECT balance, pending_balance, total_earned, total_withdrawn FROM wallets WHERE user_id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch();

        if (!$wallet) {
            $db->prepare("INSERT INTO wallets (user_id, balance, pending_balance, total_earned, total_withdrawn) VALUES (?, 0.00, 0.00, 0.00, 0.00)")
               ->execute([$userId]);
            $wallet = ['balance' => 0.00, 'pending_balance' => 0.00, 'total_earned' => 0.00, 'total_withdrawn' => 0.00];
        }

        $currentBalance = (float)$wallet['balance'];
        $pendingBalance = (float)$wallet['pending_balance'];
        $totalEarned = (float)$wallet['total_earned'];
        $totalWithdrawn = (float)$wallet['total_withdrawn'];

        // If debit and completed, verify sufficient balance
        if ($direction === 'debit' && $status === 'completed' && $currentBalance < $amount) {
            $db->rollBack();
            return false;
        }

        // Insert Transaction
        $txStmt = $db->prepare("INSERT INTO transactions 
            (user_id, reference, type, amount, direction, status, description, metadata) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $txStmt->execute([
            $userId,
            $reference,
            $type,
            $amount,
            $direction,
            $status,
            $description,
            $metadata
        ]);

        // Ledger calculation based on transaction type and status
        if ($status === 'completed') {
            if ($direction === 'credit') {
                $newBalance = $currentBalance + $amount;
                $newEarned = $totalEarned + ($type !== 'adjustment' ? $amount : 0);
                $updateStmt = $db->prepare("UPDATE wallets SET balance = ?, total_earned = ? WHERE user_id = ?");
                $updateStmt->execute([$newBalance, $newEarned, $userId]);
            } elseif ($direction === 'debit') {
                $newBalance = $currentBalance - $amount;
                $newWithdrawn = ($type === 'withdrawal') ? ($totalWithdrawn + $amount) : $totalWithdrawn;
                $updateStmt = $db->prepare("UPDATE wallets SET balance = ?, total_withdrawn = ? WHERE user_id = ?");
                $updateStmt->execute([$newBalance, $newWithdrawn, $userId]);
            }
        } elseif ($status === 'pending' && $type === 'withdrawal') {
            // Deduct from available balance, add to pending balance
            if ($currentBalance < $amount) {
                $db->rollBack();
                return false;
            }
            $newBalance = $currentBalance - $amount;
            $newPending = $pendingBalance + $amount;
            $updateStmt = $db->prepare("UPDATE wallets SET balance = ?, pending_balance = ? WHERE user_id = ?");
            $updateStmt->execute([$newBalance, $newPending, $userId]);
        }

        if ($manageTransaction) {
            $db->commit();
        }
        return $reference;
    } catch (Exception $e) {
        if ($manageTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Transaction Ledger Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch wallet summary for a user.
 */
function getUserWallet(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT balance, pending_balance, total_earned, total_withdrawn FROM wallets WHERE user_id = ?");
    $stmt->execute([$userId]);
    $wallet = $stmt->fetch();
    if (!$wallet) {
        $db->prepare("INSERT INTO wallets (user_id, balance, pending_balance, total_earned, total_withdrawn) VALUES (?, 0.00, 0.00, 0.00, 0.00)")
           ->execute([$userId]);
        return ['balance' => 0.00, 'pending_balance' => 0.00, 'total_earned' => 0.00, 'total_withdrawn' => 0.00];
    }
    return $wallet;
}

/**
 * Check and reward referrer if conditions are met.
 */
function checkAndAwardReferralReward(int $userId): void {
    // Referral rewards are issued during registration, once per referred account.
}

/**
 * Log Admin Action
 */
function logAdminAudit(int $adminId, string $action, ?string $targetType = null, ?int $targetId = null, ?string $details = null): void {
    $db = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $db->prepare("INSERT INTO admin_audit_logs (admin_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$adminId, $action, $targetType, $targetId, $details, $ip]);
}

/**
 * JSON response helper
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
