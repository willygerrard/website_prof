<?php
session_start();
// Pastikan koneksi database dan session start sudah aman di paling atas
include 'koneksi.php';
include 'csrf_helper.php';

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("HTTP/1.1 404 Not Found");
    exit();
}

// Cek apakah user mlebu lewat URL samaran /gerbang-rahasia-sija
if (strpos($_SERVER['REQUEST_URI'], 'gerbang-rahasia-sija') === false) {
    // Nek nekat ngetik management_modul.php langsung, usir nggae 404!
    header("HTTP/1.1 404 Not Found");
    exit();
}

// Query ambil data untuk tabel
$query = $pdo->query("SELECT * FROM modules ORDER BY id DESC");
$delete_token = csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Module - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

    <?php include __DIR__ . '/includes/admin_header.php'; ?>
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
                                <a href="hapus.php?id=<?= $row['id']; ?>&csrf_token=<?= urlencode($delete_token); ?>" class="btn btn-danger fw-bold px-2.5" onclick="return confirm('Yakin hapus, Pak?')"><i class="bi bi-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php include __DIR__ . '/includes/footer.php'; ?>
        <!-- Bootstrap core JS-->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>