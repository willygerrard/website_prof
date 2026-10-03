<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

require_once 'koneksi.php';

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

if (!empty($_SESSION['butuh_ganti_password'])) {
    header("Location: akun_saya.php");
    exit();
}

$is_lulus = ($_SESSION['role'] === 'siswa' && ($_SESSION['status'] ?? '') === 'lulus');

$search           = trim($_GET['keyword'] ?? '');
$filter_jenis     = $_GET['jenis'] ?? 'semua';
$kategori_pilihan = $_GET['kategori'] ?? 'Semua Materi';

$query_str = "SELECT * FROM modules WHERE 1=1";
$params    = [];

if (!empty($search)) {
    $query_str .= " AND (`title` LIKE :search OR `description` LIKE :search)";
    $params['search'] = "%" . $search . "%";
}
if ($filter_jenis !== 'semua') {
    $query_str .= " AND jenis_Resource = :jenis";
    $params['jenis'] = $filter_jenis;
}
if ($kategori_pilihan !== 'Semua' && $kategori_pilihan !== 'Semua Materi') {
    $query_str .= " AND category = :kategori";
    $params['kategori'] = $kategori_pilihan;
}
if ($_SESSION['role'] === 'siswa') {
    $tingkat_siswa = $_SESSION['tingkat'] ?? '';
    $query_str .= " AND (kelas_target = 'semua' OR FIND_IN_SET(:tingkat, kelas_target))";
    $params['tingkat'] = $tingkat_siswa;
}
$query_str .= " ORDER BY id DESC";

$stmt = $pdo->prepare($query_str);
$stmt->execute($params);
$all_modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Link pengumpulan tugas per kelas
$link_tugas_default = 'https://acesse.one/3xcdcbh';
$link_tugas_per_kelas = [
    'X TKJ 1'   => 'https://tinyurl.com/4fhc6pkh',
    'X TKJ 2'   => 'https://tinyurl.com/utfrsmta',
    'X TKJ 3'   => 'https://tinyurl.com/8wz4d5ym',
    'X TKJ 4'   => 'https://tinyurl.com/4hk9vwwh',
    'XI SIJA'   => 'https://tinyurl.com/4awhwbfh',
    'XII SIJA'  => 'https://tinyurl.com/2ztnwwyd',
    'XIII SIJA' => 'https://tinyurl.com/2ztnwwyd',
];

$link_tugas = $link_tugas_default;
if ($_SESSION['role'] === 'siswa') {
    $user_id_navbar = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
    if ($user_id_navbar) {
        $stmtKelasNavbar = $pdo->prepare("SELECT kelas FROM users WHERE id = ?");
        $stmtKelasNavbar->execute([$user_id_navbar]);
        $kelas_navbar = $stmtKelasNavbar->fetchColumn();
        if ($kelas_navbar && isset($link_tugas_per_kelas[$kelas_navbar])) {
            $link_tugas = $link_tugas_per_kelas[$kelas_navbar];
        }
    }
}

$html_lang  = 'en';
$page_title = 'Pusat Pembelajaran SIJA';
$body_class = '';
$extra_head = '<meta name="description" content="" />
    <meta name="author" content="" />
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />';

include __DIR__ . '/includes/head.php';
?>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<header class="py-5" style="
    background: linear-gradient(to bottom, rgba(15, 23, 42, 0.85), rgba(30, 41, 59, 0.9)),
                url('https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=800');
    background-size: cover;
    background-position: center;">
    <div class="container px-4 px-lg-5 my-5">
        <div class="text-center text-white">
            <h1 class="display-4 fw-bolder">
                <?= $kategori_pilihan === 'Semua' ? 'Pusat Pembelajaran SIJA' : htmlspecialchars($kategori_pilihan) ?>
            </h1>
            <p class="lead fw-normal text-white-50 mb-0">
                <?= $kategori_pilihan === 'Semua'
                    ? 'Selamat datang di portal lab kendali materi mandiri.'
                    : 'Menampilkan modul khusus kategori ' . htmlspecialchars($kategori_pilihan) ?>
            </p>
        </div>
    </div>
</header>

