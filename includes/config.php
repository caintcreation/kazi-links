<?php
// includes/config.php - Central configuration file for KaziLink

function configValue(string $name, string $default = ''): string {
    $value = getenv($name);
    return $value === false ? $default : $value;
}

// String polyfills for maximum server compatibility (PHP 7.4 through PHP 8.3+)
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

// Session settings with fallback
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.use_only_cookies', '1');
    @session_start();
}

// Timezone
date_default_timezone_set('Africa/Nairobi');

// Detect environment: Live hosting (InfinityFree / kesug.com) vs Local development (localhost/XAMPP)
$httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isInfinityFree = (strpos($httpHost, 'kesug.com') !== false || strpos($httpHost, 'infinityfree') !== false);

if ($isInfinityFree) {
    define('ENVIRONMENT', 'production');
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    // Set these values as server environment variables before deployment.
    define('DB_HOST', configValue('KAZILINK_DB_HOST'));
    define('DB_NAME', configValue('KAZILINK_DB_NAME'));
    define('DB_USER', configValue('KAZILINK_DB_USER'));
    define('DB_PASS', configValue('KAZILINK_DB_PASS'));
    define('DB_PORT', (int)configValue('KAZILINK_DB_PORT', '3306'));
    define('DB_CHARSET', configValue('KAZILINK_DB_CHARSET', 'utf8mb4'));

    // Live URL
    define('BASE_URL', 'https://kazilinks.kesug.com');
} else {
    define('ENVIRONMENT', 'development');
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    // Local XAMPP MySQL Database Credentials
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'kazilink');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_PORT', 3306);
    define('DB_CHARSET', 'utf8mb4');

    // Local Base URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    define('BASE_URL', $protocol . $httpHost . '/kazilink');
}

define('SITE_NAME_DEFAULT', 'KaziLink Kenya');

// SMTP Email Settings (Gmail App Password)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_USER', configValue('KAZILINK_SMTP_USER'));
define('SMTP_PASS', configValue('KAZILINK_SMTP_PASS'));
define('SMTP_FROM_EMAIL', configValue('KAZILINK_SMTP_FROM_EMAIL', SMTP_USER));
define('SMTP_FROM_NAME', configValue('KAZILINK_SMTP_FROM_NAME', 'KaziLink Kenya'));
define('SUPPORT_EMAIL', configValue('KAZILINK_SUPPORT_EMAIL', SMTP_FROM_EMAIL));

// PayHero Kenya M-Pesa STK Push API Credentials
define('PAYHERO_API_USERNAME', configValue('KAZILINK_PAYHERO_USERNAME'));
define('PAYHERO_API_PASSWORD', configValue('KAZILINK_PAYHERO_PASSWORD'));
define('PAYHERO_CHANNEL_ID', (int)configValue('KAZILINK_PAYHERO_CHANNEL_ID', '0'));
define('PAYHERO_API_BASE_URL', configValue('KAZILINK_PAYHERO_BASE_URL', 'https://backend.payhero.co.ke/api/v2'));
define('PAYHERO_BASIC_AUTH', configValue('KAZILINK_PAYHERO_BASIC_AUTH'));
define('PAYHERO_CALLBACK_SECRET', configValue('KAZILINK_PAYHERO_CALLBACK_SECRET'));

// Security CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
