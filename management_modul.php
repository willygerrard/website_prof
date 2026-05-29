<?php
// 1. Amankan halaman dengan satpam session yang kemarin
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

// 2. Hubungkan ke database MariaDB internal Docker Bapak
$host = 'db';       // Menggunakan nama service di docker-compose
$db   = 'db_website_pribadi'; // Sesuaikan dengan nama DB Bapak
$user = 'willy';     // Sesuaikan dengan user DB Bapak
$pass = 'RahasiaPro2026!'; // Sesuaikan dengan password DB Bapak
$port = '3306';     // Wajib port internal karena sesama container

$dsn = "mysql:host=$host;dbname=$db;port=$port;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     
     // --- BAGIAN YANG DIGANTI/DITAMBAH ---
     // Tangkap kiriman kategori dari URL menu dropdown, kalau kosong set ke 'Semua'
     $kategori_pilihan = $_GET['kategori'] ?? 'Semua';

     if ($kategori_pilihan !== 'Semua') {
         // Jika siswa memilih kategori tertentu, saring pake query WHERE (Aman dari SQL Injection)
         $stmt = $pdo->prepare("SELECT * FROM modules WHERE category = ?");
         $stmt->execute([$kategori_pilihan]);
     } else {
         // Jika tidak memilih atau klik "Semua Materi", tampilkan semua
         $stmt = $pdo->query("SELECT * FROM modules");
     }
     
     $all_modules = $stmt->fetchAll();
     // ------------------------------------
     
} catch (\PDOException $e) {
     die("Aduh Pak, koneksi database gagal lagi: " . $e->getMessage());
}

// 1. Pastikan koneksi database sudah di-include di paling atas file
// include 'koneksi.php'; 

// 2. Query untuk mengambil semua data modul dari MariaDB
$query = $pdo->query("SELECT * FROM modules ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Manajemen Modul - SIJA</title>
    <!-- Kita panggil Bootstrap via CDN biar tabel langsung otomatis rapi dan estetik -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <!-- Judul Atas -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold m-0 text-dark">📋 Daftar Modul Pembelajaran</h4>
        
        <!-- Grup Tombol di Pojok Kanan: Kembali (Panah) dan Tambah (+) -->
        <div class="btn-group gap-2">
            <!-- Tombol Kembali Ke Index pake panah putar balik minimalis -->
            <a href="index.php" class="btn btn-outline-secondary btn-md rounded-circle fw-bold shadow-sm d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" title="Kembali ke Beranda">
                ↶
            </a>
            <!-- Tombol Tambah Data -->
            <a href="tambah_modul.php" class="btn btn-success btn-md rounded-circle fw-bold shadow-sm d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" title="Tambah Modul Baru">
                ＋
            </a>
        </div>
    </div>

    <!-- Tabel Data Modul yang Lebar dan Plong -->
    <div class="table-responsive bg-white p-4 rounded-3 shadow-sm border">
        <table class="table table-hover align-middle m-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 8%">No</th>
                    <th style="width: 57%">Nama Modul</th>
                    <th style="width: 20%">Kategori</th>
                    <th style="width: 15%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                // Looping data asli dari database
                while ($row = $query->fetch(PDO::FETCH_ASSOC)) : 
                ?>
                <tr>
                    <td><?= $no++; ?></td>
                    <td class="fw-semibold text-secondary"><?= htmlspecialchars($row['title'] ?? $row['title'] ?? ''); ?></td>
                    <td>
                        <span class="badge bg-info text-dark px-2 py-1.5"><?= htmlspecialchars($row['category'] ?? $row['category'] ?? ''); ?></span>
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <a href="edit_modul.php?id=<?= $row['id']; ?>" class="btn btn-warning fw-bold px-2.5 text-dark" title="Edit">E</a>
                            <a href="proses_hapus.php?id=<?= $row['id']; ?>" class="btn btn-danger fw-bold px-2.5" onclick="return confirm('Yakin mau hapus modul ini, Pak?')" title="Hapus">－</a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Bootstrap JS Bundle (bila nanti Bapak butuh dropdown/modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>