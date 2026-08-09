<?php
// 1. Hidupkan Session di baris paling atas
session_start();
include 'koneksi.php'; // $pdo sudah tersedia dari sini
include 'csrf_helper.php';

// 2. Pastikan data dikirim lewat Method POST dari form login
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
        session_regenerate_id(true);

        $_SESSION['is_login'] = true;
        $_SESSION['username'] = $user['username'];
        // Setelah password_verify sukses
        $_SESSION['role']     = $user['role']; // <-- Catat status admin/siswa di sini
        $_SESSION['user_id'] = $user['id'];  // tambah ini
        
        // Simpan kelas asli siswa (misal "X TKJ 1") ke session
        $_SESSION['kelas']   = $user['kelas'] ?? '';
        // Ambil tingkatnya aja ("X TKJ 1" -> "X") dan simpan juga, biar index.php tinggal pakai langsung
        $_SESSION['tingkat'] = explode(' ', trim($_SESSION['kelas']))[0];

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