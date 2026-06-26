<?php
function get_db_connection(): PDO
{
    $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
    $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT');
    $name = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
    $user = $_ENV['DB_USER'] ?? getenv('DB_USER');
    $pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS');

    if ($pass === false || $pass === null) {
        throw new RuntimeException('DB_PASS env var is not set yet.');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements only
    ];
    return new PDO($dsn, $user, $pass, $options);
}