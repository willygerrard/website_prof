<?php
/**
 * laporan_silang.php
 * Laporan silang: absensi kertas vs absensi LMS (QR) vs aktivitas belajar.
 *
 * Akses: admin (guard existing checkRole(['admin'])); hanya sesi milik guru
 * yang login (guru_id = session user_id).
 *
 * Per baris (sesi × siswa kelas itu):
 *  - nama, kelas, mapel, tanggal
 *  - status kertas (absensi.status; "Belum ada data" bila tidak ada baris)
 *  - status LMS (Hadir bila waktu_scan tidak NULL)
 *  - jumlah aktivitas di tanggal sesi dari 3 sumber:
 *      1) log_aktivitas (created_at = tanggal sesi; modul milik mapel sesi
 *         via modules.category — Opsi A, tidak ada tabel mapel)
 *      2) pengumpulan_tugas.uploaded_at
 *      3) kuis_hasil.dikerjakan_at
 *  - Kategori (dievaluasi berurutan):
 *      1) "Hadir tapi tidak belajar": (status kertas hadir/terlambat ATAU
 *         waktu_scan tidak NULL) DAN total aktivitas = 0
 *      2) "Konflik absensi": status kertas hadir tapi tidak scan, ATAU scan
 *         tapi status kertas bukan hadir/terlambat
 *      3) "Sesuai"; "Belum ada data" bila tidak ada baris absensi
 *
 * Satu query agregat (tanpa N+1). Export CSV dengan filter sama, BOM UTF-8,
 * fputcsv, dan netralisasi formula injection (prefix ' untuk sel =+-@).
 * Waktu dihitung di PHP; semua query pakai prepared statement.
 */

date_default_timezone_set('Asia/Jakarta');

require_once 'session.php';
require_once 'csrf_helper.php';
require 'koneksi.php';

checkLogin();
checkRole(['admin']);

$user_id = (int)($_SESSION['user_id'] ?? 0);

/**
 * Daftar kelas yang muncul di sesi milik guru ini (untuk dropdown filter).
 */
