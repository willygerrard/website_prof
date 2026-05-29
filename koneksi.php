<?php
$host = 'db';
$db   = 'db_website_pribadi';
$user = 'willy';
$pass = 'RahasiaPro2026!';
$port = '3306';

try {
    // Kita ganti nama variabelnya menjadi $pdo agar seragam
    $pdo = new PDO("mysql:host=$host;dbname=$db;port=$port;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Aduh Pak, koneksi database GAGAL ❌ Karena: " . $e->getMessage());
}
?>