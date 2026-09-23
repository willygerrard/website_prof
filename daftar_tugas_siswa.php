<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

include 'koneksi.php';

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'siswa') {
    die("Halaman ini khusus untuk siswa.");
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

$stmtUser = $pdo->prepare("SELECT kelas FROM users WHERE id = ?");
$stmtUser->execute([$user_id]);
$kelas_siswa = $stmtUser->fetchColumn();

// Daftar tugas yang ditujukan ke kelas siswa ini, beserta status pengumpulan miliknya sendiri.
$stmt = $pdo->prepare("
    SELECT t.id, t.judul, t.deskripsi, t.deadline, t.link_lkpd,
           pt.id AS pengumpulan_id, pt.uploaded_at,
           pt.nilai, pt.catatan_guru
    FROM tugas t
    JOIN tugas_kelas tk ON t.id = tk.tugas_id
    LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id = t.id AND pt.user_id = ?
    WHERE tk.kelas = ?
    ORDER BY (t.deadline IS NULL), t.deadline ASC, t.id DESC
");
$stmt->execute([$user_id, $kelas_siswa]);
$daftar_tugas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== Variabel untuk includes/head.php (pola sama seperti index.php) =====
$html_lang  = 'en';
$page_title = 'Tugas Aktif - Pusat Pembelajaran SIJA';
$body_class = 'bg-light';
$extra_head = '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />';

include __DIR__ . '/includes/head.php';
?>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<section class="py-5">
    <div class="container" style="max-width: 800px;">
        <h3 class="fw-bold mb-1">📋 Tugas Aktif</h3>
        <p class="text-muted mb-4">Daftar tugas untuk kelas <?= htmlspecialchars($kelas_siswa) ?>.</p>

        <?php if (empty($daftar_tugas)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    Belum ada tugas yang di-deploy untuk kelas kamu.
                </div>
            </div>
        <?php else: ?>
            <div class="list-group shadow-sm">
                <?php foreach ($daftar_tugas as $t):
    $deadlineTs = $t['deadline'] ? strtotime($t['deadline']) : null;
    $sudahLewatDeadline = $deadlineTs && time() > $deadlineTs;
    
    // Nilai hanya tampil kalau: sudah upload + deadline lewat (atau tidak ada deadline) + nilai tidak null
    $nilaiTersedia = !empty($t['pengumpulan_id'])
                     && $t['nilai'] !== null
                     && ($deadlineTs === null || $sudahLewatDeadline);

    if ($t['pengumpulan_id']) {
        $uploadTs   = strtotime($t['uploaded_at']);
        $terlambat  = $deadlineTs && $uploadTs > $deadlineTs;
        $statusBadge = $terlambat
            ? '<span class="badge bg-danger">Terlambat</span>'
            : '<span class="badge bg-success">Sudah Dikerjakan</span>';
    } else {
        $statusBadge = $sudahLewatDeadline
            ? '<span class="badge bg-danger">Belum Dikerjakan (Lewat Deadline)</span>'
            : '<span class="badge bg-secondary">Belum Dikerjakan</span>';
    }
?>
<div class="list-group-item py-3">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <div class="fw-bold"><?= htmlspecialchars($t['judul']) ?></div>
            <?php if ($deadlineTs): ?>
                <div class="small text-muted">Batas waktu: <?= date('d M Y, H:i', $deadlineTs) ?></div>
            <?php endif; ?>
            <?php $lkpd_url = !empty($t['link_lkpd']) ? $t['link_lkpd'] : $link_tugas; ?>
            <a href="<?= htmlspecialchars($lkpd_url) ?>" target="_blank" rel="noopener noreferrer" class="small">
                📄 Baca LKPD dulu
            </a>
        </div>
        <div class="text-end">
            <div class="mb-2"><?= $statusBadge ?></div>
            <a href="kumpul_tugas.php?id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-primary">Buka / Kumpulkan</a>
        </div>
    </div>

    <?php if ($nilaiTersedia): ?>
        <div class="mt-3 pt-3 border-top">
            <div class="d-flex align-items-baseline gap-2">
                <span class="text-muted small">Nilai:</span>
                <span class="fw-bold fs-5 text-dark"><?= htmlspecialchars(number_format((float)$t['nilai'], 2, ',', '.')) ?></span>
                <span class="text-muted small">/ 100</span>
            </div>
            <?php if (!empty($t['catatan_guru'])): ?>
                <div class="mt-2 small text-dark">
                    <i class="bi bi-chat-left-quote"></i>
                    <em><?= nl2br(htmlspecialchars($t['catatan_guru'])) ?></em>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($t['pengumpulan_id']) && $t['nilai'] === null && $deadlineTs && !$sudahLewatDeadline): ?>
        <div class="mt-2 small text-muted">
            <i class="bi bi-hourglass-split"></i> Nilai akan muncul setelah deadline.
        </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<footer class="py-5 bg-dark">
    <div class="container"><p class="m-0 text-center text-white">Copyright &copy; SIJA Website 2026</p></div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>