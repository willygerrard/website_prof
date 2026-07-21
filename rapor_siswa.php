<?php
include 'koneksi.php';
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

const KKM = 75;

// Tentukan siswa_id yang mau ditampilkan
if ($_SESSION['role'] === 'admin' && isset($_GET['id'])) {
    // Admin bisa lihat siswa manapun lewat parameter ?id=
    $siswa_id = (int)$_GET['id'];
} else {
    // Siswa hanya bisa lihat rapor sendiri
    $siswa_id = $_SESSION['user_id'] ?? null;
}

if (!$siswa_id) {
    die("Data siswa tidak ditemukan.");
}

// Ambil data siswa
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$siswa_id]);
$siswa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$siswa) {
    die("Siswa tidak ditemukan.");
}

// Export ke CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $sql = "SELECT kuis_hasil.kategori, kuis_hasil.level, ks.dibuka_at AS sesi_dibuka,
                   MAX(kuis_hasil.skor) as nilai_terbaik, COUNT(*) as total_attempt
            FROM kuis_hasil 
            LEFT JOIN kuis_sesi ks ON kuis_hasil.sesi_id = ks.id
            WHERE kuis_hasil.user_id = ?
            GROUP BY kuis_hasil.kategori, kuis_hasil.level, kuis_hasil.sesi_id
            ORDER BY kuis_hasil.kategori, kuis_hasil.level, ks.dibuka_at";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$siswa_id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rapor_' . $siswa['username'] . '.csv"');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // BOM biar Excel baca UTF-8 dengan benar
    fputcsv($output, ['Nama Siswa', 'Kategori', 'Level', 'Sesi (Tanggal Deploy)', 'Nilai Terbaik', 'Jumlah Percobaan', 'Status']);

    foreach ($data as $row) {
        $status = $row['nilai_terbaik'] >= KKM ? 'Lulus' : ($row['total_attempt'] >= 4 ? 'Tidak Lulus' : 'Belum Tuntas');
        $sesi_label = $row['sesi_dibuka'] ? date('d M Y', strtotime($row['sesi_dibuka'])) : 'Riwayat lama';
        fputcsv($output, [$siswa['username'], $row['kategori'], ucfirst($row['level']), $sesi_label, $row['nilai_terbaik'], $row['total_attempt'], $status]);
    }
    fclose($output);
    exit();
}

// Ambil ringkasan nilai PER SESI (bukan gabungan semua deploy kategori+level yang sama)
$sql = "SELECT kuis_hasil.kategori, kuis_hasil.level, kuis_hasil.sesi_id, ks.dibuka_at AS sesi_dibuka,
               MAX(kuis_hasil.skor) as nilai_terbaik, COUNT(*) as total_attempt,
               MAX(kuis_hasil.dikerjakan_at) as terakhir_dikerjakan
        FROM kuis_hasil
        LEFT JOIN kuis_sesi ks ON kuis_hasil.sesi_id = ks.id
        WHERE kuis_hasil.user_id = ?
        GROUP BY kuis_hasil.kategori, kuis_hasil.level, kuis_hasil.sesi_id
        ORDER BY kuis_hasil.kategori, kuis_hasil.level, ks.dibuka_at";
$stmt = $pdo->prepare($sql);
$stmt->execute([$siswa_id]);
$ringkasan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Riwayat semua attempt
$stmt2 = $pdo->prepare("SELECT * FROM kuis_hasil WHERE user_id = ? ORDER BY dikerjakan_at DESC");
$stmt2->execute([$siswa_id]);
$riwayat = $stmt2->fetchAll(PDO::FETCH_ASSOC);

$level_badge = [
    'pemula'   => ['🟢 Pemula', 'success'],
    'menengah' => ['🟡 Menengah', 'warning'],
    'mahir'    => ['🔴 Mahir', 'danger'],
];

