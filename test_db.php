<?php
$host = '127.0.0.1';
$port = 3306;
$dbname = 'tiao2_db';
$user = 'root';
$pass = 'localhost:tiao2';
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    echo "数据库连接成功！\n";
} catch (Exception $e) {
    echo "连接失败: " . $e->getMessage() . "\n";
}
