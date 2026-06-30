<?php
function get_db_connection(): PDO
{
    $dsn = 'sqlite:' . __DIR__ . '/../../database/database.sqlite';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    return new PDO($dsn, null, null, $options);
}
