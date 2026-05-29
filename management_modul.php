<?php
// Pastikan koneksi database dan session start sudah aman di paling atas
include 'koneksi.php';
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}
// Query ambil data untuk tabel
$query = $pdo->query("SELECT * FROM modules ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Module - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Style Jumbotron/Hero Banner penunjang gambar background server rack */
        .hero-banner {
            background: linear-gradient(rgba(15, 23, 42, 0.85), rgba(15, 23, 42, 0.85)), url('path_gambar_server_bapak.jpg') no-repeat center center;
            background-size: cover;
            color: white;
            padding: 80px 0;
            text-align: center;
        }
    </style>
</head>
<body class="bg-light">

    <!-- 1. NAVBAR (Sama persis seperti index.php Bapak) -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container px-4 px-lg-5">
                <a class="navbar-brand" href="index.php">Modul Pembelajaran SIJA</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <?php if (isset($_SESSION['username'])) : ?>
                     <span class="text-secondary fw-medium d-none d-md-inline small">
                         👋 Hai, <strong class="text-dark"><?= htmlspecialchars($_SESSION['username']); ?></strong>
                    </span>
                 <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

        <!-- Header-->
 <header class="py-5" style="
    background: linear-gradient(to bottom, rgba(15, 23, 42, 0.85), rgba(30, 41, 59, 0.9)), 
                url('https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=800'); 
    background-size: cover; 
    background-position: center;">
    
    <div class="container px-4 px-lg-5 my-5">
        <div class="text-center text-white">
            <h1 class="display-4 fw-bolder"> 
                Pusat Pembelajaran SIJA
            </h1>
            <p class="lead fw-normal text-white-50 mb-0">
               Selamat datang di portal lab kendali materi mandiri</p>
            </div>
    </div>
</header>
    <!-- 3. KONTEN UTAMA (Kartu materi hilang, ganti MENU TABEL MANAJEMEN) -->
    <div class="container mt-5 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold m-0 text-dark">📋 Daftar Modul Pembelajaran</h4>
            <div class="btn-group gap-2">
                <a href="index.php" class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Kembali ke Beranda">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
                <a href="tambah_modul.php" class="btn btn-success rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Tambah Modul Baru">
                    <i class="bi bi-plus-lg"></i>
                </a>
            </div>
        </div>

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
                    <?php $no = 1; while ($row = $query->fetch(PDO::FETCH_ASSOC)) : ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td class="fw-semibold text-secondary"><?= htmlspecialchars($row['title'] ?? ''); ?></td>
                        <td><span class="badge bg-info text-dark px-2.5 py-1.5"><?= htmlspecialchars($row['category'] ?? ''); ?></span></td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="edit_modul.php?id=<?= $row['id']; ?>" class="btn btn-warning fw-bold text-dark px-2.5">E</a>
                                <a href="proses_hapus.php?id=<?= $row['id']; ?>" class="btn btn-danger fw-bold px-2.5" onclick="return confirm('Yakin hapus, Pak?')">－</a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
     <!-- Footer-->
        <footer class="py-5 bg-dark">
            <div class="container"><p class="m-0 text-center text-white">Copyright &copy; SIJA Website 2026</p></div>
        </footer>
        <!-- Bootstrap core JS-->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>