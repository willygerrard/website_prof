<?php
require_once 'session.php';
require_once 'csrf_helper.php';
require 'koneksi.php';
    
checkLogin();

$user_id  = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? '';
$modul_id = (int)($_GET['modul_id'] ?? 0);

if (!$user_id || !$modul_id) {
    die('modul_id atau sesi user tidak valid.');
}

$stmt = $pdo->prepare("SELECT * FROM checkpoint_modul WHERE modul_id = ? LIMIT 1");
$stmt->execute([$modul_id]);
$q = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$q) {
    die('Belum ada checkpoint untuk modul ini.');
}

$questionText = $q['pertanyaan'];
$optionA = $q['opsi_a'];
$optionB = $q['opsi_b'];
$correctKey = $q['jawaban_benar'];

$csrf_token = csrf_token();
$pesan_error = '';
$done = false;
$lulus = false;

// Cek: apakah user SUDAH LULUS checkpoint ini sebelumnya?
$stmtCek = $pdo->prepare("SELECT id FROM checkpoint_hasil WHERE user_id=? AND modul_id=? AND is_correct=1 LIMIT 1");
$stmtCek->execute([$user_id, $modul_id]);
$sudah_lulus = (bool)$stmtCek->fetchColumn();

if ($sudah_lulus) {
    $done = true;
    $lulus = true;
}

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_checkpoint']) && !$sudah_lulus) {
    
    $jawaban = $_POST['jawaban'] ?? '';
    $jawaban = in_array($jawaban, ['a','b','c','d'], true) ? $jawaban : '';

    if (!$jawaban) {
        $pesan_error = 'Jawaban wajib dipilih.';
    } else {
        $modul_checkpoint_id = $q['id'] ?? null;
        $is_correct = ((string)$jawaban === (string)$correctKey) ? 1 : 0;

        // PERBAIKAN: Tambahkan processed_at = NOW()
        $stmt = $pdo->prepare(
            "INSERT INTO checkpoint_hasil (user_id, modul_id, modul_checkpoint_id, is_correct, processed_at) 
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$user_id, $modul_id, $modul_checkpoint_id, $is_correct]);
        $done = true;
        $lulus = ($is_correct === 1);

        // TAMBAHAN BARU: Jika benar, catat achievement di modul_tuntas
        if ($is_correct === 1) {
            try {
                $stmtTuntas = $pdo->prepare(
                    "INSERT IGNORE INTO modul_tuntas (user_id, modul_id, tuntas_at) VALUES (?, ?, NOW())"
                );
                $stmtTuntas->execute([$user_id, $modul_id]);
            } catch (PDOException $e) {
                error_log("Gagal simpan achievement: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkpoint Modul - SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container px-4 px-lg-5">
        <a class="navbar-brand" href="index.php">Pusat Pembelajaran SIJA</a>
        <div class="d-flex align-items-center gap-3">
            <?php if (isset($_SESSION['username'])) : ?>
                <span class="text-secondary fw-medium d-none d-md-inline small">👋 Hai, <strong class="text-dark"><?= htmlspecialchars($_SESSION['username']); ?></strong></span>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container py-4" style="max-width: 800px;">

    <?php if ($done && $lulus): ?>
        <div class="card border-0 shadow-sm rounded-3 bg-gradient mb-3" style="background: linear-gradient(135deg, #ffd700 0%, #ff8c00 100%);">
            <div class="card-body text-center py-4">
                <div style="font-size: 3rem;">🏅</div>
                <h4 class="fw-bold text-dark mb-1">Checkpoint Lulus!</h4>
                <p class="text-dark mb-0 small fw-semibold">Achievement berhasil diraih 🎉</p>
            </div>
        </div>
        <a class="btn btn-primary w-100" href="index.php">Kembali ke Materi</a>

    <?php elseif ($done && !$lulus): ?>
        <div class="alert alert-warning shadow-sm" role="alert">
            <div class="d-flex align-items-center gap-2">
                <div style="font-size: 2rem;">⚠️</div>
                <div>
                    <div class="fw-bold">Jawaban Belum Tepat</div>
                    <div class="small text-secondary">Coba baca ulang modulnya, lalu jawab lagi ya!</div>
                </div>
            </div>
        </div>

        <!-- Tampilkan form lagi supaya bisa retry -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-primary text-white">
                <strong>🧩 Coba Lagi</strong>
            </div>
            <div class="card-body p-4">
                <p class="fw-semibold mb-3"><?= htmlspecialchars($questionText) ?></p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div class="list-group mb-3">
                        <label class="list-group-item list-group-item-action">
                            <input class="form-check-input me-2" type="radio" name="jawaban" value="a" required>
                            <span><?= htmlspecialchars('A) ' . $optionA) ?></span>
                        </label>
                        <label class="list-group-item list-group-item-action">
                            <input class="form-check-input me-2" type="radio" name="jawaban" value="b" required>
                            <span><?= htmlspecialchars('B) ' . $optionB) ?></span>
                        </label>
                    </div>
                    <button type="submit" name="submit_checkpoint" class="btn btn-success w-100 fw-bold">
                        Submit Jawaban
                    </button>
                </form>
            </div>
        </div>
        <a class="btn btn-outline-secondary w-100 mt-3" href="index.php">Kembali ke Materi</a>

    <?php else: ?>
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-primary text-white">
                <strong>🧩 Pertanyaan Checkpoint</strong>
            </div>
            <div class="card-body p-4">
                <p class="fw-semibold mb-3"><?= htmlspecialchars($questionText) ?></p>

                <?php if ($pesan_error): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars($pesan_error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div class="list-group mb-3">
                        <label class="list-group-item list-group-item-action">
                            <input class="form-check-input me-2" type="radio" name="jawaban" value="a" required>
                            <span><?= htmlspecialchars('A) ' . $optionA) ?></span>
                        </label>
                        <label class="list-group-item list-group-item-action">
                            <input class="form-check-input me-2" type="radio" name="jawaban" value="b" required>
                            <span><?= htmlspecialchars('B) ' . $optionB) ?></span>
                        </label>
                    </div>
                    <button type="submit" name="submit_checkpoint" class="btn btn-success w-100 fw-bold">
                        Submit Jawaban
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

</div>
</body>
</html>