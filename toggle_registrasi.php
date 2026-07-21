<?php
include 'koneksi.php';
session_start();
include 'csrf_helper.php';

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    header("HTTP/1.1 404 Not Found");
    exit();
}

// Proteksi akses via URL samaran
if (strpos($_SERVER['REQUEST_URI'], 'pintu-pendaftaran-sija') === false) {
    header("HTTP/1.1 404 Not Found");
    exit();
}

$pesan = '';
$pesan_type = '';

// Toggle status registrasi (BUKA / TUTUP)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle'])) {
    csrf_require_valid_post();
    
    $status_baru = $_POST['toggle'] === 'buka' ? 'buka' : 'tutup';

    $stmt = $pdo->prepare("UPDATE pengaturan SET `value` = ? WHERE `key` = 'registrasi_status'");
    $stmt->execute([$status_baru]);

    if ($status_baru === 'buka') {
        $pesan = "🟢 Pendaftaran siswa DIBUKA! Siswa bisa daftar menggunakan token yang aktif.";
        $pesan_type = 'success';
    } else {
        $pesan = "🔴 Pendaftaran siswa DITUTUP! Hanya token yang cocok dengan 'Token Aktif' yang bisa daftar — tapi karena ditutup, semua ditolak.";
        $pesan_type = 'secondary';
    }
}

// Update token aktif
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_token'])) {
    csrf_require_valid_post();
    
    $token_baru = trim($_POST['token_baru'] ?? '');
    
    if (strlen($token_baru) < 3) {
        $pesan = "⚠️ Token minimal 3 karakter!";
        $pesan_type = 'danger';
    } else {
        // Gunakan INSERT ... ON DUPLICATE KEY UPDATE agar aman kalau baris belum ada di tabel
        $sql = "INSERT INTO pengaturan (`key`, `value`) VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";
        
        $stmt1 = $pdo->prepare($sql);
        $stmt1->execute(['registrasi_token_sekarang', $token_baru]);
        
        $stmt2 = $pdo->prepare($sql);
        $stmt2->execute(['registrasi_token', $token_baru]);

        $pesan = "✅ Token berhasil diperbarui menjadi: <strong>" . htmlspecialchars($token_baru) . "</strong>";
        $pesan_type = 'success';
        
        // 🚨 KUNCI PERBAIKAN: Hapus $_POST['token_baru'] dari memori agar form tidak nempel nilai lama!
        unset($_POST['token_baru']);
    }
}

// Ambil status & token terkini langsung dari database
$status = $pdo->query("SELECT `value` FROM pengaturan WHERE `key` = 'registrasi_status'")->fetchColumn();
$token_sekarang = $pdo->query("SELECT `value` FROM pengaturan WHERE `key` = 'registrasi_token_sekarang'")->fetchColumn();

// Fallback jika belum ada di database
if ($token_sekarang === false) {
    $token_sekarang = '';
}
$is_buka = ($status === 'buka');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toggle Registrasi Siswa - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand" href="index.php">Modul Pembelajaran SIJA</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4"></ul>
                <div class="d-flex align-items-center gap-3">
                    <?php if (isset($_SESSION['username'])) : ?>
                        <span class="text-secondary fw-medium d-none d-md-inline small">
                            👋 Hai, <strong class="text-dark"><?= htmlspecialchars($_SESSION['username']); ?></strong>
                        </span>
                    <?php endif; ?>
                </div>
        </div>
    </nav>

    <!-- HEADER -->
    <header class="py-5" style="
        background: linear-gradient(to bottom, rgba(15, 23, 42, 0.85), rgba(30, 41, 59, 0.9)), 
                    url('https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=800'); 
        background-size: cover; 
        background-position: center;">
        <div class="container px-4 px-lg-5 my-5">
            <div class="text-center text-white">
                <h1 class="display-4 fw-bolder">Pusat Pembelajaran SIJA</h1>
                <p class="lead fw-normal text-white-50 mb-0">Panel kontrol pendaftaran siswa baru</p>
            </div>
    </header>

    <div class="container mt-5 mb-5" style="max-width: 550px;">

        <?php if ($pesan): ?>
        <div class="alert alert-<?= $pesan_type ?> alert-dismissible fade show" role="alert">
            <?= $pesan ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- KARTU 1: Toggle Buka/Tutup -->
        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="card-title mb-0 fw-bold">🔓 Kontrol Pendaftaran Siswa</h5>
            </div>
            <div class="card-body p-4 text-center">
                <p class="text-muted small mb-4">
                    Buka pendaftaran saat sedang ada sesi registrasi (misal awal semester atau 
                    saat siswa baru masuk). Tutup kembali setelah selesai agar tidak ada 
                    pendaftaran liar di luar jam sekolah.
                </p>

                <div class="mb-4">
                    <span class="badge fs-5 px-4 py-2 <?= $is_buka ? 'bg-success' : 'bg-secondary' ?>">
                        <?= $is_buka ? '🟢 REGISTRASI DIBUKA' : '🔴 REGISTRASI DITUTUP' ?>
                    </span>
                </div>

                <form method="POST">
                    <!-- 🚨 INI DIA YANG KETINGGALAN SAMA AI (TOKEN CSRF) 🚨 -->
                    <?= csrf_field(); ?>
                    <?php if ($is_buka): ?>
                        <button type="submit" name="toggle" value="tutup" class="btn btn-danger w-100 fw-bold py-3">
                            <i class="bi bi-lock-fill"></i> Tutup Pendaftaran
                        </button>
                    <?php else: ?>
                        <button type="submit" name="toggle" value="buka" class="btn btn-success w-100 fw-bold py-3">
                            <i class="bi bi-unlock-fill"></i> Buka Pendaftaran
                        </button>
                    <?php endif; ?>
                </form>
            </div>

        <!-- KARTU 2: Ganti Token -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-warning text-dark py-3">
                <h5 class="card-title mb-0 fw-bold">🔑 Atur Token Akses Pendaftaran</h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-3">
                    Token saat ini: 
                    <span class="badge bg-dark fs-6 px-3 py-1">
                        <?= htmlspecialchars($token_sekarang ?: 'gak berlaku') ?>
                    </span>
                </p>
                <p class="text-muted small mb-3">
                    Ganti token tiap bulan/kelas. Beritahu token baru ke siswa agar mereka bisa 
                    mendaftar. Token lama otomatis tidak berlaku setelah diganti.
                </p>
                <form method="POST">
                    <?= csrf_field(); ?>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" name="token_baru" 
                            placeholder="Ketik token baru..." required minlength="3"
                            value="<?= htmlspecialchars($token_sekarang); ?>">
                        <button type="submit" name="update_token" class="btn btn-warning fw-bold">
                            <i class="bi bi-check2"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>

        <div class="text-center mt-4">
            <a href="index.php" class="text-muted small text-decoration-none">← Kembali ke Beranda</a>
            <span class="text-muted mx-2">|</span>
            <a href="gerbang-rahasia-sija" class="text-muted small text-decoration-none">Manage Modul</a>
            <span class="text-muted mx-2">|</span>
            <a href="pintu-belakang-sija" class="text-muted small text-decoration-none">Manage User</a>
        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
