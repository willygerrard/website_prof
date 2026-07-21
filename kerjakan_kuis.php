<?php
include 'koneksi.php';
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("Sesi user_id tidak ditemukan. Silakan login ulang.");
}

const MAX_ATTEMPT = 4;
const KKM = 75;

$sesi_id = (int)($_GET['sesi'] ?? $_POST['sesi_id'] ?? 0);
if (!$sesi_id) {
    header("Location: kuis.php");
    exit();
}

// Ambil data sesi, pastikan masih aktif
$stmt = $pdo->prepare("SELECT * FROM kuis_sesi WHERE id = ? AND status = 'aktif'");
$stmt->execute([$sesi_id]);
$sesi = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sesi) {
    die("Sesi kuis ini sudah ditutup atau tidak ditemukan. <a href='kuis.php'>Kembali</a>");
}

// Pastikan sesi ini memang ditujukan untuk kelas siswa yang login
// (mencegah akses langsung via URL walau kartunya tidak muncul di kuis.php)
$stmtKelasSiswa = $pdo->prepare("SELECT kelas FROM users WHERE id = ?");
$stmtKelasSiswa->execute([$user_id]);
$kelas_siswa = $stmtKelasSiswa->fetchColumn();

$cekKelas = $pdo->prepare("SELECT 1 FROM kuis_sesi_kelas WHERE sesi_id = ? AND kelas = ?");
$cekKelas->execute([$sesi_id, $kelas_siswa]);

if (!$cekKelas->fetch()) {
    die("Kuis ini tidak ditujukan untuk kelas kamu. <a href='kuis.php'>Kembali</a>");
}

// Cek attempt & status lulus sebelum mengizinkan akses (PER SESI, bukan gabungan semua deploy)
$cek = $pdo->prepare("SELECT COUNT(*) as total, MAX(skor) as nilai_terbaik FROM kuis_hasil WHERE user_id = ? AND sesi_id = ?");
$cek->execute([$user_id, $sesi_id]);
$status = $cek->fetch(PDO::FETCH_ASSOC);

$total_attempt = (int)$status['total'];
$nilai_terbaik = $status['nilai_terbaik'] !== null ? (int)$status['nilai_terbaik'] : null;

if ($nilai_terbaik !== null && $nilai_terbaik >= KKM) {
    die("Kamu sudah lulus kuis ini dengan nilai $nilai_terbaik. <a href='kuis.php'>Kembali</a>");
}
if ($total_attempt >= MAX_ATTEMPT) {
    die("Kesempatan mengerjakan kuis ini sudah habis. Nilai terbaik: $nilai_terbaik. <a href='kuis.php'>Kembali</a>");
}

$pesan_error = '';

// ===== HANDLE SUBMIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_kuis'])) {
    $soal_ids = $_POST['soal_id'] ?? [];
    $jawaban_siswa = $_POST['jawaban'] ?? [];

    if (empty($soal_ids)) {
        $pesan_error = "Terjadi kesalahan, silakan coba lagi.";
    } else {
        $benar = 0;
        $total_soal = count($soal_ids);

        foreach ($soal_ids as $soal_id) {
            $soal_id = (int)$soal_id;
            $stmt = $pdo->prepare("SELECT jawaban FROM kuis_soal WHERE id = ?");
            $stmt->execute([$soal_id]);
            $kunci = $stmt->fetchColumn();

            $jawab_user = $jawaban_siswa[$soal_id] ?? null;
            if ($jawab_user !== null && $jawab_user === $kunci) {
                $benar++;
            }
        }

        $skor = $total_soal > 0 ? round(($benar / $total_soal) * 100) : 0;

        // Simpan hasil (terikat ke sesi_id ini, sehingga deploy lain kategori+level sama tidak tercampur)
        $stmt = $pdo->prepare("INSERT INTO kuis_hasil (user_id, kategori, level, sesi_id, skor, total_soal, attempt, dikerjakan_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $sesi['kategori'], $sesi['level'], $sesi_id, $skor, $total_soal, $total_attempt + 1]);

        // Kirim notifikasi WA ke ortu
        $stmt_wa = $pdo->prepare("SELECT no_wa_ortu FROM users WHERE id = ?");
        $stmt_wa->execute([$user_id]);
        $no_wa = $stmt_wa->fetchColumn();

        if ($no_wa) {
            require_once 'fonnte.php';
            $tanggal = date('d M Y, H:i');
            $status_lulus = $skor >= KKM ? 'LULUS ✅' : 'belum lulus, KKM 75';
            $pesan_wa = "📝 *Pusat Pembelajaran SIJA*\n\n"
                    . htmlspecialchars($_SESSION['username']) . " mengerjakan kuis:\n"
                    . "*" . htmlspecialchars($sesi['kategori']) . " - " . ucfirst($sesi['level']) . "*\n\n"
                    . "Percobaan ke-" . ($total_attempt + 1) . " dari 4\n"
                    . "Nilai: *$skor* ($benar dari $total_soal soal benar)\n"
                    . "Status: $status_lulus\n\n"
                    . "Pada: $tanggal";
            kirimWA($no_wa, $pesan_wa);
        }

        // Redirect ke halaman hasil
        header("Location: hasil_kuis.php?skor=$skor&benar=$benar&total=$total_soal&lulus=" . ($skor >= KKM ? '1' : '0'));
        exit();
    }
}