<section class="py-5">
    <div class="container mt-1 mb-1">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center mb-2">
                <?php include 'ai_sandbox.php'; ?>
            </div>
        </div>

        <div class="text-center">
            <div class="btn-group shadow-sm bg-white p-1 rounded-3" role="group" aria-label="Filter Materi">
                <a href="index.php?jenis=semua&kategori=<?= urlencode($kategori_pilihan) ?>&keyword=<?= urlencode($search) ?>"
                   class="btn btn-light filter-btn px-3 py-2 text-dark fw-semibold rounded-2 <?= $filter_jenis == 'semua' ? 'active' : '' ?>">
                   Semua Materi
                </a>
                <a href="index.php?jenis=modul&kategori=<?= urlencode($kategori_pilihan) ?>&keyword=<?= urlencode($search) ?>"
                   class="btn btn-light filter-btn px-3 py-2 text-dark fw-semibold rounded-2 <?= $filter_jenis == 'modul' ? 'active' : '' ?>">
                   Modul (PDF)
                </a>
                <a href="index.php?jenis=media&kategori=<?= urlencode($kategori_pilihan) ?>&keyword=<?= urlencode($search) ?>"
                   class="btn btn-light filter-btn px-3 py-2 text-dark fw-semibold rounded-2 <?= $filter_jenis == 'media' ? 'active' : '' ?>">
                   Media (PPT)
                </a>
                <a href="index.php?jenis=video&kategori=<?= urlencode($kategori_pilihan) ?>&keyword=<?= urlencode($search) ?>"
                   class="btn btn-light filter-btn px-3 py-2 text-dark fw-semibold rounded-2 <?= $filter_jenis == 'video' ? 'active' : '' ?>">
                   Video
                </a>
            </div>
        </div>

        <!-- GRID MODUL — id="modulGrid" supaya AI result bisa replace isinya -->
        <div class="row gx-4 gx-lg-5 row-cols-2 row-cols-md-3 row-cols-xl-4 justify-content-center" id="modulGrid">
            <?php if (empty($all_modules)): ?>
                <div class="col-12 text-center">
                    <p class="text-muted">Belum ada modul yang dimasukkan ke database nih, Pak.</p>
                </div>
            <?php else: ?>
                <?php foreach ($all_modules as $modul): ?>
                <div class="col mb-5">
                    <div class="card h-100 shadow-sm">
                        <div class="badge bg-dark text-white position-absolute" style="top: 0.5rem; right: 0.5rem">
                            <?= htmlspecialchars($modul['category']) ?>
                        </div>
                        <img class="card-img-top"
                             src="<?= !empty($modul['image_path']) ? htmlspecialchars($modul['image_path']) : 'https://dummyimage.com/450x300/dee2e6/6c757d.jpg' ?>"
                             alt="Ikon Modul" />
                        <div class="card-body p-4">
                            <div class="text-center">
                                <h5 class="fw-bolder"><?= htmlspecialchars($modul['title']) ?></h5>
                                <p class="text-muted small mt-2"><?= htmlspecialchars($modul['description']) ?></p>
                            </div>
                        </div>
                        <div class="card-footer p-4 pt-0 border-top-0 bg-transparent">
                            <div class="d-grid gap-2">
                                <a class="btn btn-outline-dark fw-bold buka-modul-btn"
                                   href="<?= htmlspecialchars($modul['file_path']) ?>"
                                   target="_blank" rel="noopener noreferrer"
                                   data-id="<?= (int) $modul['id'] ?>">
                                    📂 Buka Modul
                                </a>
                                <a class="btn btn-success fw-bold cekpoint-btn disabled"
                                   id="cekpoint-<?= (int) $modul['id'] ?>"
                                   style="pointer-events:none;" href="#">
                                    ⏳ Buka dan Baca Modul Terlebih Dahulu
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<footer class="py-5 bg-dark">
    <div class="container"><p class="m-0 text-center text-white">Copyright &copy; SIJA Website 2026</p></div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/scripts.js"></script>

<script>
// Event delegation — otomatis works untuk kartu dinamis dari AI Search
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.buka-modul-btn');
    if (!btn) return;

    const modulId = btn.dataset.id;
    if (!modulId) return;

    if (btn.dataset.started === '1') return;
    btn.dataset.started = '1';

    // Notifikasi WA (fire-and-forget)
    fetch('notify_buka.php?id=' + encodeURIComponent(modulId))
        .catch(err => console.error('Gagal kirim notifikasi:', err));

    const cekpointBtn = document.getElementById('cekpoint-' + modulId);
    if (!cekpointBtn) return;

    let waktu = 100;
    cekpointBtn.innerHTML = '⏳ Tunggu ' + waktu + ' detik...';

    const hitung = setInterval(function () {
        waktu--;
        if (waktu > 0) {
            cekpointBtn.innerHTML = '⏳ Tunggu ' + waktu + ' detik...';
        } else {
            clearInterval(hitung);
            cekpointBtn.classList.remove('disabled');
            cekpointBtn.style.pointerEvents = 'auto';
            cekpointBtn.href = 'checkpoint_quiz.php?modul_id=' + modulId;
            cekpointBtn.innerHTML = '✅ Cek Point (1 Pertanyaan)';
        }
    }, 1000);
});
</script>

</body>
</html>