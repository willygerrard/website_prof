<?php
require_once 'session.php';      // session dulu
require 'koneksi.php';
checkLogin();

// Nilai dari URL tidak dipercaya untuk skor/skor_mentah/batas_remedial:
// ketiganya diambil dari database berdasarkan user yang login.
$sesi_id_get  = isset($_GET['sesi']) ? (int)$_GET['sesi'] : 0;
$attempt_get  = isset($_GET['attempt']) ? (int)$_GET['attempt'] : 0;
$user_id_page = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

$skor = null;
$skor_mentah = null;
$batas_remedial = null;
$lulus = false;

if ($user_id_page && $sesi_id_get > 0 && $attempt_get > 0) {
    $stmt = $pdo->prepare("SELECT h.skor, h.skor_mentah, h.attempt, s.batas_remedial FROM kuis_hasil h JOIN kuis_sesi s ON s.id = h.sesi_id WHERE h.user_id = ? AND h.sesi_id = ? AND h.attempt = ?");
    $stmt->execute([$user_id_page, $sesi_id_get, $attempt_get]);
    $hasil_db = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (empty($hasil_db)) {
    die("Hasil tidak ditemukan. <a href='kuis.php'>Kembali ke Daftar Kuis</a>");
}

$skor           = (int)$hasil_db['skor'];
$skor_mentah    = $hasil_db['skor_mentah'] !== null ? (int)$hasil_db['skor_mentah'] : null;
$batas_remedial = (int)$hasil_db['batas_remedial'];
$lulus          = $skor >= 75;

// Angka benar/total tetap dari URL (statistik tampilan, bukan nilai rapor)
$benar = isset($_GET['benar']) ? (int)$_GET['benar'] : null;
$total = isset($_GET['total']) ? (int)$_GET['total'] : null;

// Pesan cap remedial hanya jika ada skor mentah yang tertahan batas
$pesan_cap = ($skor_mentah !== null && $skor < $skor_mentah)
    ? "Nilai mentah {$skor_mentah}, nilai tercatat {$skor} (batas remedial {$batas_remedial})"
    : null;
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

                <?php if ($pesan_cap): ?>
                <div class="alert alert-info small mb-4"><?= htmlspecialchars($pesan_cap) ?></div>
                <?php endif; ?>

                <?php if (!$lulus): ?>
                <!-- FITUR BARU: AI REVIEW -->
                <div class="mt-4 border-top pt-4">
                    <h6 class="fw-bold"><i class="bi bi-stars text-primary"></i> AI Tutor: Review Kesalahanmu</h6>
                    <p class="small text-muted mb-2">Kamu sudah lulus, tapi mau tahu di mana letak kesalahanmu tadi? Tanya AI Tutor kami.</p>
                    <button id="btnReview" class="btn btn-outline-primary w-100 fw-bold" onclick="loadReview()">
                        Analisis Jawaban Salah
                    </button>
                    <div id="aiReviewResult" class="mt-3 text-start" style="display:none;"></div>
                </div>
                <?php else: ?>
                <div class="alert alert-light border small text-start mt-3">
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
    <script>
async function loadReview() {
    const btn = document.getElementById('btnReview');
    const resDiv = document.getElementById('aiReviewResult');
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Menganalisis...';
    
    try {
        const res = await fetch(`ai_quiz_review.php?sesi=<?= $sesi_id_get ?>&attempt=<?= $attempt_get ?>`);
        const data = await res.json();
        
        if (data.status === 'success') {
            if (data.data.length === 0) {
                resDiv.innerHTML = `<div class="alert alert-success">Luar biasa! Tidak ada kesalahan yang perlu dianalisis.</div>`;
            } else {
                let html = '';
                data.data.forEach(item => {
                    html += `<div class="card mb-2 border-warning bg-warning bg-opacity-10">
                        <div class="card-body p-3">
                            <div class="small fw-bold mb-1">${item.soal}</div>
                            <div class="small text-danger mb-1"><i class="bi bi-x-circle"></i> ${item.kesalahan}</div>
                            <div class="small text-success"><i class="bi bi-lightbulb"></i> Fokus belajar: <strong>${item.konsep_harus_dipelajari}</strong></div>
                        </div>
                    </div>`;
                });
                resDiv.innerHTML = html;
            }
            resDiv.style.display = 'block';
            btn.style.display = 'none';
        } else {
            alert(data.message || 'Gagal memuat review.');
            btn.disabled = false;
            btn.innerHTML = 'Coba Lagi';
        }
    } catch (e) {
        alert('Koneksi error.');
        btn.disabled = false;
        btn.innerHTML = 'Coba Lagi';
    }
}
</script>
</body>
</html>