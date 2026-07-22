<?php
include 'koneksi.php';
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    header("HTTP/1.1 404 Not Found");
    exit();
}

const KKM = 75;

// Filter - Tambah filter kelas
$kategori_filter = $_GET['kategori'] ?? '';
$level_filter    = $_GET['level'] ?? '';
$status_filter   = $_GET['status'] ?? '';
$kelas_filter    = $_GET['kelas'] ?? ''; // 🔥 Filter kelas baru
$materi_filter   = $_GET['materi'] ?? ''; // 🔥 Filter materi baru

// Query 1: Riwayat Attempt (Tambah users.kelas + info sesi)
$sql = "SELECT kuis_hasil.*, users.username, users.nama_asli, users.kelas, ks.dibuka_at AS sesi_dibuka,
               (SELECT GROUP_CONCAT(materi SEPARATOR ', ') FROM kuis_sesi_materi WHERE sesi_id = ks.id) AS materi
        FROM kuis_hasil 
        JOIN users ON kuis_hasil.user_id = users.id 
        LEFT JOIN kuis_sesi ks ON kuis_hasil.sesi_id = ks.id
        WHERE 1=1";
$params = [];

if ($kategori_filter) {
    $sql .= " AND kuis_hasil.kategori = ?";
    $params[] = $kategori_filter;
}
if ($level_filter) {
    $sql .= " AND kuis_hasil.level = ?";
    $params[] = $level_filter;
}
if ($kelas_filter) { // 🔥 Inject filter kelas ke query
    $sql .= " AND users.kelas = ?";
    $params[] = $kelas_filter;
}
if ($materi_filter) { // 🔥 Inject filter materi ke query
    $sql .= " AND ks.materi = ?";
    $params[] = $materi_filter;
}
if ($status_filter === 'lulus') {
    $sql .= " AND kuis_hasil.skor >= " . KKM;
} elseif ($status_filter === 'belum') {
    $sql .= " AND kuis_hasil.skor < " . KKM;
}

$sql .= " ORDER BY kuis_hasil.dikerjakan_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$hasil_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query 2: Ringkasan per siswa PER SESI (bukan gabungan semua deploy kategori+level yang sama)
$ringkasan_sql = "SELECT users.id as user_id, users.username, users.nama_asli, users.kelas,
                          kuis_hasil.kategori, kuis_hasil.level, kuis_hasil.sesi_id,
                          ks.dibuka_at AS sesi_dibuka,
                          MAX(kuis_hasil.skor) as nilai_terbaik,
                          COUNT(*) as total_attempt,
                          (SELECT GROUP_CONCAT(materi SEPARATOR ', ') FROM kuis_sesi_materi WHERE sesi_id = ks.id) AS materi
                   FROM kuis_hasil
                   JOIN users ON kuis_hasil.user_id = users.id
                   LEFT JOIN kuis_sesi ks ON kuis_hasil.sesi_id = ks.id
                   GROUP BY users.id, kuis_hasil.kategori, kuis_hasil.level, kuis_hasil.sesi_id
                   ORDER BY users.kelas ASC, users.username ASC, kuis_hasil.kategori ASC, kuis_hasil.level ASC, ks.dibuka_at ASC";
$ringkasan = $pdo->query($ringkasan_sql)->fetchAll(PDO::FETCH_ASSOC);

$level_badge = [
    'pemula'   => ['🟢 Pemula', 'success'],
    'menengah' => ['🟡 Menengah', 'warning'],
    'mahir'    => ['🔴 Mahir', 'danger'],
];

// Ambil daftar unik kelas yang ada di database untuk opsi filter dropdown otomatis
$list_kelas_sql = "SELECT DISTINCT kelas FROM users WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC";
$daftar_kelas = $pdo->query($list_kelas_sql)->fetchAll(PDO::FETCH_COLUMN);

// Ambil daftar unik materi yang ada di database untuk opsi filter dropdown
$list_materi_sql = "SELECT DISTINCT materi FROM kuis_sesi WHERE materi IS NOT NULL AND materi != '' ORDER BY materi ASC";
$daftar_materi = $pdo->query($list_materi_sql)->fetchAll(PDO::FETCH_COLUMN);

