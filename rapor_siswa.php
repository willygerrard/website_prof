<?php
require_once 'session.php';
require 'koneksi.php';
checkLogin();
checkRole(['admin', 'siswa']);

const KKM = 75;
const MAX_ATTEMPT = 4;

$isAdmin = ($_SESSION['role'] === 'admin');
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// Tentukan siswa_id yang mau ditampilkan
if ($isAdmin && isset($_GET['id'])) {
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
$stmt = $pdo->prepare("SELECT username, kelas FROM users WHERE id = ?");
$stmt->execute([$siswa_id]);
$siswa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$siswa) {
    die("Siswa tidak ditemukan.");
}

// ===== Export ke CSV (nilai kuis) =====
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $sql = "SELECT kuis_hasil.kategori, kuis_hasil.level, ks.dibuka_at AS sesi_dibuka,
               MAX(kuis_hasil.skor) as nilai_terbaik, COUNT(*) as total_attempt,
               (SELECT GROUP_CONCAT(materi SEPARATOR ', ') FROM kuis_sesi_materi WHERE sesi_id = ks.id) AS materi
        FROM kuis_hasil
        LEFT JOIN kuis_sesi ks ON kuis_hasil.sesi_id = ks.id
        WHERE kuis_hasil.user_id = ?
        GROUP BY kuis_hasil.kategori, kuis_hasil.level, kuis_hasil.sesi_id
        ORDER BY kuis_hasil.kategori, FIELD(kuis_hasil.level, 'pemula', 'menengah', 'mahir'), ks.dibuka_at";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$siswa_id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $namaFile = preg_replace('/[^A-Za-z0-9_-]/', '_', $siswa['username']);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rapor_' . $namaFile . '.csv"');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // BOM biar Excel baca UTF-8 dengan benar
    fputcsv($output, ['Nama Siswa', 'Kategori', 'Level', 'Materi', 'Sesi (Tanggal Deploy)', 'Nilai Terbaik', 'Jumlah Percobaan', 'Status']);

    foreach ($data as $row) {
        $status = $row['nilai_terbaik'] >= KKM ? 'Lulus' : ($row['total_attempt'] >= MAX_ATTEMPT ? 'Tidak Lulus' : 'Belum Tuntas');
        $sesi_label = $row['sesi_dibuka'] ? date('d M Y', strtotime($row['sesi_dibuka'])) : 'Riwayat lama';
        fputcsv($output, [$siswa['username'], $row['kategori'], ucfirst($row['level']), $row['materi'] ?? '-', $sesi_label, $row['nilai_terbaik'], $row['total_attempt'], $status]);
    }
    fclose($output);
    exit();
}

// ===== NILAI KUIS: ringkasan PER SESI =====
$sql = "SELECT kuis_hasil.kategori, kuis_hasil.level, kuis_hasil.sesi_id, ks.dibuka_at AS sesi_dibuka,
               MAX(kuis_hasil.skor) as nilai_terbaik, COUNT(*) as total_attempt,
               MAX(kuis_hasil.dikerjakan_at) as terakhir_dikerjakan,
               (SELECT GROUP_CONCAT(materi SEPARATOR ', ') FROM kuis_sesi_materi WHERE sesi_id = ks.id) AS materi
        FROM kuis_hasil
        LEFT JOIN kuis_sesi ks ON kuis_hasil.sesi_id = ks.id
        WHERE kuis_hasil.user_id = ?
        GROUP BY kuis_hasil.kategori, kuis_hasil.level, kuis_hasil.sesi_id
        ORDER BY kuis_hasil.kategori, FIELD(kuis_hasil.level, 'pemula', 'menengah', 'mahir'), ks.dibuka_at";
