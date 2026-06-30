<?php
require_once __DIR__ . '/vendor/autoload.php';

try {
    $dsn = 'sqlite:' . __DIR__ . '/database/database.sqlite';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, null, null, $options);

    $count = $pdo->query('SELECT COUNT(*) FROM groups')->fetchColumn();
    echo "Connected to SQLite successfully! Groups in database: {$count}\n";

} catch (PDOException $e) {
    echo "Connection Failed: " . $e->getMessage() . "\n";
}