// Daftar unik siswa untuk tombol akses cepat rapor
$siswa_unik = [];
foreach ($ringkasan as $r) {
    $siswa_unik[$r['user_id']] = $r['username'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Nilai Kuis - Pusat Pembelajaran SIJA</title>
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
            <h4 class="fw-bold m-0 text-dark">📊 Rekap Nilai Kuis</h4>
            <a href="pintu-rahasia-sija" class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Kembali">
                <i class="bi bi-arrow-counterclockwise"></i>
            </a>
        </div>
<h5 class="fw-bold mb-3">📁 Pilih Rekap Per Kelas</h5>
        <div class="mb-4">
            <a href="rekap_nilai.php" class="btn btn-sm <?= empty($kelas_filter) ? 'btn-dark' : 'btn-outline-dark' ?> me-2">
                🌍 Semua Kelas
            </a>
            <?php foreach ($daftar_kelas as $k): ?>
                <a href="rekap_nilai.php?kelas=<?= urlencode($k) ?>" class="btn btn-sm <?= $kelas_filter === $k ? 'btn-dark' : 'btn-outline-dark' ?> me-2">
                    📦 Kelas <?= htmlspecialchars($k) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <h5 class="fw-bold mb-3">🏆 Ringkasan Nilai Terbaik <?= $kelas_filter ? 'Kelas ' . htmlspecialchars($kelas_filter) : 'Semua Siswa' ?></h5>
        <div class="table-responsive bg-white p-4 rounded-3 shadow-sm border mb-5">
            <table class="table table-hover align-middle m-0">
                <thead class="table-light">
                    <tr>
                        <th>Kelas</th>
                        <th>Siswa</th>
                        <th>Kategori</th>
                        <th>Level</th>
                        <th>Materi</th>
                        <th>Sesi</th>
                        <th>Nilai Terbaik</th>
                        <th>Attempt</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $ada_data_ringkasan = false;
                    foreach ($ringkasan as $r): 
                        // 🔥 FILTER RINGKASAN AGAR MENGIKUTI SELEKSI KELAS
                        if ($kelas_filter && $r['kelas'] !== $kelas_filter) continue;
                        
                        $ada_data_ringkasan = true;
                        $lvl = $level_badge[$r['level']] ?? ['-', 'secondary'];
                        $lulus = $r['nilai_terbaik'] >= KKM;
                    ?>
                    <tr>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($r['kelas'] ?? '-') ?></span></td>
                        <td class="fw-semibold"><?= htmlspecialchars($r['nama_asli'] ?: $r['username']) ?></td>
                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($r['kategori']) ?></span></td>
                        <td><span class="badge bg-<?= $lvl[1] ?>"><?= $lvl[0] ?></span></td>
                        <td><?= htmlspecialchars($r['materi'] ?? '-') ?></td>
                        <td class="small text-muted"><?= $r['sesi_dibuka'] ? date('d M Y', strtotime($r['sesi_dibuka'])) : 'Riwayat lama' ?></td>
                        <td class="fw-bold"><?= $r['nilai_terbaik'] ?></td>
                        <td><?= $r['total_attempt'] ?> / 4</td>
                        <td>
                            <?php if ($lulus): ?>
                                <span class="badge bg-success">✅ Lulus</span>
                            <?php elseif ($r['total_attempt'] >= 4): ?>
                                <span class="badge bg-danger">❌ Tidak Lulus</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">⏳ Belum Tuntas</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="rapor_siswa.php?id=<?= $r['user_id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-file-text"></i> Rapor
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (!$ada_data_ringkasan): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">Tidak ada data untuk kelas ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- RINGKASAN PER SISWA -->
        <h5 class="fw-bold mb-3">🏆 Ringkasan Nilai Terbaik per Siswa</h5>
        <div class="table-responsive bg-white p-4 rounded-3 shadow-sm border mb-5">
            <table class="table table-hover align-middle m-0">
                <thead class="table-light">
                    <tr>
                        <th>Kelas</th> <!-- 🔥 Kolom Kelas Baru -->
                        <th>Siswa</th>
                        <th>Kategori</th>
                        <th>Level</th>
                        <th>Materi</th>
                        <th>Sesi</th>
                        <th>Nilai Terbaik</th>
                        <th>Attempt</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ringkasan)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data kuis.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($ringkasan as $r): 
                        $lvl = $level_badge[$r['level']] ?? ['-', 'secondary'];
                        $lulus = $r['nilai_terbaik'] >= KKM;
                    ?>
                    <tr>
                        <!-- 🔥 Tampilkan Kelas -->
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($r['kelas'] ?? '-') ?></span></td>
                        <td class="fw-semibold"><?= htmlspecialchars($r['nama_asli'] ?: $r['username']) ?></td>
                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($r['kategori']) ?></span></td>
                        <td><span class="badge bg-<?= $lvl[1] ?>"><?= $lvl[0] ?></span></td>
                        <td><?= htmlspecialchars($r['materi'] ?? '-') ?></td>
                        <td class="small text-muted"><?= $r['sesi_dibuka'] ? date('d M Y', strtotime($r['sesi_dibuka'])) : 'Riwayat lama' ?></td>
                        <td class="fw-bold"><?= $r['nilai_terbaik'] ?></td>
                        <td><?= $r['total_attempt'] ?> / 4</td>
                        <td>
                            <?php if ($lulus): ?>
                                <span class="badge bg-success">✅ Lulus</span>
                            <?php elseif ($r['total_attempt'] >= 4): ?>
                                <span class="badge bg-danger">❌ Tidak Lulus</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">⏳ Belum Tuntas</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="rapor_siswa.php?id=<?= $r['user_id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-file-text"></i> Rapor
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- DETAIL RIWAYAT ATTEMPT -->
        <h5 class="fw-bold mb-3">📜 Riwayat Semua Attempt</h5>

        <!-- Filter -->
        <form method="GET" class="row g-2 mb-3">
            <!-- 🔥 Dropdown Filter Kelas Baru -->
            <div class="col-md-3">
                <select name="kelas" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($daftar_kelas as $k): ?>
                        <option value="<?= htmlspecialchars($k) ?>" <?= $kelas_filter === $k ? 'selected' : '' ?>><?= htmlspecialchars($k) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="kategori" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua Kategori</option>
                    <option value="Network" <?= $kategori_filter === 'Network' ? 'selected' : '' ?>>Network</option>
                    <option value="IoT" <?= $kategori_filter === 'IoT' ? 'selected' : '' ?>>IoT</option>
                    <option value="Cloud Computing" <?= $kategori_filter === 'Cloud Computing' ? 'selected' : '' ?>>Cloud Computing</option>
                    <option value="DevOps" <?= $kategori_filter === 'DevOps' ? 'selected' : '' ?>>DevOps</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="level" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua Level</option>
                    <option value="pemula" <?= $level_filter === 'pemula' ? 'selected' : '' ?>>Pemula</option>
                    <option value="menengah" <?= $level_filter === 'menengah' ? 'selected' : '' ?>>Menengah</option>
                    <option value="mahir" <?= $level_filter === 'mahir' ? 'selected' : '' ?>>Mahir</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="materi" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua Materi</option>
                    <?php foreach ($daftar_materi as $m): ?>
                        <option value="<?= htmlspecialchars($m) ?>" <?= $materi_filter === $m ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="lulus" <?= $status_filter === 'lulus' ? 'selected' : '' ?>>Lulus</option>
                    <option value="belum" <?= $status_filter === 'belum' ? 'selected' : '' ?>>Belum Lulus</option>
                </select>
            </div>
        </form>

        <div class="table-responsive bg-white p-4 rounded-3 shadow-sm border">
            <table class="table table-sm table-hover align-middle m-0">
                <thead class="table-light">
                    <tr>
                        <th>Kelas</th> <!-- 🔥 Kolom Kelas Baru -->
                        <th>Siswa</th>
                        <th>Kategori</th>
                        <th>Level</th>
                        <th>Materi</th>
                        <th>Sesi</th>
                        <th>Skor</th>
                        <th>Attempt ke-</th>
                        <th>Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($hasil_list)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada data untuk filter ini.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($hasil_list as $h): 
                        $lvl = $level_badge[$h['level']] ?? ['-', 'secondary'];
                    ?>
                    <tr>
                        <!-- 🔥 Tampilkan Kelas -->
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($h['kelas'] ?? '-') ?></span></td>
                        <td><?= htmlspecialchars($h['nama_asli'] ?: $h['username']) ?></td>
                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($h['kategori']) ?></span></td>
                        <td><span class="badge bg-<?= $lvl[1] ?>"><?= $lvl[0] ?></span></td>
                        <td><?= htmlspecialchars($h['materi'] ?? '-') ?></td>
                        <td class="small text-muted"><?= $h['sesi_dibuka'] ? date('d M Y', strtotime($h['sesi_dibuka'])) : 'Riwayat lama' ?></td>
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

    <!-- 🔥 Perbaikan versi Bootstrap JS biar sinkron (dari 5.2.3 ke 5.3.0) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>