<?php
// includes/db.php - PDO Database connection singleton

require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (!extension_loaded('pdo_mysql')) {
            $message = 'The PHP pdo_mysql extension is not enabled. Start KaziLink with C:\\xampp\\php\\php.exe or enable pdo_mysql in the active php.ini.';
            error_log($message);
            if (ENVIRONMENT === 'development') {
                die($message);
            }
            die("System maintenance is currently in progress. Please check back shortly.");
        }

        $portPart = (!empty(DB_PORT) && DB_PORT != 3306) ? ";port=" . DB_PORT : "";
        $dsn = "mysql:host=" . DB_HOST . $portPart . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 10,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("<div style='font-family:sans-serif;padding:30px;max-width:600px;margin:40px auto;border:1px solid #e2e8f0;border-radius:12px;background:#fef2f2;color:#991b1b;'>
                <h3 style='margin-top:0;'>Database Connection Error</h3>
                <p>" . htmlspecialchars($e->getMessage()) . "</p>
                <p style='font-size:13px;color:#64748b;'>Database: <strong>" . DB_NAME . "</strong> | Host: <strong>" . DB_HOST . "</strong></p>
                <p style='font-size:13px;color:#64748b;'>Make sure your database is created in InfinityFree cPanel and tables imported via phpMyAdmin.</p>
            </div>");
        }
    }
    return $pdo;
}
