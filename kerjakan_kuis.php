<?php
include 'koneksi.php';
include 'csrf_helper.php'; // Helper CSRF yang sudah Anda buat
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

// Ambil data sesi, pastikan status 'aktif' DAN belum expired (2x durasi)
$stmt = $pdo->prepare("
    SELECT * FROM kuis_sesi 
    WHERE id = ? 
      AND status = 'aktif'
      AND TIMESTAMPADD(MINUTE, (durasi_menit * 2), dibuka_at) > NOW()
");
$stmt->execute([$sesi_id]);
$sesi = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sesi) {
    // Update status di DB jika ada yang lolos batas waktu
    $pdo->prepare("UPDATE kuis_sesi SET status = 'nonaktif', ditutup_at = NOW() WHERE id = ? AND status = 'aktif'")
        ->execute([$sesi_id]);
        
    die("Sesi kuis ini sudah ditutup otomatis oleh sistem atau tidak ditemukan. <a href='kuis.php'>Kembali</a>");
}

// Pastikan sesi ini ditujukan untuk kelas siswa yang login
$stmtKelasSiswa = $pdo->prepare("SELECT kelas FROM users WHERE id = ?");
$stmtKelasSiswa->execute([$user_id]);
$kelas_siswa = $stmtKelasSiswa->fetchColumn();

$cekKelas = $pdo->prepare("SELECT 1 FROM kuis_sesi_kelas WHERE sesi_id = ? AND kelas = ?");
$cekKelas->execute([$sesi_id, $kelas_siswa]);

if (!$cekKelas->fetch()) {
    die("Kuis ini tidak ditujukan untuk kelas kamu. <a href='kuis.php'>Kembali</a>");
}

// Cek attempt & status lulus
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

// ===== HANDLE SUBMIT (KEBAL MANIPULASI POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_kuis'])) {
    // Validasi CSRF Token
    if (function_exists('csrf_require_valid_post')) {
        csrf_require_valid_post();
    }

    // AMBIL ID SOAL RESMI DARI SESSION (Abaikan $_POST['soal_id'] suntikan)
    $session_key = 'kuis_soal_ids_sesi_' . $sesi_id . '_user_' . $user_id;
    $soal_ids_resmi = $_SESSION[$session_key] ?? [];
    $jawaban_siswa  = $_POST['jawaban'] ?? [];

    if (empty($soal_ids_resmi)) {
        $pesan_error = "Sesi kuis tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $benar = 0;
        $total_soal = count($soal_ids_resmi);

        // Prepare query sekali di luar loop untuk efisiensi
        $stmt_kunci = $pdo->prepare("SELECT jawaban FROM kuis_soal WHERE id = ?");

        foreach ($soal_ids_resmi as $soal_id) {
            $soal_id = (int)$soal_id;
            
            $stmt_kunci->execute([$soal_id]);
            $kunci = $stmt_kunci->fetchColumn();

            $jawab_user = $jawaban_siswa[$soal_id] ?? null;

            // Sanitasi: Pastikan input hanya string a, b, c, atau d
            if ($jawab_user !== null && in_array(strtolower($jawab_user), ['a', 'b', 'c', 'd'], true)) {
                if (strtolower($jawab_user) === strtolower($kunci)) {
                    $benar++;
                }
            }
        }

        $skor = $total_soal > 0 ? round(($benar / $total_soal) * 100) : 0;

        // Simpan hasil
        $stmt = $pdo->prepare("INSERT INTO kuis_hasil (user_id, kategori, level, sesi_id, skor, total_soal, attempt, dikerjakan_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $sesi['kategori'], $sesi['level'], $sesi_id, $skor, $total_soal, $total_attempt + 1]);

        // Bersihkan session ID Soal kuis setelah berhasil submit
        unset($_SESSION[$session_key]);

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

// ===== AMBIL SOAL (RANDOM & FILTER MATERI) =====
$stmtMateri = $pdo->prepare("SELECT materi FROM kuis_sesi_materi WHERE sesi_id = ?");
$stmtMateri->execute([$sesi_id]);
$materi_terpilih = $stmtMateri->fetchAll(PDO::FETCH_COLUMN);

if (!empty($materi_terpilih)) {
    $placeholders = implode(',', array_fill(0, count($materi_terpilih), '?'));
    $querySoal = "
        SELECT * FROM kuis_soal 
        WHERE kategori = ? AND level = ? AND materi IN ($placeholders)
        ORDER BY RAND()
    ";
    $params = array_merge([$sesi['kategori'], $sesi['level']], $materi_terpilih);
    $stmt = $pdo->prepare($querySoal);
    $stmt->execute($params);
} else {
    $stmt = $pdo->prepare("SELECT * FROM kuis_soal WHERE kategori = ? AND level = ? ORDER BY RAND()");
    $stmt->execute([$sesi['kategori'], $sesi['level']]);
}

$soal_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($soal_list)) {
    die("Belum ada soal untuk kategori/level/materi ini. Hubungi admin. <a href='kuis.php'>Kembali</a>");
}

// SIMPAN ID SOAL RESMI KE SESSION UNTUK DIVALIDASI SAAT SUBMIT POST
$session_key = 'kuis_soal_ids_sesi_' . $sesi_id . '_user_' . $user_id;
$_SESSION[$session_key] = array_map('intval', array_column($soal_list, 'id'));

// Acak urutan pilihan jawaban per soal
foreach ($soal_list as &$soal) {
    $opsi = ['a' => $soal['pilihan_a'], 'b' => $soal['pilihan_b'], 'c' => $soal['pilihan_c'], 'd' => $soal['pilihan_d']];
    $soal['opsi_acak'] = $opsi;
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
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        <div class="alert alert-danger"><?= htmlspecialchars($pesan_error) ?></div>
        <?php endif; ?>

        <form method="POST" id="form-kuis">
            <!-- Hidden CSRF Token (sesuai fungsi csrf_helper Anda) -->
            <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>

            <input type="hidden" name="sesi_id" value="<?= $sesi_id ?>">

            <?php $no = 1; foreach ($soal_list as $soal): ?>
            <div class="card mb-3 shadow-sm border-0 rounded-3 soal-card">
                <div class="card-body p-4">
                    <p class="fw-semibold mb-3"><?= $no++ ?>. <?= htmlspecialchars($soal['pertanyaan']) ?></p>

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

        // ===== ANTI-CHEAT: Deteksi Pindah Tab =====
        let tabSwitchCount = 0;

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                tabSwitchCount++;
                console.warn('Tab switch terdeteksi:', tabSwitchCount);
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'HAYOO MAU PINDAH KEMANA? 🧐',
                    html: `Aktivitas keluar tab terdeteksi <b>${tabSwitchCount} kali</b>!<br><span class="text-danger fw-bold">Tetap di halaman ini atau nilai kamu akan ditinjau ulang oleh sistem.</span>`,
                    confirmButtonText: 'Ampun Pak, Saya Kembali Kerjakan!',
                    confirmButtonColor: '#dc3545',
                    allowOutsideClick: false
                });
            }
        });
    </script>
</body>
</html>