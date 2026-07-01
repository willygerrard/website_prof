<?php
// 1. Hidupkan Session di baris paling atas
session_start();
include 'koneksi.php';
include 'csrf_helper.php';

// 2. Konfigurasi Koneksi MariaDB Docker Bapak (Port 3307)
$host = 'db';
$port = '3306'; 
$db   = 'db_website_pribadi';
$user_db = 'willy'; // Kita ganti nama variabel koneksinya biar gak tabrakan sama $user database
$pass_db = 'RahasiaPro2026!';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // Proteksi mutlak Anti SQL Injection
];

try {
     $pdo = new PDO($dsn, $user_db, $pass_db, $options);
} catch (\PDOException $e) {
     die("Koneksi Database Gagal: " . $e->getMessage());
}

// 3. Pastikan data dikirim lewat Method POST dari form login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid_post();
    // Ambil input dan bersihkan (Proteksi XSS Dasar)
    $user_input = htmlspecialchars(trim($_POST['username'] ?? ''));
    $pass_input = trim($_POST['password'] ?? '');

    // 4. Jalankan Prepared Statement untuk mencari User
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$user_input]);
    
    // DI SINI VARIABEL $user RESMI DI-DEFINISIKAN SEBAGAI HASIL DATABASE!
    $user = $stmt->fetch();

    // 5. Baru lakukan verifikasi Password Hash BCRYPT
    if ($user && password_verify($pass_input, $user['password'])) {
        if (($user['status'] ?? 'aktif') === 'nonaktif') {
        header("Location: login.php?error=nonaktif");
        exit();
    }
        // LOGIN SUKSES! Buat tanda bukti session
        $_SESSION['is_login'] = true;
        $_SESSION['username'] = $user['username'];
        // Setelah password_verify sukses
        $_SESSION['role']     = $user['role']; // <-- Catat status admin/siswa di sini
        $_SESSION['user_id'] = $user['id'];  // tambah ini
        // Oper langsung masuk ke halaman utama Bootstrap (index.php)
        header("Location: index.php");
        exit();
    } else {
        // LOGIN GAGAL! Balikkan ke gerbang login dengan pesan eror
        header("Location: login.php?error=Username atau Password Salah!");
        exit();
    }
} else {
    // Kalau ada yang coba akses file ini langsung tanpa POST, usir ke login.php
    header("Location: login.php");
    exit();
}