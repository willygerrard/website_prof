<?php
include 'koneksi.php';
session_start();
include 'csrf_helper.php';

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("HTTP/1.1 404 Not Found");
    exit();
}

$pesan = '';
$pesan_type = '';

// Daftar kelas yang ada, diambil dari data siswa (bukan hardcode)
$kelas_options = $pdo->query("SELECT DISTINCT kelas FROM users WHERE kelas IS NOT NULL AND kelas <> '' ORDER BY kelas")
                      ->fetchAll(PDO::FETCH_COLUMN);

// Deploy sesi baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deploy'])) {
    csrf_require_valid_post();
    $kategori     = trim($_POST['kategori'] ?? '');
    $level        = trim($_POST['level'] ?? '');
    $durasi       = (int)($_POST['durasi_menit'] ?? 30);
    $kelas_target = $_POST['kelas'] ?? [];
    $kelas_target = array_values(array_unique(array_filter(array_map('trim', $kelas_target))));

    if ($kategori && $level && $durasi > 0 && !empty($kelas_target)) {

        // Cek apakah ada sesi aktif untuk kategori+level yang sama DAN kelasnya overlap
        $placeholders = implode(',', array_fill(0, count($kelas_target), '?'));
        $cek = $pdo->prepare("
            SELECT DISTINCT ks.id, ksk.kelas
            FROM kuis_sesi ks
            JOIN kuis_sesi_kelas ksk ON ks.id = ksk.sesi_id
            WHERE ks.kategori = ? AND ks.level = ? AND ks.status = 'aktif'
              AND ksk.kelas IN ($placeholders)
        ");
        $cek->execute(array_merge([$kategori, $level], $kelas_target));
        $bentrok = $cek->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($bentrok)) {
            $kelas_bentrok = implode(', ', array_unique(array_column($bentrok, 'kelas')));
            $pesan = "⚠️ Sudah ada sesi aktif untuk $kategori - $level di kelas: $kelas_bentrok. Tutup dulu sesi yang lama untuk kelas tersebut.";
            $pesan_type = 'warning';
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO kuis_sesi (kategori, level, status, durasi_menit, dibuka_at) VALUES (?, ?, 'aktif', ?, NOW())");
                $stmt->execute([$kategori, $level, $durasi]);
                $sesi_id = $pdo->lastInsertId();

                $stmtKelas = $pdo->prepare("INSERT INTO kuis_sesi_kelas (sesi_id, kelas) VALUES (?, ?)");
                foreach ($kelas_target as $k) {
                    $stmtKelas->execute([$sesi_id, $k]);
                }

                $pdo->commit();
                $pesan = "✅ Kuis $kategori - $level berhasil di-deploy ke kelas: " . implode(', ', $kelas_target) . "!";
                $pesan_type = 'success';
            } catch (Exception $e) {
                $pdo->rollBack();
                $pesan = "❌ Gagal deploy kuis. Coba lagi.";
                $pesan_type = 'danger';
            }
        }
    } else {
        $pesan = "⚠️ Lengkapi semua field, minimal pilih 1 kelas!";
        $pesan_type = 'danger';
    }
}

// Tutup sesi (POST, sudah dilindungi CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tutup'])) {
    csrf_require_valid_post();
    $id = (int)$_POST['tutup'];
    $stmt = $pdo->prepare("UPDATE kuis_sesi SET status = 'nonaktif', ditutup_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: deploy-kuis-sija");
    exit();
}

