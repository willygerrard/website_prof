<?php
session_start();
include 'koneksi.php';
include 'csrf_helper.php';

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['is_login']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("HTTP/1.1 404 Not Found");
    exit();
}

$id = $_GET['id'] ?? '';
if (empty($id)) {
    header("Location: edit-soal-sija");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM kuis_soal WHERE id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) {
    die("Data soal tidak ditemukan!");
}

// Daftar materi yang sudah ada, untuk dropdown + opsi tambah baru
$materi_list = $pdo->query("SELECT DISTINCT materi FROM kuis_soal WHERE materi IS NOT NULL AND materi <> '' ORDER BY materi")
                    ->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid_post();
    $kategori   = trim($_POST['kategori'] ?? '');
    $level      = trim($_POST['level'] ?? '');
    $pertanyaan = trim($_POST['pertanyaan'] ?? '');
    $pilihan_a  = trim($_POST['pilihan_a'] ?? '');
    $pilihan_b  = trim($_POST['pilihan_b'] ?? '');
    $pilihan_c  = trim($_POST['pilihan_c'] ?? '');
    $pilihan_d  = trim($_POST['pilihan_d'] ?? '');
    $jawaban    = trim($_POST['jawaban'] ?? '');

    // Materi: ambil dari input "tambah baru" kalau diisi, kalau tidak pakai yang dipilih dropdown
    $materi_baru   = trim($_POST['materi_baru'] ?? '');
    $materi_pilih  = trim($_POST['materi_pilih'] ?? '');
    $materi        = $materi_baru !== '' ? $materi_baru : ($materi_pilih !== '' ? $materi_pilih : null);

    try {
        $stmt = $pdo->prepare("UPDATE kuis_soal SET kategori=?, level=?, materi=?, pertanyaan=?, pilihan_a=?, pilihan_b=?, pilihan_c=?, pilihan_d=?, jawaban=? WHERE id=?");
        $stmt->execute([$kategori, $level, $materi, $pertanyaan, $pilihan_a, $pilihan_b, $pilihan_c, $pilihan_d, $jawaban, $id]);

        echo "<script>
            alert('Soal berhasil diperbarui!');
            window.location.href = 'pintu-rahasia-sija';
        </script>";
        exit();
    } catch (PDOException $e) {
        db_error($e);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Soal Kuis - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .hero-banner {
            background: linear-gradient(rgba(15, 23, 42, 0.85), rgba(15, 23, 42, 0.85)), url('https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=800') no-repeat center center;
            background-size: cover;
            color: white;
            padding: 60px 0;
            text-align: center;
        }
    </style>
</head>
<body class="bg-light">

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top py-3">
        <div class="container">
            <a class="navbar-brand fw-bold text-dark" href="index.php">Modul Pembelajaran SIJA</a>
            <div class="ms-auto d-flex align-items-center gap-3">
                <?php if (isset($_SESSION['username'])) : ?>
                    <span class="text-secondary fw-medium d-none d-md-inline small">
                        👋 Hai, <strong class="text-dark"><?= htmlspecialchars($_SESSION['username']); ?></strong>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- HEADER -->
    <div class="hero-banner shadow-sm">
        <div class="container">
            <h1 class="display-5 fw-bold">Pusat Pembelajaran SIJA</h1>
            <p class="lead text-white-50 fs-6 m-0">Selamat datang di portal lab kendali materi mandiri.</p>
        </div>
    </div>

    <!-- KONTEN -->
    <div class="container mt-5 mb-5" style="max-width: 650px;">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h4 class="fw-bold m-0 text-dark">✏️ Edit Soal Kuis</h4>
            <a href="pintu-rahasia-sija" class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="bi bi-arrow-counterclockwise"></i>
            </a>
        </div>

        <div class="card border-0 shadow-sm rounded-3 p-4 bg-white">
            <form method="POST">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label fw-semibold text-secondary">Kategori</label>
                    <select class="form-select" name="kategori" required>
                        <option value="Network"        <?= $data['kategori'] === 'Network' ? 'selected' : '' ?>>Network</option>
                        <option value="IoT"            <?= $data['kategori'] === 'IoT' ? 'selected' : '' ?>>Internet of Things (IoT)</option>
                        <option value="Cloud Computing" <?= $data['kategori'] === 'Cloud Computing' ? 'selected' : '' ?>>Cloud Computing</option>
                        <option value="DevOps"         <?= $data['kategori'] === 'DevOps' ? 'selected' : '' ?>>DevOps</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold text-secondary">Level</label>
                    <select class="form-select" name="level" required>
                        <option value="pemula"   <?= $data['level'] === 'pemula' ? 'selected' : '' ?>>🟢 Pemula</option>
                        <option value="menengah" <?= $data['level'] === 'menengah' ? 'selected' : '' ?>>🟡 Menengah</option>
                        <option value="mahir"    <?= $data['level'] === 'mahir' ? 'selected' : '' ?>>🔴 Mahir</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold text-secondary">Materi</label>
                    <select class="form-select" name="materi_pilih" id="materi_pilih" onchange="toggleMateriBaru(this)">
                        <option value="">-- Belum Ditandai --</option>
                        <?php foreach ($materi_list as $m): ?>
                        <option value="<?= htmlspecialchars($m) ?>" <?= $data['materi'] === $m ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m) ?>
                        </option>
                        <?php endforeach; ?>
                        <option value="__baru__">+ Tambah materi baru...</option>
                    </select>
                    <input type="text" name="materi_baru" id="materi_baru" class="form-control mt-2"
                           placeholder="Ketik nama materi baru" style="display:none;">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold text-secondary">Pertanyaan</label>
                    <textarea class="form-control" name="pertanyaan" rows="3" required><?= htmlspecialchars($data['pertanyaan']) ?></textarea>
                </div>

                <hr class="my-3 text-muted">
                <p class="fw-semibold text-secondary mb-2">Pilihan Jawaban</p>

                <?php foreach (['a','b','c','d'] as $huruf): ?>
                <div class="mb-3 input-group">
                    <span class="input-group-text fw-bold bg-light"><?= strtoupper($huruf) ?></span>
                    <input type="text" class="form-control" name="pilihan_<?= $huruf ?>"
                        value="<?= htmlspecialchars($data['pilihan_' . $huruf]) ?>" required>
                </div>
                <?php endforeach; ?>

                <div class="mb-4">
                    <label class="form-label fw-semibold text-secondary">Jawaban Benar</label>
                    <select class="form-select" name="jawaban" required>
                        <?php foreach (['a','b','c','d'] as $huruf): ?>
                        <option value="<?= $huruf ?>" <?= $data['jawaban'] === $huruf ? 'selected' : '' ?>>
                            <?= strtoupper($huruf) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-warning w-100 py-2 fw-bold text-dark rounded-2 shadow-sm">
                    💾 Simpan Perubahan
                </button>

            </form>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="py-5 bg-dark">
        <div class="container"><p class="m-0 text-center text-white">Copyright &copy; SIJA Website 2026</p></div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleMateriBaru(select) {
            const inputBaru = document.getElementById('materi_baru');
            if (select.value === '__baru__') {
                inputBaru.style.display = 'block';
                inputBaru.required = true;
            } else {
                inputBaru.style.display = 'none';
                inputBaru.required = false;
                inputBaru.value = '';
            }
        }
    </script>
</body>
</html>