$stmt = $pdo->prepare($sql);
$stmt->execute([$siswa_id]);
$ringkasan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Riwayat semua attempt
$stmt2 = $pdo->prepare("
    SELECT kuis_hasil.*,
           (SELECT GROUP_CONCAT(materi SEPARATOR ', ') FROM kuis_sesi_materi WHERE sesi_id = kuis_hasil.sesi_id) AS materi
    FROM kuis_hasil
    WHERE user_id = ?
    ORDER BY dikerjakan_at DESC
");
$stmt2->execute([$siswa_id]);
$riwayat = $stmt2->fetchAll(PDO::FETCH_ASSOC);

$level_badge = [
    'pemula'   => ['🟢 Pemula', 'success'],
    'menengah' => ['🟡 Menengah', 'warning'],
    'mahir'    => ['🔴 Mahir', 'danger'],
];

$total_sesi  = count($ringkasan);
$kuis_lulus  = 0;
foreach ($ringkasan as $r) {
    if ($r['nilai_terbaik'] >= KKM) $kuis_lulus++;
}

// ===== NILAI TUGAS PRAKTIK =====
// Logika tampil nilai sama dengan daftar_tugas_siswa.php:
// nilai muncul hanya jika sudah upload + deadline lewat (atau tanpa deadline) + nilai tidak null.
// Admin selalu melihat nilai.
$stmtT = $pdo->prepare("
    SELECT t.id, t.judul, t.deadline,
           pt.id AS pengumpulan_id, pt.uploaded_at, pt.nilai, pt.catatan_guru
    FROM tugas t
    JOIN tugas_kelas tk ON t.id = tk.tugas_id
    LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id = t.id AND pt.user_id = ?
    WHERE tk.kelas = ?
    ORDER BY (t.deadline IS NULL), t.deadline ASC, t.id DESC
");
$stmtT->execute([$siswa_id, $siswa['kelas']]);
$daftar_tugas = $stmtT->fetchAll(PDO::FETCH_ASSOC);

$tugas_total = count($daftar_tugas);
$tugas_dinilai = 0;
$tugas_belum = 0;
foreach ($daftar_tugas as &$t) {
    $deadlineTs = $t['deadline'] ? strtotime($t['deadline']) : null;
    $lewat = $deadlineTs && time() > $deadlineTs;
    $sudahKumpul = !empty($t['pengumpulan_id']);

    $t['deadlineTs']    = $deadlineTs;
    $t['nilaiTersedia'] = $sudahKumpul && $t['nilai'] !== null && ($isAdmin || $deadlineTs === null || $lewat);
    $t['menungguDeadline'] = $sudahKumpul && $t['nilai'] === null && $deadlineTs && !$lewat;

    if ($sudahKumpul) {
        $terlambat = $deadlineTs && strtotime($t['uploaded_at']) > $deadlineTs;
        $t['badge'] = $terlambat ? ['Terlambat', 'danger'] : ['Sudah dikerjakan', 'success'];
    } else {
        $tugas_belum++;
        $t['badge'] = $lewat ? ['Belum dikerjakan (lewat deadline)', 'danger'] : ['Belum dikerjakan', 'secondary'];
    }
    if ($t['nilaiTersedia']) $tugas_dinilai++;
}
unset($t);

// ===== Variabel untuk includes/head.php (pola sama seperti daftar_tugas_siswa.php) =====
$html_lang  = 'id';
$page_title = 'Rapor Nilai - Pusat Pembelajaran SIJA';
$body_class = 'bg-light';
$extra_head = '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />';

include __DIR__ . '/includes/head.php';
?>

<?php include __DIR__ . '/includes/navbar.php'; ?>

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
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <h4 class="fw-bold m-0 text-dark">🎓 Rapor Saya — <?= $e($siswa['username']) ?></h4>
        <div class="d-flex align-items-center gap-2">
            <div class="btn-group" role="group" aria-label="Filter rapor" id="filterRapor">
                <button type="button" class="btn btn-sm btn-outline-primary active" data-filter="all">Semua</button>
                <button type="button" class="btn btn-sm btn-outline-primary" data-filter="kuis">Kuis</button>
                <button type="button" class="btn btn-sm btn-outline-primary" data-filter="tugas">Tugas</button>
            </div>
            <a href="<?= $isAdmin ? 'rekap_nilai.php' : 'index.php' ?>"
               class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center"
               style="width: 40px; height: 40px;" title="<?= $isAdmin ? 'Kembali ke Rekap' : 'Kembali ke Beranda' ?>">
                <i class="bi bi-arrow-counterclockwise"></i>
            </a>
            <a href="?id=<?= (int)$siswa_id ?>&export=csv"
               class="btn btn-success rounded-circle d-flex align-items-center justify-content-center"
               style="width: 40px; height: 40px;" title="Export nilai kuis ke Excel/CSV">
                <i class="bi bi-file-earmark-excel"></i>
            </a>
        </div>
    </div>

    <!-- STATISTIK RINGKAS -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 text-center p-3">
                <div class="fs-2 fw-bold text-success"><?= $kuis_lulus ?> <span class="fs-6 text-muted fw-normal">dari <?= $total_sesi ?> sesi</span></div>
                <div class="text-muted small">Kuis Lulus</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 text-center p-3">
                <div class="fs-2 fw-bold text-primary"><?= $tugas_dinilai ?> <span class="fs-6 text-muted fw-normal">dari <?= $tugas_total ?> tugas</span></div>
                <div class="text-muted small">Tugas Sudah Dinilai</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 text-center p-3">
                <div class="fs-2 fw-bold <?= $tugas_belum > 0 ? 'text-danger' : 'text-secondary' ?>"><?= $tugas_belum ?></div>
                <div class="text-muted small">Tugas Belum Dikumpulkan</div>
            </div>
        </div>
    </div>

    <!-- ===================== BAGIAN KUIS ===================== -->
    <section id="sec-kuis">
        <h5 class="fw-bold mb-3">📊 Nilai Kuis</h5>
        <div class="table-responsive bg-white p-4 rounded-3 shadow-sm border mb-5">
            <table class="table table-hover align-middle m-0">
                <thead class="table-light">
                    <tr>
                        <th>Kategori</th>
                        <th>Level</th>
                        <th>Materi</th>
                        <th>Sesi</th>
                        <th>Nilai Terbaik</th>
                        <th>Percobaan</th>
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
                        $sisa = max(0, MAX_ATTEMPT - (int)$r['total_attempt']);
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= $e($r['kategori']) ?></td>
                        <td><span class="badge bg-<?= $lvl[1] ?>"><?= $lvl[0] ?></span></td>
                        <td class="small" style="min-width: 160px; max-width: 260px;"><?= $e($r['materi'] ?? '-') ?></td>
                        <td class="small text-muted"><?= $r['sesi_dibuka'] ? date('d M Y', strtotime($r['sesi_dibuka'])) : 'Riwayat lama' ?></td>
                        <td class="fw-bold fs-5 <?= $lulus ? 'text-success' : 'text-danger' ?>"><?= $e($r['nilai_terbaik']) ?></td>
                        <td><?= (int)$r['total_attempt'] ?> / <?= MAX_ATTEMPT ?></td>
                        <td>
                            <?php if ($lulus): ?>
                                <span class="badge bg-success">✅ Lulus</span>
                            <?php elseif ($r['total_attempt'] >= MAX_ATTEMPT): ?>
                                <span class="badge bg-danger">❌ Tidak Lulus</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">⏳ Belum Tuntas</span>
                                <div class="small text-muted mt-1">Sisa <?= $sisa ?> percobaan</div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- RIWAYAT LENGKAP -->
        <h5 class="fw-bold mb-3">📜 Riwayat Semua Percobaan</h5>
        <div class="table-responsive bg-white p-4 rounded-3 shadow-sm border mb-5">
            <table class="table table-sm align-middle m-0">
                <thead class="table-light">
                    <tr>
                        <th>Kategori</th>
                        <th>Level</th>
                        <th>Materi</th>
                        <th>Skor</th>
                        <th>Attempt ke-</th>
                        <th>Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($riwayat)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada riwayat.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($riwayat as $h):
                        $lvl = $level_badge[$h['level']] ?? ['-', 'secondary'];
                    ?>
                    <tr>
                        <td><?= $e($h['kategori']) ?></td>
                        <td><span class="badge bg-<?= $lvl[1] ?>"><?= $lvl[0] ?></span></td>
                        <td class="small"><?= $e($h['materi'] ?? '-') ?></td>
                        <td class="fw-bold <?= $h['skor'] >= KKM ? 'text-success' : 'text-danger' ?>"><?= $e($h['skor']) ?></td>
                        <td><?= $e($h['attempt']) ?></td>
                        <td class="text-muted small"><?= date('d M Y, H:i', strtotime($h['dikerjakan_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ===================== BAGIAN TUGAS ===================== -->
    <section id="sec-tugas">
        <h5 class="fw-bold mb-3">📋 Nilai Tugas Praktik</h5>
        <?php if (empty($daftar_tugas)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    Belum ada tugas yang di-deploy untuk kelas <?= $e($siswa['kelas']) ?>.
                </div>
            </div>
        <?php else: ?>
            <div class="list-group shadow-sm">
                <?php foreach ($daftar_tugas as $t): ?>
                <div class="list-group-item py-3">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="fw-bold"><?= $e($t['judul']) ?></div>
                            <?php if ($t['deadlineTs']): ?>
                                <div class="small text-muted">Batas waktu: <?= date('d M Y, H:i', $t['deadlineTs']) ?></div>
                            <?php endif; ?>
                            <?php if ($t['nilaiTersedia'] && !empty($t['catatan_guru'])): ?>
                                <div class="mt-2 small text-dark">
                                    <i class="bi bi-chat-left-quote"></i>
                                    <em><?= nl2br($e($t['catatan_guru'])) ?></em>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <?php if ($t['nilaiTersedia']): ?>
                                <div>
                                    <span class="fw-bold fs-5 text-dark"><?= $e(number_format((float)$t['nilai'], 2, ',', '.')) ?></span>
                                    <span class="text-muted small">/ 100</span>
                                </div>
                            <?php elseif ($t['menungguDeadline']): ?>
                                <div class="small text-muted"><i class="bi bi-hourglass-split"></i> Nilai muncul setelah deadline</div>
                            <?php elseif (!empty($t['pengumpulan_id'])): ?>
                                <div class="small text-muted">Belum dinilai guru</div>
                            <?php else: ?>
                                <div class="small text-muted">Nilai —</div>
                            <?php endif; ?>
                            <span class="badge bg-<?= $t['badge'][1] ?> mt-1"><?= $e($t['badge'][0]) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- FOOTER -->
<footer class="py-5 bg-dark">
    <div class="container"><p class="m-0 text-center text-white">Copyright &copy; SIJA Website 2026</p></div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Filter tampilan: Semua / Kuis / Tugas
document.querySelectorAll('#filterRapor [data-filter]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var f = btn.getAttribute('data-filter');
        document.querySelectorAll('#filterRapor .btn').forEach(function (b) { b.classList.toggle('active', b === btn); });
        document.getElementById('sec-kuis').style.display  = (f === 'all' || f === 'kuis')  ? '' : 'none';
        document.getElementById('sec-tugas').style.display = (f === 'all' || f === 'tugas') ? '' : 'none';
    });
});
</script>
</body>
</html>