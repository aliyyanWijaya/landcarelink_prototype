<?php
/**
 * Database connection (PDO, MySQL).
 *
 * Returns a configured PDO instance. Connection details are read from
 * environment variables so the same code runs locally and in tests/CI:
 *
 *   DB_HOST (default 127.0.0.1)
 *   DB_PORT (default 3306)
 *   DB_NAME (default landcarelink)
 *   DB_USER (default root)
 *   DB_PASS (default empty)
 */

function get_db_connection(): PDO
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'landcarelink';
    $user = getenv('DB_USER') ?: 'landcarelink_user';
    $pass = getenv('DB_PASS') ?: 'passwordkuat123';
    if ($pass === false) {
        $pass = '';
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements only
    ];

    return new PDO($dsn, $user, $pass, $options);
}
