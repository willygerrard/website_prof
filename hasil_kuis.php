<?php
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

$skor  = isset($_GET['skor']) ? (int)$_GET['skor'] : null;
$benar = isset($_GET['benar']) ? (int)$_GET['benar'] : null;
$total = isset($_GET['total']) ? (int)$_GET['total'] : null;
$lulus = isset($_GET['lulus']) && $_GET['lulus'] === '1';

if ($skor === null) {
    header("Location: kuis.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Kuis - Pusat Pembelajaran SIJA</title>
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

    <!-- KONTEN -->
    <div class="container d-flex align-items-center justify-content-center" style="min-height: 80vh;">
        <div class="card shadow-sm border-0 rounded-3 text-center p-4" style="max-width: 480px;">
            <div class="card-body">

                <?php if ($lulus): ?>
                    <div class="mb-3" style="font-size: 4rem;">🎉</div>
                    <h3 class="fw-bold text-success mb-2">Selamat, Kamu Lulus!</h3>
                <?php else: ?>
                    <div class="mb-3" style="font-size: 4rem;">📚</div>
                    <h3 class="fw-bold text-warning mb-2">Belum Lulus, Coba Lagi!</h3>
                <?php endif; ?>

                <div class="display-3 fw-bolder my-4 <?= $lulus ? 'text-success' : 'text-danger' ?>">
                    <?= $skor ?>
                </div>

                <p class="text-secondary mb-4">
                    Kamu menjawab benar <strong><?= $benar ?></strong> dari <strong><?= $total ?></strong> soal
                </p>

                <?php if (!$lulus): ?>
                <div class="alert alert-light border small text-start">
                    💡 Nilai minimal kelulusan (KKM) adalah <strong>75</strong>.
                    Kamu masih punya kesempatan remidi — jangan menyerah!
                </div>
                <?php endif; ?>

                <a href="kuis.php" class="btn btn-primary fw-bold w-100 mt-3">
                    <i class="bi bi-arrow-left"></i> Kembali ke Daftar Kuis
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>