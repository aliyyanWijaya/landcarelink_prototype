<?php

$host = "MYSQLHOST_KAMU";
$user = "MYSQLUSER_KAMU";
$pass = "MYSQLPASSWORD_KAMU";
$name = "MYSQLDATABASE_KAMU";
$port = "MYSQLPORT_KAMU";

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);

    echo "✅ Connected to Railway DB successfully!";
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage();
}