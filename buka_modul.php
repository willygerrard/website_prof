<?php
require_once 'session.php'; 
require 'koneksi.php';
require_once 'fonnte.php';
require_once 'log_aktivitas.php';

checkLogin();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

$user_id  = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? '';
$modul_id = (int)($_GET['id'] ?? 0);

if (!$user_id || !$modul_id) {
    header("Location: index.php");
    exit();
}

// Ambil data modul
$stmt = $pdo->prepare("SELECT * FROM modules WHERE id = ?");
$stmt->execute([$modul_id]);
$modul = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$modul || empty($modul['file_path'])) {
    header("Location: index.php");
    exit();
}

// Log aktivitas belajar siswa (debounce 30 menit, toggle log_aktivitas_status).
// Kegagalan logging tidak boleh merusak halaman — ditangani di dalam fungsi.
catat_log_aktivitas($pdo, $modul_id, 'buka_modul');

$cek_sesi = $pdo->query("SELECT status FROM notifikasi_sesi ORDER BY id DESC LIMIT 1")->fetchColumn();

// Cek apakah modul sudah tuntas
$sudah_tuntas = false;
$stmtTuntas = $pdo->prepare("SELECT id FROM modul_tuntas WHERE user_id = ? AND modul_id = ? LIMIT 1");
$stmtTuntas->execute([$user_id, $modul_id]);
$sudah_tuntas = (bool)$stmtTuntas->fetchColumn();

// Notifikasi WA (tetap seperti sebelumnya)
if ($cek_sesi === 'aktif') {
    $cek = $pdo->prepare("SELECT id FROM notifikasi_modul WHERE user_id = ? AND modul_id = ?");
    $cek->execute([$user_id, $modul_id]);

    if (!$cek->fetch()) {
        try {
            $insert = $pdo->prepare("INSERT INTO notifikasi_modul (user_id, modul_id, terkirim_at) VALUES (?, ?, NOW())");
            $insert->execute([$user_id, $modul_id]);

            $stmt_wa = $pdo->prepare("SELECT no_wa_ortu FROM users WHERE id = ?");
            $stmt_wa->execute([$user_id]);
            $no_wa = $stmt_wa->fetchColumn();

            if ($no_wa) {
                $tanggal = date('d M Y, H:i');
                $pesan = "📚 *Pusat Pembelajaran SIJA*\n\n" . htmlspecialchars($username) . " membuka modul:\n*" . htmlspecialchars($modul['title']) . "*\n\nPada: $tanggal";
                kirimWA($no_wa, $pesan);
            }
        } catch (PDOException $e) {
            error_log("Notifikasi modul gagal: " . $e->getMessage());
        }
    }
}

$fileUrl = trim($modul['file_path']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($modul['title'] ?? 'Modul') ?> - SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container px-4 px-lg-5">
        <a class="navbar-brand" href="index.php">Pusat Pembelajaran SIJA</a>
        <div class="d-flex gap-3 align-items-center">
            <?php if (isset($_SESSION['username'])) : ?>
                <span class="text-secondary fw-medium d-none d-md-inline small">
                    👋 Hai, <strong class="text-dark"><?= htmlspecialchars($_SESSION['username']); ?></strong>
                </span>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container py-4" style="max-width: 760px;">
    <div class="mb-3">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">← Kembali</a>
    </div>

    <div class="card shadow-sm border-0 rounded-3 mb-3">
        <div class="card-body">
            <div class="fw-bold fs-5"><?= htmlspecialchars($modul['title'] ?? '') ?></div>
            <div class="text-muted small"><?= htmlspecialchars($modul['description'] ?? '') ?></div>
            <div class="text-muted small mt-2">
                Status: <span class="fw-semibold">Terbuka</span>
            </div>
        </div>
    </div>

    <div class="d-grid gap-2">
        <a id="bukaModulBtn" class="btn btn-primary btn-lg fw-bold" href="<?= htmlspecialchars($fileUrl) ?>" target="_blank" rel="noopener noreferrer">
            📂 Buka Modul di Tab Baru
        </a>

        <a id="checkpointBtn" class="btn btn-success btn-lg fw-bold disabled" style="pointer-events:none;" href="#">
            ⏳ Buka dan Baca Modul Terlebih Dahulu  
        </a>

        <div id="achievementArea" class="mt-3" style="display:none;">
            <div class="card border-0 shadow-sm rounded-3 bg-gradient" style="background: linear-gradient(135deg, #ffd700 0%, #ff8c00 100%);">
                <div class="card-body text-center py-4">
                    <div style="font-size: 3rem;">🏅</div>
                    <h4 class="fw-bold text-dark mb-1">Modul Sudah Tuntas!</h4>
                    <p class="text-dark mb-0 small fw-semibold">Achievement berhasil diraih 🎉</p>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-warning mt-4 mb-0 small">
        Catatan: modul dibuka di tab baru agar ukuran & tampilan tetap nyaman dibaca/simak.
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const bukaBtn = document.getElementById("bukaModulBtn");
const checkpointBtn = document.getElementById("checkpointBtn");
const achievementArea = document.getElementById("achievementArea");

let timerBerjalan = false;
checkpointBtn.style.pointerEvents = "none";

// PERBAIKAN: Menggunakan ternary operator agar tidak menyebabkan Syntax Error di JavaScript
if (<?= $sudah_tuntas ? 'true' : 'false' ?>) {
    achievementArea.style.display = 'block';
    checkpointBtn.innerHTML = '✅ Modul Tuntas';
    checkpointBtn.classList.remove('disabled');
    checkpointBtn.style.pointerEvents = 'auto';
    checkpointBtn.href = 'checkpoint_quiz.php?modul_id=<?= (int)$modul_id ?>';
}

bukaBtn.addEventListener("click", function () {
    if (timerBerjalan) return;
    timerBerjalan = true;

    let waktu = 100;
    checkpointBtn.innerHTML = "⏳ Tunggu " + waktu + " detik...";

    const hitung = setInterval(() => {
        waktu--;

        if (waktu > 0) {
            checkpointBtn.innerHTML = "⏳ Tunggu " + waktu + " detik...";
        } else {
            clearInterval(hitung);

            // Simpan ke modul_tuntas
            fetch('simpan_tuntas.php?modul_id=<?= (int)$modul_id ?>')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        checkpointBtn.classList.remove("disabled");
                        checkpointBtn.style.pointerEvents = "auto";
                        checkpointBtn.href = "checkpoint_quiz.php?modul_id=<?= (int)$modul_id ?>";
                        checkpointBtn.innerHTML = "✅ Cek Point (1 Pertanyaan)";
                        achievementArea.style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error('Gagal simpan tuntas:', err);
                    checkpointBtn.innerHTML = "❌ Gagal menyimpan, coba muat ulang";
                });
        }
    }, 1000);
});
</script>
</body>
</html>