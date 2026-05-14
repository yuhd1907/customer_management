<?php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'customer_management');
define('DB_USER', 'root');
define('DB_PASS', '1907');
define('DB_CHARSET', 'utf8mb4');

define('JWT_SECRET', 'crm_jwt_secret_key_2024_change_this');
define('JWT_EXPIRE', 86400); // 24 hours

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}