function laporan_daftar_kelas(PDO $pdo, int $user_id): array
{
    $st = $pdo->prepare("SELECT DISTINCT kelas FROM absensi_sesi WHERE guru_id = ? ORDER BY kelas");
    $st->execute([$user_id]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Daftar mapel (kategori modules) yang terpakai di sesi milik guru ini.
 * Kembalikan [mapel_id => label]; mapel_id = urutan kategori (Opsi A).
 */
function laporan_daftar_mapel(PDO $pdo, int $user_id): array
{
    $all = $pdo->query("SELECT DISTINCT category FROM modules ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
    $st = $pdo->prepare("SELECT DISTINCT mapel_id FROM absensi_sesi WHERE guru_id = ? ORDER BY mapel_id");
    $st->execute([$user_id]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $mid) {
        $i = (int)$mid - 1;
        $out[(int)$mid] = isset($all[$i]) ? $all[$i] : ('Mapel #' . (int)$mid);
    }
    return $out;
}

// =====================
// FILTER (GET)
// =====================
$tanggal_default = date('Y-m-d');
$tgl_awal  = trim($_GET['tgl_awal'] ?? $tanggal_default);
$tgl_akhir = trim($_GET['tgl_akhir'] ?? $tanggal_default);
$f_kelas   = trim($_GET['kelas'] ?? '');
$f_mapel   = (isset($_GET['mapel_id']) && $_GET['mapel_id'] !== '') ? (int)$_GET['mapel_id'] : 0;

$tgl_ok = fn ($t) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $t);
if (!$tgl_ok($tgl_awal))  $tgl_awal  = $tanggal_default;
if (!$tgl_ok($tgl_akhir)) $tgl_akhir = $tgl_awal;
if ($tgl_akhir < $tgl_awal) { [$tgl_awal, $tgl_akhir] = [$tgl_akhir, $tgl_awal]; }

$kelas_boleh = laporan_daftar_kelas($pdo, $user_id);
$mapel_boleh = laporan_daftar_mapel($pdo, $user_id);
if ($f_kelas !== '' && !in_array($f_kelas, $kelas_boleh, true)) $f_kelas = '';
if ($f_mapel !== 0 && !isset($mapel_boleh[$f_mapel])) $f_mapel = 0;

$export_csv = isset($_GET['export']) && $_GET['export'] === 'csv';

// =====================
// QUERY AGREGAT UTAMA
// =====================
// Satu query untuk semua baris (sesi × siswa kelas itu): LEFT JOIN absensi,
// plus 3 subquery skalar berkorelasi per sumber aktivitas (log/tugas/quiz)
// di tanggal sesi — tanpa query per baris di PHP.
//
// Soal jml_log: aktivitas modul difilter ke modul milik mapel sesi.
// Tidak ada tabel mapel (keputusan user: Opsi A) — mapel_id disimpan sebagai
// nomor urut DISTINCT category modules (dihitung PHP saat buat sesi).
// Mapping (mapel_id → category) dibangun di PHP sebagai CASE SQL statis
// (nilai dari DB sendiri, bukan input user), sehingga subquery log hanya
// menghitung aktivitas pada modul dengan kategori = mapel sesi.
$all_categories = $pdo->query("SELECT DISTINCT category FROM modules ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$case_parts = [];
foreach ($all_categories as $idx => $cat) {
    $case_parts[] = "WHEN " . ((int)$idx + 1) . " THEN " . $pdo->quote((string)$cat);
}
$mapel_case = $case_parts ? implode(' ', $case_parts) : "WHEN 0 THEN '__none__'";

$sql = "SELECT s.id AS sesi_id, s.tanggal, s.kelas, s.mapel_id,
               u.id AS user_id, COALESCE(u.nama_asli, u.username) AS nama,
               a.status AS status_kertas, a.waktu_scan,
               (SELECT COUNT(*)
                  FROM log_aktivitas la
                 WHERE la.user_id = u.id
                   AND DATE(la.created_at) = s.tanggal
                   AND la.modul_id IN (
                        SELECT m3.id FROM modules m3
                         WHERE m3.category COLLATE utf8mb4_unicode_ci = CASE s.mapel_id $mapel_case END
                   )
               ) AS jml_log,
               (SELECT COUNT(*)
                  FROM pengumpulan_tugas pt
                 WHERE pt.user_id = u.id AND DATE(pt.uploaded_at) = s.tanggal) AS jml_tugas,
               (SELECT COUNT(*)
                  FROM kuis_hasil kh
                 WHERE kh.user_id = u.id AND DATE(kh.dikerjakan_at) = s.tanggal) AS jml_quiz
        FROM absensi_sesi s
        JOIN users u
          ON u.role = 'siswa' AND u.kelas COLLATE utf8mb4_unicode_ci = s.kelas AND u.status = 'aktif'
        LEFT JOIN absensi a
          ON a.sesi_id = s.id AND a.user_id = u.id
        WHERE s.guru_id = ? AND s.tanggal BETWEEN ? AND ?";

// Filter opsional kelas/mapel
$params = [$user_id, $tgl_awal, $tgl_akhir];
if ($f_kelas !== '') { $sql .= " AND s.kelas = ?"; $params[] = $f_kelas; }
if ($f_mapel !== 0)  { $sql .= " AND s.mapel_id = ?"; $params[] = $f_mapel; }
$sql .= " ORDER BY s.tanggal DESC, s.id DESC, nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================
// EVALUASI KATEGORI
// =====================
$laporan = [];
$ringkas = ['hadir_tidak_belajar' => 0, 'konflik' => 0, 'sesuai' => 0, 'kosong' => 0];

foreach ($rows as $r) {
    $total_aktivitas = (int)$r['jml_log'] + (int)$r['jml_tugas'] + (int)$r['jml_quiz'];
    $ada_baris = ($r['status_kertas'] !== null || $r['waktu_scan'] !== null);
    $scan = ($r['waktu_scan'] !== null);
    $status_kertas = $r['status_kertas'];

    if (!$ada_baris) {
        $kategori = 'Belum ada data';
        $badge = 'secondary';
    } else {
        $kategori = '';
        $badge = '';
        // 1) Hadir tapi tidak belajar
        if ((in_array($status_kertas, ['hadir', 'terlambat'], true) || $scan) && $total_aktivitas === 0) {
            $kategori = 'Hadir tapi tidak belajar';
            $badge = 'danger';
        } elseif (($status_kertas === 'hadir' && !$scan) || ($scan && !in_array($status_kertas, ['hadir', 'terlambat'], true))) {
            // 2) Konflik absensi
            $kategori = 'Konflik absensi';
            $badge = 'warning text-dark';
        } else {
            // 3) Sesuai
            $kategori = 'Sesuai';
            $badge = 'success';
        }
    }

    switch ($kategori) {
        case 'Hadir tapi tidak belajar': $ringkas['hadir_tidak_belajar']++; break;
        case 'Konflik absensi':          $ringkas['konflik']++; break;
        case 'Sesuai':                   $ringkas['sesuai']++; break;
        default:                         $ringkas['kosong']++;
    }

    $laporan[] = [
        'sesi_id'    => (int)$r['sesi_id'],
        'tanggal'    => $r['tanggal'],
        'kelas'      => $r['kelas'],
        'mapel'      => $mapel_boleh[(int)$r['mapel_id']] ?? ('Mapel #' . (int)$r['mapel_id']),
        'nama'       => $r['nama'],
        'status_kertas' => $status_kertas,
        'scan'       => $scan,
        'waktu_scan' => $r['waktu_scan'],
        'jml_log'    => (int)$r['jml_log'],
        'jml_tugas'  => (int)$r['jml_tugas'],
        'jml_quiz'   => (int)$r['jml_quiz'],
        'total'      => $total_aktivitas,
        'kategori'   => $kategori,
        'badge'      => $badge,
    ];
}

// =====================
// EXPORT CSV
// =====================
if ($export_csv) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan_silang_' . $tgl_awal . '_' . $tgl_akhir . '.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8
    fputcsv($out, ['Tanggal', 'Sesi', 'Kelas', 'Mapel', 'Nama', 'Status Kertas', 'Status LMS', 'Log Modul', 'Tugas', 'Quiz', 'Total Aktivitas', 'Kategori']);

    foreach ($laporan as $r) {
        $line = [
            $r['tanggal'],
            $r['sesi_id'],
            $r['kelas'],
            $r['mapel'],
            $r['nama'],
            $r['status_kertas'] ?? 'Belum ada data',
            $r['scan'] ? ('Hadir (' . $r['waktu_scan'] . ')') : 'Tidak scan',
            $r['jml_log'],
            $r['jml_tugas'],
            $r['jml_quiz'],
            $r['total'],
            $r['kategori'],
        ];
        // Netralkan formula injection: sel yang diawali = + - @ diberi prefix '
        foreach ($line as $i => $cell) {
            $s = (string)$cell;
            if ($s !== '' && in_array($s[0], ['=', '+', '-', '@'], true)) {
                $line[$i] = "'" . $s;
            }
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Silang Absensi - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/datatables/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include 'includes/admin_header.php'; ?>

    <div class="container mt-4 mb-5">

        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="card-title mb-0 fw-bold">📊 Laporan Silang — Absensi vs Aktivitas Belajar</h5>
            </div>
            <div class="card-body p-4">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Dari Tanggal</label>
                        <input type="date" class="form-control" name="tgl_awal" value="<?= htmlspecialchars($tgl_awal, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Sampai</label>
                        <input type="date" class="form-control" name="tgl_akhir" value="<?= htmlspecialchars($tgl_akhir, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Kelas</label>
                        <select class="form-select" name="kelas">
                            <option value="">Semua kelas</option>
                            <?php foreach ($kelas_boleh as $k): ?>
                                <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= $f_kelas === $k ? 'selected' : '' ?>><?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Mapel</label>
                        <select class="form-select" name="mapel_id">
                            <option value="">Semua mapel</option>
                            <?php foreach ($mapel_boleh as $mid => $label): ?>
                                <option value="<?= (int)$mid ?>" <?= $f_mapel === (int)$mid ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid gap-2">
                        <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-funnel"></i> Terapkan</button>
                        <a class="btn btn-outline-success btn-sm" href="?tgl_awal=<?= urlencode($tgl_awal) ?>&tgl_akhir=<?= urlencode($tgl_akhir) ?>&kelas=<?= urlencode($f_kelas) ?>&mapel_id=<?= $f_mapel ?>&export=csv">
                            <i class="bi bi-download"></i> Export CSV
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Ringkasan per kategori -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-3 text-center">
                    <div class="card-body py-3">
                        <div class="fs-3 fw-bold text-danger"><?= (int)$ringkas['hadir_tidak_belajar'] ?></div>
                        <div class="small text-muted">Hadir tapi tidak belajar</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-3 text-center">
                    <div class="card-body py-3">
                        <div class="fs-3 fw-bold text-warning"><?= (int)$ringkas['konflik'] ?></div>
                        <div class="small text-muted">Konflik absensi</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-3 text-center">
                    <div class="card-body py-3">
                        <div class="fs-3 fw-bold text-success"><?= (int)$ringkas['sesuai'] ?></div>
                        <div class="small text-muted">Sesuai</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-3 text-center">
                    <div class="card-body py-3">
                        <div class="fs-3 fw-bold text-secondary"><?= (int)$ringkas['kosong'] ?></div>
                        <div class="small text-muted">Belum ada data</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel laporan -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle" id="tabelLaporan">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Mapel</th>
                                <th>Kelas</th>
                                <th>Nama</th>
                                <th>Kertas</th>
                                <th>LMS (QR)</th>
                                <th>Aktivitas (log/tugas/quiz)</th>
                                <th>Kategori</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($laporan as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['tanggal'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($r['mapel'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($r['kelas'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($r['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if ($r['status_kertas'] === null): ?>
                                        <span class="badge bg-secondary">Belum ada data</span>
                                    <?php else: ?>
                                        <span class="badge bg-dark"><?= htmlspecialchars($r['status_kertas'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($r['scan']): ?>
                                        <span class="badge bg-success">Hadir (scan)</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">Tidak scan</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$r['jml_log'] ?> / <?= (int)$r['jml_tugas'] ?> / <?= (int)$r['jml_quiz'] ?> <span class="text-muted small">(total <?= (int)$r['total'] ?>)</span></td>
                                <td><span class="badge bg-<?= htmlspecialchars($r['badge'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($r['kategori'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($laporan)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada sesi pada rentang filter ini.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/vendor/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
    <script src="assets/vendor/datatables/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(function () { $('#tabelLaporan').DataTable({ pageLength: 25 }); });
    </script>
</body>
</html>
