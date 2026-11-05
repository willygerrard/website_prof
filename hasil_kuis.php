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
$topbar_page = 'Hasil Kuis';

$html_lang  = 'id';
$page_title = 'Hasil Kuis - Pusat Pembelajaran SIJA';
$body_class = 'bg-light';
$extra_head = '<link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />';

include __DIR__ . '/includes/head.php';
?>

<!-- Bungkus dengan shell untuk sidebar -->
<div class="sj-shell flex-grow-1">

    <!-- Include Sidebar -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Konten Utama -->
    <main class="sj-main bg-light pb-5">

        <?php include __DIR__ . '/includes/topbar.php'; ?>

        <!-- KONTEN -->
        <div class="container d-flex align-items-center justify-content-center py-4" style="min-height: 70vh;">
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
    </main>
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