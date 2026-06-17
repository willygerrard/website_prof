<?php
include 'koneksi.php';
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['is_login']) || $_SESSION['role'] !== 'admin') {
 header("HTTP/1.1 404 Not Found");
    exit();
}

$pesan = '';
$pesan_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kategori   = trim($_POST['kategori'] ?? '');
    $pertanyaan = trim($_POST['pertanyaan'] ?? '');
    $pilihan_a  = trim($_POST['pilihan_a'] ?? '');
    $pilihan_b  = trim($_POST['pilihan_b'] ?? '');
    $pilihan_c  = trim($_POST['pilihan_c'] ?? '');
    $pilihan_d  = trim($_POST['pilihan_d'] ?? '');
    $jawaban    = trim($_POST['jawaban'] ?? '');
    $level      = trim($_POST['level'] ?? '');

    if ($kategori && $pertanyaan && $pilihan_a && $pilihan_b && $pilihan_c && $pilihan_d && $jawaban && $level) {
        $stmt = $pdo->prepare("INSERT INTO kuis_soal (kategori, level, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, jawaban) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$kategori, $level, $pertanyaan, $pilihan_a, $pilihan_b, $pilihan_c, $pilihan_d, $jawaban]);
        $pesan = '✅ Soal berhasil ditambahkan!';
        $pesan_type = 'success';
    } else {
        $pesan = '⚠️ Semua field wajib diisi!';
        $pesan_type = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Soal Kuis - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand" href="index.php">Modul Pembelajaran SIJA</a>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4"></ul>
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

    <!-- HEADER -->
    <header class="py-5" style="
        background: linear-gradient(to bottom, rgba(15, 23, 42, 0.85), rgba(30, 41, 59, 0.9)), 
                    url('https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=800'); 
        background-size: cover; 
        background-position: center;">
        <div class="container px-4 px-lg-5 my-5">
            <div class="text-center text-white">
                <h1 class="display-4 fw-bolder">Pusat Pembelajaran SIJA</h1>
                <p class="lead fw-normal text-white-50 mb-0">Selamat datang di portal lab kendali materi mandiri</p>
            </div>
        </div>
    </header>

    <!-- KONTEN -->
    <div class="container mt-5 mb-5" style="max-width: 720px;">

        <?php if ($pesan): ?>
        <div class="alert alert-<?= $pesan_type ?> alert-dismissible fade show" role="alert">
            <?= $pesan ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold">📝 Tambah Soal Kuis</h5>
                <a href="pintu-rahasia-sija" class="btn btn-sm btn-light fw-semibold">
                    <i class="bi bi-arrow-left"></i> Kembali
                </a>
            </div>

            <div class="card-body p-4">
                <form method="POST">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kategori</label>
                        <select class="form-select" name="kategori" required>
                            <option value="" selected disabled>-- Pilih Kategori --</option>
                            <option value="Network">Network</option>
                            <option value="IoT">Internet of Things (IoT)</option>
                            <option value="Cloud Computing">Cloud Computing</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Level</label>
                        <select class="form-select" name="level" required>
                            <option value="" selected disabled>-- Pilih Level --</option>
                            <option value="pemula">🟢 Pemula</option>
                            <option value="menengah">🟡 Menengah</option>
                            <option value="mahir">🔴 Mahir</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pertanyaan</label>
                        <textarea class="form-control" name="pertanyaan" rows="3" placeholder="Tulis pertanyaan di sini..." required></textarea>
                    </div>

                    <hr class="my-3 text-muted">
                    <p class="fw-semibold text-secondary mb-2">Pilihan Jawaban</p>

                    <?php foreach (['a','b','c','d'] as $huruf): ?>
                    <div class="mb-3 input-group">
                        <span class="input-group-text fw-bold bg-light"><?= strtoupper($huruf) ?></span>
                        <input type="text" class="form-control" name="pilihan_<?= $huruf ?>" placeholder="Pilihan <?= strtoupper($huruf) ?>" required>
                    </div>
                    <?php endforeach; ?>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Jawaban Benar</label>
                        <select class="form-select" name="jawaban" required>
                            <option value="" selected disabled>-- Pilih Jawaban Benar --</option>
                            <option value="a">A</option>
                            <option value="b">B</option>
                            <option value="c">C</option>
                            <option value="d">D</option>
                        </select>
                    </div>

                    <hr class="my-4 text-muted">

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary fw-bold py-2">💾 Simpan Soal</button>
                        <button type="reset" class="btn btn-outline-danger btn-sm">Reset Form</button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="py-5 bg-dark">
        <div class="container"><p class="m-0 text-center text-white">Copyright &copy; SIJA Website 2026</p></div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