// Sesi aktif, sekalian ambil daftar kelas targetnya (GROUP_CONCAT)
$sesi_aktif = $pdo->query("
    SELECT ks.*, GROUP_CONCAT(ksk.kelas ORDER BY ksk.kelas SEPARATOR ', ') AS kelas_target
    FROM kuis_sesi ks
    LEFT JOIN kuis_sesi_kelas ksk ON ks.id = ksk.sesi_id
    WHERE ks.status = 'aktif'
    GROUP BY ks.id
    ORDER BY ks.dibuka_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$sesi_riwayat = $pdo->query("
    SELECT ks.*, GROUP_CONCAT(ksk.kelas ORDER BY ksk.kelas SEPARATOR ', ') AS kelas_target
    FROM kuis_sesi ks
    LEFT JOIN kuis_sesi_kelas ksk ON ks.id = ksk.sesi_id
    WHERE ks.status = 'nonaktif'
    GROUP BY ks.id
    ORDER BY ks.ditutup_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$level_badge = [
    'pemula'   => ['🟢 Pemula', 'success'],
    'menengah' => ['🟡 Menengah', 'warning'],
    'mahir'    => ['🔴 Mahir', 'danger'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deploy Kuis - Pusat Pembelajaran SIJA</title>
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
    <div class="container mt-5 mb-5">

        <?php if ($pesan): ?>
        <div class="alert alert-<?= $pesan_type ?> alert-dismissible fade show" role="alert">
            <?= $pesan ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold m-0 text-dark">🚀 Deploy Kuis Resmi</h4>
            <a href="pintu-rahasia-sija" class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Kembali ke Manage Kuis">
                <i class="bi bi-arrow-counterclockwise"></i>
            </a>
        </div>

        <!-- FORM DEPLOY -->
        <div class="card shadow-sm border-0 rounded-3 mb-5">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="card-title mb-0 fw-bold">Mulai Sesi Kuis Baru</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Kategori</label>
                        <select class="form-select" name="kategori" required>
                            <option value="" selected disabled>-- Pilih --</option>
                            <option value="Network">Network</option>
                            <option value="IoT">Internet of Things (IoT)</option>
                            <option value="Cloud Computing">Cloud Computing</option>
                            <option value="DevOps">DevOps</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Level</label>
                        <select class="form-select" name="level" required>
                            <option value="" selected disabled>-- Pilih --</option>
                            <option value="pemula">🟢 Pemula</option>
                            <option value="menengah">🟡 Menengah</option>
                            <option value="mahir">🔴 Mahir</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Durasi (menit)</label>
                        <input type="number" class="form-control" name="durasi_menit" value="30" min="5" max="180" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Kelas Tujuan</label>
                        <?php if (empty($kelas_options)): ?>
                            <div class="alert alert-warning small mb-0">
                                Belum ada data kelas di tabel siswa. Pastikan kolom "kelas" sudah terisi.
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-wrap gap-3 border rounded-3 p-3 bg-light">
                                <?php foreach ($kelas_options as $k): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="kelas[]"
                                           value="<?= htmlspecialchars($k) ?>" id="kelas_<?= md5($k) ?>">
                                    <label class="form-check-label" for="kelas_<?= md5($k) ?>">
                                        <?= htmlspecialchars($k) ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text">Pilih 1 atau lebih kelas yang akan mengerjakan kuis ini.</div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12">
                        <button type="submit" name="deploy" class="btn btn-success fw-bold px-4">
                            <i class="bi bi-rocket-takeoff"></i> Deploy Kuis Sekarang
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- SESI AKTIF -->
        <h5 class="fw-bold mb-3">🟢 Sesi Sedang Berjalan</h5>
        <?php if (empty($sesi_aktif)): ?>
            <p class="text-muted">Belum ada sesi kuis yang aktif.</p>
        <?php else: ?>
        <div class="table-responsive bg-white p-4 rounded-3 shadow-sm border mb-5">
            <table class="table table-hover align-middle m-0">
                <thead class="table-light">
                    <tr>
                        <th>Kategori</th>
                        <th>Level</th>
                        <th>Kelas Tujuan</th>
                        <th>Durasi</th>
                        <th>Dibuka Pukul</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sesi_aktif as $s): $lvl = $level_badge[$s['level']] ?? ['-', 'secondary']; ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($s['kategori']) ?></td>
                        <td><span class="badge bg-<?= $lvl[1] ?>"><?= $lvl[0] ?></span></td>
                        <td><span class="small"><?= htmlspecialchars($s['kelas_target'] ?? '-') ?></span></td>
                        <td><?= $s['durasi_menit'] ?> menit</td>
                        <td><?= date('d M Y, H:i', strtotime($s['dibuka_at'])) ?></td>
                        <td class="text-center">
                            <form method="POST" style="display:inline" onsubmit="return confirm('Tutup sesi ini? Siswa tidak bisa mengerjakan lagi.')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="tutup" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger fw-bold">
                                    <i class="bi bi-stop-circle"></i> Tutup Sesi
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- RIWAYAT -->
        <h5 class="fw-bold mb-3">📜 Riwayat Sesi Terakhir</h5>
        <?php if (empty($sesi_riwayat)): ?>
            <p class="text-muted">Belum ada riwayat sesi.</p>
        <?php else: ?>
        <div class="table-responsive bg-white p-4 rounded-3 shadow-sm border">
            <table class="table table-sm align-middle m-0">
                <thead class="table-light">
                    <tr>
                        <th>Kategori</th>
                        <th>Level</th>
                        <th>Kelas Tujuan</th>
                        <th>Dibuka</th>
                        <th>Ditutup</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sesi_riwayat as $s): $lvl = $level_badge[$s['level']] ?? ['-', 'secondary']; ?>
                    <tr class="text-muted">
                        <td><?= htmlspecialchars($s['kategori']) ?></td>
                        <td><span class="badge bg-<?= $lvl[1] ?>"><?= $lvl[0] ?></span></td>
                        <td class="small"><?= htmlspecialchars($s['kelas_target'] ?? '-') ?></td>
                        <td><?= date('d M Y, H:i', strtotime($s['dibuka_at'])) ?></td>
                        <td><?= $s['ditutup_at'] ? date('d M Y, H:i', strtotime($s['ditutup_at'])) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>

    <!-- FOOTER -->
    <footer class="py-5 bg-dark">
        <div class="container"><p class="m-0 text-center text-white">Copyright &copy; SIJA Website 2026</p></div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
