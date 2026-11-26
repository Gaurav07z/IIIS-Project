<?php

// Read database settings from environment variables.
// If no environment variables exist, use local XAMPP settings.

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'foodloop';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$port = getenv('DB_PORT') ?: '3306';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];

    // Enable TLS certificate verification for TiDB Cloud.
    if ($host !== 'localhost' && $host !== '127.0.0.1') {
        $options[PDO::MYSQL_ATTR_SSL_CA] =
            getenv('DB_SSL_CA') ?: '/etc/ssl/certs/isrgrootx1.pem';
    }

    $conn = new PDO($dsn, $username, $password, $options);

} catch (PDOException $e) {
    error_log("FoodLoop database connection failed: " . $e->getMessage());

    http_response_code(500);
    exit("Database connection failed. Please check the server configuration.");
}
?>