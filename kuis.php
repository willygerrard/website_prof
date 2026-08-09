<?php
include 'koneksi.php';
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
if (!$user_id) {
    die("Sesi user tidak valid. Silakan login ulang.");
}

const MAX_ATTEMPT = 4; // 1 awal + 3 remidi
const KKM = 75;

// Ambil kelas siswa langsung dari DB (bukan session) supaya selalu akurat
// walau session belum menyimpan kelas.
$stmtKelas = $pdo->prepare("SELECT kelas FROM users WHERE id = ?");
$stmtKelas->execute([$user_id]);
$kelas_siswa = $stmtKelas->fetchColumn();

if (!$kelas_siswa) {
    die("Data kelas kamu belum diisi oleh admin. Hubungi guru/admin untuk melengkapi data kelas.");
}

// Ambil sesi aktif yang ditujukan untuk kelas siswa ini saja
$stmt = $pdo->prepare("
    SELECT ks.*
    FROM kuis_sesi ks
    JOIN kuis_sesi_kelas ksk ON ks.id = ksk.sesi_id
    WHERE ks.status = 'aktif' AND ksk.kelas = ?
    ORDER BY ks.kategori, ks.level
");
$stmt->execute([$kelas_siswa]);
$sesi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$level_badge = [
    'pemula'   => ['🟢 Pemula', 'success'],
    'menengah' => ['🟡 Menengah', 'warning'],
    'mahir'    => ['🔴 Mahir', 'danger'],
];

// Hitung status attempt siswa untuk setiap sesi (PER SESI, bukan gabungan semua deploy dengan kategori+level sama)
foreach ($sesi_list as &$sesi) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total, MAX(skor) as nilai_terbaik FROM kuis_hasil WHERE user_id = ? AND sesi_id = ?");
    $stmt->execute([$user_id, $sesi['id']]);
    $hasil = $stmt->fetch(PDO::FETCH_ASSOC);

    $sesi['total_attempt'] = (int)$hasil['total'];
    $sesi['nilai_terbaik'] = $hasil['nilai_terbaik'] !== null ? (int)$hasil['nilai_terbaik'] : null;
    $sesi['sudah_lulus']   = $sesi['nilai_terbaik'] !== null && $sesi['nilai_terbaik'] >= KKM;
    $sesi['habis_attempt'] = $sesi['total_attempt'] >= MAX_ATTEMPT;
}
unset($sesi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kuis - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

    <?php include 'includes/admin_header.php'; ?>

    <!-- KONTEN -->
    <div class="container mt-5 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold m-0 text-dark">📝 Kuis yang Sedang Berlangsung</h4>
            <span class="badge bg-dark"><?= htmlspecialchars($kelas_siswa) ?></span>
        </div>

        <?php if (empty($sesi_list)): ?>
        <div class="alert alert-secondary text-center py-5">
            <i class="bi bi-hourglass-split fs-1 d-block mb-2"></i>
            Belum ada kuis yang dibuka untuk kelas kamu. Cek lagi nanti ya!
        </div>
        <?php else: ?>

        <div class="row g-4">
            <?php foreach ($sesi_list as $sesi): $lvl = $level_badge[$sesi['level']] ?? ['-', 'secondary']; ?>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0 rounded-3">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title fw-bold mb-0"><?= htmlspecialchars($sesi['kategori']) ?></h5>
                            <span class="badge bg-<?= $lvl[1] ?>"><?= $lvl[0] ?></span>
                        </div>
                        <p class="text-muted small mb-3">
                            <i class="bi bi-clock"></i> Durasi: <?= $sesi['durasi_menit'] ?> menit
                        </p>

                        <?php if ($sesi['sudah_lulus']): ?>
                            <div class="alert alert-success small mb-3">
                                ✅ Lulus! Nilai terbaik: <strong><?= $sesi['nilai_terbaik'] ?></strong>
                            </div>
                            <button class="btn btn-success w-100 mt-auto" disabled>Sudah Lulus</button>

                        <?php elseif ($sesi['habis_attempt']): ?>
                            <div class="alert alert-danger small mb-3">
                                Kesempatan habis, nilai terbaik: <strong><?= $sesi['nilai_terbaik'] ?? 0 ?></strong>
                            </div>
                            <button class="btn btn-secondary w-100 mt-auto" disabled>Kesempatan Habis</button>

                        <?php else: ?>
                            <p class="small text-secondary mb-3">
                                Percobaan ke-<strong><?= $sesi['total_attempt'] + 1 ?></strong> dari <?= MAX_ATTEMPT ?>
                                <?php if ($sesi['nilai_terbaik'] !== null): ?>
                                    <br>Nilai terbaik sebelumnya: <strong><?= $sesi['nilai_terbaik'] ?></strong>
                                <?php endif; ?>
                            </p>
                            <a href="kerjakan_kuis.php?sesi=<?= $sesi['id'] ?>" class="btn btn-primary w-100 mt-auto fw-bold">
                                <i class="bi bi-play-fill"></i> Mulai Kuis
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
