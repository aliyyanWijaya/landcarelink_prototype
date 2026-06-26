<?php


function get_db_connection(): PDO
{
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT');
    $name = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    if ($pass === false) {
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