// Statistik ringkas
$total_lulus = 0;
$total_sesi_dikerjakan = count($ringkasan); // jumlah baris = jumlah sesi/deploy yang pernah dikerjakan
$kategori_unik = [];
foreach ($ringkasan as $r) {
    if ($r['nilai_terbaik'] >= KKM) $total_lulus++;
    $kategori_unik[$r['kategori'] . '|' . $r['level']] = true;
}
$total_kategori = count($kategori_unik);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapor Nilai - Pusat Pembelajaran SIJA</title>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold m-0 text-dark">🎓 Rapor Nilai — <?= htmlspecialchars($siswa['username']) ?></h4>
            <div class="btn-group gap-2">
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="rekap_nilai.php" class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Kembali ke Rekap">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
                <?php else: ?>
                <a href="index.php" class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Kembali ke Beranda">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
                <?php endif; ?>
                <a href="?id=<?= $siswa_id ?>&export=csv" class="btn btn-success rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Export ke Excel/CSV">
                    <i class="bi bi-file-earmark-excel"></i>
                </a>
            </div>
        </div>

        <!-- STATISTIK RINGKAS -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-3 text-center p-3">
                    <div class="fs-2 fw-bold text-primary"><?= $total_kategori ?></div>
                    <div class="text-muted small">Kategori Dikerjakan</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-3 text-center p-3">
                    <div class="fs-2 fw-bold text-success"><?= $total_lulus ?></div>
                    <div class="text-muted small">Sesi Lulus</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-3 text-center p-3">
                    <div class="fs-2 fw-bold text-warning"><?= $total_sesi_dikerjakan - $total_lulus ?></div>
                    <div class="text-muted small">Sesi Belum Lulus</div>
                </div>
            </div>
        </div>

        <!-- RINGKASAN NILAI -->
        <h5 class="fw-bold mb-3">📊 Ringkasan Nilai per Kategori</h5>
        <div class="table-responsive bg-white p-4 rounded-3 shadow-sm border mb-5">
            <table class="table table-hover align-middle m-0">
                <thead class="table-light">
                    <tr>
                        <th>Kategori</th>
                        <th>Level</th>
                        <th>Sesi</th>
                        <th>Nilai Terbaik</th>
                        <th>Percobaan</th>
                        <th>Terakhir Dikerjakan</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ringkasan)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada kuis yang dikerjakan.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($ringkasan as $r):
                        $lvl = $level_badge[$r['level']] ?? ['-', 'secondary'];
                        $lulus = $r['nilai_terbaik'] >= KKM;
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($r['kategori']) ?></td>
                        <td><span class="badge bg-<?= $lvl[1] ?>"><?= $lvl[0] ?></span></td>
                        <td class="small text-muted"><?= $r['sesi_dibuka'] ? date('d M Y', strtotime($r['sesi_dibuka'])) : 'Riwayat lama' ?></td>
                        <td class="fw-bold fs-5 <?= $lulus ? 'text-success' : 'text-danger' ?>"><?= $r['nilai_terbaik'] ?></td>
                        <td><?= $r['total_attempt'] ?> / 4</td>
                        <td class="text-muted small"><?= date('d M Y', strtotime($r['terakhir_dikerjakan'])) ?></td>
                        <td>
                            <?php if ($lulus): ?>
                                <span class="badge bg-success">✅ Lulus</span>
                            <?php elseif ($r['total_attempt'] >= 4): ?>
                                <span class="badge bg-danger">❌ Tidak Lulus</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">⏳ Belum Tuntas</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- RIWAYAT LENGKAP -->
        <h5 class="fw-bold mb-3">📜 Riwayat Semua Percobaan</h5>
        <div class="table-responsive bg-white p-4 rounded-3 shadow-sm border">
            <table class="table table-sm align-middle m-0">
                <thead class="table-light">
                    <tr>
                        <th>Kategori</th>
                        <th>Level</th>
                        <th>Skor</th>
                        <th>Attempt ke-</th>
                        <th>Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($riwayat)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada riwayat.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($riwayat as $h):
                        $lvl = $level_badge[$h['level']] ?? ['-', 'secondary'];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($h['kategori']) ?></td>
                        <td><span class="badge bg-<?= $lvl[1] ?>"><?= $lvl[0] ?></span></td>
                        <td class="fw-bold <?= $h['skor'] >= KKM ? 'text-success' : 'text-danger' ?>"><?= $h['skor'] ?></td>
                        <td><?= $h['attempt'] ?></td>
                        <td class="text-muted small"><?= date('d M Y, H:i', strtotime($h['dikerjakan_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="py-5 bg-dark">
        <div class="container"><p class="m-0 text-center text-white">Copyright &copy; SIJA Website 2026</p></div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>