// ===== AMBIL SOAL (RANDOM) =====
$stmt = $pdo->prepare("SELECT * FROM kuis_soal WHERE kategori = ? AND level = ? ORDER BY RAND()");
$stmt->execute([$sesi['kategori'], $sesi['level']]);
$soal_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($soal_list)) {
    die("Belum ada soal untuk kategori/level ini. Hubungi admin. <a href='kuis.php'>Kembali</a>");
}

// Acak urutan pilihan jawaban per soal (opsional tapi bagus untuk anti-cheat)
foreach ($soal_list as &$soal) {
    $opsi = ['a' => $soal['pilihan_a'], 'b' => $soal['pilihan_b'], 'c' => $soal['pilihan_c'], 'd' => $soal['pilihan_d']];
    $soal['opsi_acak'] = $opsi; // tetap pakai key asli a/b/c/d agar pengecekan jawaban tetap akurat
}
unset($soal);

$durasi_detik = $sesi['durasi_menit'] * 60;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mengerjakan Kuis - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        #timer-box {
            position: sticky;
            top: 10px;
            z-index: 1000;
        }
        .soal-card { scroll-margin-top: 80px; }
    </style>
</head>
<body>

    <div class="container py-4" style="max-width: 800px;">

        <!-- TIMER -->
        <div id="timer-box" class="alert alert-dark d-flex justify-content-between align-items-center shadow-sm mb-4">
            <div>
                <strong><?= htmlspecialchars($sesi['kategori']) ?></strong> — <?= ucfirst($sesi['level']) ?>
                <span class="text-muted small d-block">Percobaan ke-<?= $total_attempt + 1 ?> dari <?= MAX_ATTEMPT ?></span>
            </div>
            <div class="fs-4 fw-bold text-danger" id="timer">--:--</div>
        </div>

        <?php if ($pesan_error): ?>
        <div class="alert alert-danger"><?= $pesan_error ?></div>
        <?php endif; ?>

        <form method="POST" id="form-kuis">
            <input type="hidden" name="sesi_id" value="<?= $sesi_id ?>">

            <?php $no = 1; foreach ($soal_list as $soal): ?>
            <div class="card mb-3 shadow-sm border-0 rounded-3 soal-card">
                <div class="card-body p-4">
                    <p class="fw-semibold mb-3"><?= $no++ ?>. <?= htmlspecialchars($soal['pertanyaan']) ?></p>
                    <input type="hidden" name="soal_id[]" value="<?= $soal['id'] ?>">

                    <?php foreach (['a','b','c','d'] as $huruf): ?>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio"
                               name="jawaban[<?= $soal['id'] ?>]"
                               id="soal<?= $soal['id'] ?>_<?= $huruf ?>"
                               value="<?= $huruf ?>" required>
                        <label class="form-check-label" for="soal<?= $soal['id'] ?>_<?= $huruf ?>">
                            <?= strtoupper($huruf) ?>. <?= htmlspecialchars($soal['opsi_acak'][$huruf]) ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="d-grid mb-5">
                <button type="submit" name="submit_kuis" class="btn btn-primary btn-lg fw-bold">
                    <i class="bi bi-check-circle"></i> Submit Jawaban
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ===== TIMER COUNTDOWN =====
        let sisaWaktu = <?= $durasi_detik ?>;
        const timerEl = document.getElementById('timer');
        const formKuis = document.getElementById('form-kuis');

        function updateTimer() {
            const menit = Math.floor(sisaWaktu / 60);
            const detik = sisaWaktu % 60;
            timerEl.textContent = String(menit).padStart(2, '0') + ':' + String(detik).padStart(2, '0');

            if (sisaWaktu <= 0) {
                alert('Waktu habis! Jawaban akan otomatis disubmit.');
                formKuis.submit();
                return;
            }
            sisaWaktu--;
        }
        updateTimer();
        setInterval(updateTimer, 1000);

        // ===== ANTI-CHEAT: deteksi pindah tab =====
        let tabSwitchCount = 0;
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                tabSwitchCount++;
                console.warn('Tab switch terdeteksi:', tabSwitchCount);
            }
        });

        // Kirim tab switch count saat submit (opsional, untuk dicatat)
        formKuis.addEventListener('submit', function () {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'tab_switch';
            input.value = tabSwitchCount;
            formKuis.appendChild(input);
        });
    </script>
</body>
</html>