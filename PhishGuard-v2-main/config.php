
<?php

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'phishguard');
define('DB_USER', 'root');
define('DB_PASS', '');

// PDO database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    error_log($e->getMessage());
    die("Database connection failed.");
}
