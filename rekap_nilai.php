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
$kelas_filter    = $_GET['kelas'] ?? '';
$materi_filter   = $_GET['materi'] ?? '';

// Query 1: Riwayat Attempt
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
if ($kelas_filter) {
    $sql .= " AND users.kelas = ?";
    $params[] = $kelas_filter;
}
if ($materi_filter) {
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

// Query 2: Ringkasan per siswa PER SESI
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

// Ambil daftar unik kelas
$list_kelas_sql = "SELECT DISTINCT kelas FROM users WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC";
$daftar_kelas = $pdo->query($list_kelas_sql)->fetchAll(PDO::FETCH_COLUMN);

// Ambil daftar unik materi
$list_materi_sql = "SELECT DISTINCT materi FROM kuis_sesi WHERE materi IS NOT NULL AND materi != '' ORDER BY materi ASC";
$daftar_materi = $pdo->query($list_materi_sql)->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Nilai Kuis - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables Bootstrap 5 CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include 'includes/admin_header.php'; ?>

    <!-- KONTEN -->
    <div class="container mt-5 mb-5">
        
        <!-- HEADER DENGAN TOMBOL EXPORT + BACK -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold m-0 text-dark">📊 Rekap Nilai Kuis</h4>
            <div class="d-flex align-items-center gap-2">
                <a href="export_nilai.php?<?= http_build_query($_GET) ?>" 
                   class="btn btn-success rounded-circle d-flex align-items-center justify-content-center shadow-sm" 
                   style="width: 40px; height: 40px;" 
                   title="Export Ringkasan Excel">
                    <i class="bi bi-file-earmark-excel"></i>
                </a>
                
                <a href="pintu-rahasia-sija" 
                   class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center shadow-sm" 
                   style="width: 40px; height: 40px;" 
                   title="Kembali ke Manage Kuis">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </div>

        <!-- REKAP PER KELAS -->
        <h5 class="fw-bold mb-3">📁 Pilih Rekap Per Kelas</h5>
        <div class="mb-4">
            <a href="rekap_nilai.php" class="btn btn-sm <?= empty($kelas_filter) ? 'btn-dark' : 'btn-outline-dark' ?> me-2">
                🌍 Semua Kelas
            </a>
            <?php foreach ($daftar_kelas as $k): ?>
                <a href="rekap_nilai.php?kelas=<?= urlencode($k) ?>" class="btn btn-sm <?= $kelas_filter === $k ? 'btn-dark' : 'btn-outline-dark' ?> me-2 mb-1">
                    📦 Kelas <?= htmlspecialchars($k) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- TABS NAVIGASI TABEL -->
        <ul class="nav nav-tabs nav-fill mb-4 shadow-sm rounded bg-white p-1" id="rekapTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold py-2" id="ringkasan-tab" data-bs-toggle="tab" data-bs-target="#tab-ringkasan" type="button" role="tab">
                    🏆 Ringkasan Nilai Terbaik <?= $kelas_filter ? 'Kelas ' . htmlspecialchars($kelas_filter) : 'Semua Siswa' ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold py-2" id="attempt-tab" data-bs-toggle="tab" data-bs-target="#tab-attempt" type="button" role="tab">
                    📜 Log Riwayat Semua Attempt
                </button>
            </li>
        </ul>

        <!-- TAB CONTENT -->
        <div class="tab-content" id="rekapTabContent">
            
            <!-- TAB 1: RINGKASAN PER SISWA -->
            <div class="tab-pane fade show active" id="tab-ringkasan" role="tabpanel">
                <div class="table-responsive bg-white p-4 rounded-3 shadow-sm border">
                    <table id="tableRingkasan" class="table table-hover align-middle m-0 w-100">
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
                            foreach ($ringkasan as $r): 
                                if ($kelas_filter && $r['kelas'] !== $kelas_filter) continue;
                                
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
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: DETAIL RIWAYAT ATTEMPT -->
            <div class="tab-pane fade" id="tab-attempt" role="tabpanel">
                
                <!-- Filter Server Side PHP -->
                <form method="GET" class="row g-2 mb-3 bg-white p-3 rounded-3 shadow-sm border align-items-center">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted m-0">Kelas</label>
                        <select name="kelas" class="form-select form-select-sm mt-1" onchange="this.form.submit()">
                            <option value="">Semua Kelas</option>
                            <?php foreach ($daftar_kelas as $k): ?>
                                <option value="<?= htmlspecialchars($k) ?>" <?= $kelas_filter === $k ? 'selected' : '' ?>><?= htmlspecialchars($k) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted m-0">Kategori</label>
                        <select name="kategori" class="form-select form-select-sm mt-1" onchange="this.form.submit()">
                            <option value="">Semua Kategori</option>
                            <option value="Network" <?= $kategori_filter === 'Network' ? 'selected' : '' ?>>Network</option>
                            <option value="IoT" <?= $kategori_filter === 'IoT' ? 'selected' : '' ?>>IoT</option>
                            <option value="Cloud Computing" <?= $kategori_filter === 'Cloud Computing' ? 'selected' : '' ?>>Cloud Computing</option>
                            <option value="DevOps" <?= $kategori_filter === 'DevOps' ? 'selected' : '' ?>>DevOps</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted m-0">Level</label>
                        <select name="level" class="form-select form-select-sm mt-1" onchange="this.form.submit()">
                            <option value="">Semua Level</option>
                            <option value="pemula" <?= $level_filter === 'pemula' ? 'selected' : '' ?>>Pemula</option>
                            <option value="menengah" <?= $level_filter === 'menengah' ? 'selected' : '' ?>>Menengah</option>
                            <option value="mahir" <?= $level_filter === 'mahir' ? 'selected' : '' ?>>Mahir</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted m-0">Materi</label>
                        <select name="materi" class="form-select form-select-sm mt-1" onchange="this.form.submit()">
                            <option value="">Semua Materi</option>
                            <?php foreach ($daftar_materi as $m): ?>
                                <option value="<?= htmlspecialchars($m) ?>" <?= $materi_filter === $m ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted m-0">Status</label>
                        <select name="status" class="form-select form-select-sm mt-1" onchange="this.form.submit()">
                            <option value="">Status</option>
                            <option value="lulus" <?= $status_filter === 'lulus' ? 'selected' : '' ?>>Lulus</option>
                            <option value="belum" <?= $status_filter === 'belum' ? 'selected' : '' ?>>Belum Lulus</option>
                        </select>
                    </div>
                </form>

                <div class="table-responsive bg-white p-4 rounded-3 shadow-sm border">
                    <table id="tableAttempt" class="table table-sm table-hover align-middle m-0 w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Kelas</th>
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
                            <?php foreach ($hasil_list as $h): 
                                $lvl = $level_badge[$h['level']] ?? ['-', 'secondary'];
                            ?>
                            <tr>
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
            
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            // Inisialisasi DataTables untuk Ringkasan
            $('#tableRingkasan').DataTable({
                "pageLength": 25,
                "language": {
                    "search": "🔍 Cari Siswa/Materi:",
                    "lengthMenu": "Tampilkan _MENU_ data",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ siswa",
                    "paginate": {
                        "first": "Awal",
                        "last": "Akhir",
                        "next": "›",
                        "previous": "‹"
                    },
                    "emptyTable": "Tidak ada data untuk kelas ini"
                }
            });

            // Inisialisasi DataTables untuk Attempt Log
            $('#tableAttempt').DataTable({
                "pageLength": 25,
                "order": [[ 8, "desc" ]], // Default urutkan dari waktu pengerjaan terbaru
                "language": {
                    "search": "🔍 Cari di Log Attempt:",
                    "lengthMenu": "Tampilkan _MENU_ data",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ log attempt",
                    "paginate": {
                        "first": "Awal",
                        "last": "Akhir",
                        "next": "›",
                        "previous": "‹"
                    },
                    "emptyTable": "Tidak ada data attempt"
                }
            });
        });
    </script>
</body>
</html>