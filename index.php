<?php
// 1. Amankan halaman dengan satpam session yang kemarin
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); // Anti diintip skrip jahat
    ini_set('session.use_only_cookies', 1);
    session_start();
}

include 'koneksi.php';

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

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
$all_modules = $stmt->fetchAll(PDO::FETCH_ASSOC); // Variabel penampung looping kartu

// Link Pengumpulan Tugas per kelas, biar siswa gak perlu cari-cari folder kelasnya sendiri.
// Kelas diambil langsung dari DB (bukan session) supaya selalu akurat.
$link_tugas_default = 'https://acesse.one/3xcdcbh'; // folder umum, dipakai kalau kelas belum ke-mapping
$link_tugas_per_kelas = [
    'X TKJ 1'  => 'https://tinyurl.com/4fhc6pkh',
    'X TKJ 2'  => 'https://tinyurl.com/utfrsmta',
    'X TKJ 3'  => 'https://tinyurl.com/8wz4d5ym',
    'X TKJ 4'  => 'https://tinyurl.com/4hk9vwwh',
    'XI SIJA'  => 'https://tinyurl.com/4awhwbfh',
    'XII SIJA' => 'https://tinyurl.com/2ztnwwyd',
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

// ===== Variabel untuk includes/head.php =====
// Nilai-nilai ini menyamai persis apa yang sebelumnya ditulis manual di <head> index.php,
// supaya tidak ada perubahan tampilan/perilaku yang tidak disengaja.
$html_lang  = 'en'; // catatan: index.php asli memang pakai lang="en" walau kontennya bahasa Indonesia, dibiarkan apa adanya
$page_title = 'Pusat Pembelajaran SIJA';
$body_class = ''; // index.php asli tidak punya class di <body>, dikosongkan (bukan fallback 'bg-light' bawaan head.php)
$extra_head = '<meta name="description" content="" />
    <meta name="author" content="" />
    <!-- Favicon-->
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
    <!-- Bootstrap icons-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet" />
    <!-- Core theme CSS (includes Bootstrap)-->
    <link href="css/styles.css" rel="stylesheet" />';

include __DIR__ . '/includes/head.php';
?>

    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <!-- Header-->
    <header class="py-5" style="
        background: linear-gradient(to bottom, rgba(15, 23, 42, 0.85), rgba(30, 41, 59, 0.9)),
                    url('https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=800');
        background-size: cover;
        background-position: center;">

        <div class="container px-4 px-lg-5 my-5">
            <div class="text-center text-white">
                <h1 class="display-4 fw-bolder">
                    <?php echo $kategori_pilihan === 'Semua' ? 'Pusat Pembelajaran SIJA' : '' . htmlspecialchars($kategori_pilihan); ?>
                </h1>
                <p class="lead fw-normal text-white-50 mb-0">
                    <?php echo $kategori_pilihan === 'Semua' ? 'Selamat datang di portal lab kendali materi mandiri.' : 'Menampilkan modul khusus kategori ' . htmlspecialchars($kategori_pilihan); ?>
                </p>
            </div>
        </div>
    </header>
    <!-- Section-->
    <section class="py-5">
        <div class="container mt-1 mb-1">
            <div class="row justify-content-center">
                <div class="col-md-8 text-center mb-2">
                    <form action="index.php" method="GET" class="d-flex gap-2 shadow-sm p-2 bg-white rounded-3 mb-3">
                        <input type="text" name="keyword" class="form-control border-0" placeholder="Ketik kata kunci materi (misal: Wifi, Mikrotik, Debian)..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-primary px-4 rounded-2">Cari</button>
                        <?php if (!empty($search)): ?>
                            <a href="index.php?kategori=<?= urlencode($kategori_pilihan) ?>&jenis=<?= urlencode($filter_jenis) ?>" class="btn btn-outline-secondary">Reset</a>
                        <?php endif; ?>
                    </form>
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
            <!-- Bagian Row Bungkus Kartu -->
            <div class="row gx-4 gx-lg-5 row-cols-2 row-cols-md-3 row-cols-xl-4 justify-content-center">

                <?php if (empty($all_modules)): ?>
                    <!-- Antisipasi kalau database Bapak ternyata masih kosong -->
                    <div class="col-12 text-center">
                        <p class="text-muted">Belum ada modul yang dimasukkan ke database nih, Pak.</p>
                    </div>
                <?php else: ?>

                    <!-- MULAI LOOPING KARTU DARI DATABASE -->
                    <?php foreach ($all_modules as $modul): ?>
                    <div class="col mb-5">
                        <div class="card h-100 shadow-sm">
                            <!-- Badge Kategori di Pojok Atas Kartu -->
                            <div class="badge bg-dark text-white position-absolute" style="top: 0.5rem; right: 0.5rem">
                                <?php echo htmlspecialchars($modul['category']); ?>
                            </div>

                            <!-- Gambar/Ikon Materi (Jika kosong, tampilkan placeholder abu-abu) -->
                            <img class="card-img-top" src="<?php echo !empty($modul['image_path']) ? htmlspecialchars($modul['image_path']) : 'https://dummyimage.com/450x300/dee2e6/6c757d.jpg'; ?>" alt="Ikon Modul" />

                            <!-- Detail Isi Modul -->
                            <div class="card-body p-4">
                                <div class="text-center">
                                    <!-- Judul Modul -->
                                    <h5 class="fw-bolder"><?php echo htmlspecialchars($modul['title']); ?></h5>
                                    <!-- Deskripsi Modul -->
                                    <p class="text-muted small mt-2"><?php echo htmlspecialchars($modul['description']); ?></p>
                                </div>
                            </div>

                            <!-- Bagian Tombol Aksi -->
                            <div class="card-footer p-4 pt-0 border-top-0 bg-transparent">
                                <div class="d-grid gap-2">
                                    <!-- Tombol buka modul: langsung ke link file, sekaligus memicu timer cek point -->
                                    <a class="btn btn-outline-dark fw-bold buka-modul-btn"
                                       href="<?= htmlspecialchars($modul['file_path']) ?>"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       data-id="<?= (int) $modul['id'] ?>">
                                        📂 Buka Modul
                                    </a>

                                    <!-- Tombol cek point: nonaktif sampai timer 100 detik selesai -->
                                    <a class="btn btn-success fw-bold cekpoint-btn disabled"
                                       id="cekpoint-<?= (int) $modul['id'] ?>"
                                       style="pointer-events:none;"
                                       href="#">
                                        ⏳ Buka dan Baca Modul Terlebih Dahulu
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <!-- SELESAI LOOPING -->

                <?php endif; ?>

            </div>
        </div>
    </section>
    <!-- Footer-->
    <footer class="py-5 bg-dark">
        <div class="container"><p class="m-0 text-center text-white">Copyright &copy; SIJA Website 2026</p></div>
    </footer>
    <!-- Bootstrap core JS-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Core theme JS-->
    <script src="js/scripts.js"></script>

    <!-- Script Buka Modul + Cek Point (pindahan dari buka_modul.php) -->
    <script>
    document.querySelectorAll('.buka-modul-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modulId    = this.dataset.id;
            var cekpointBtn = document.getElementById('cekpoint-' + modulId);

            // Cegah timer dobel kalau tombol diklik berkali-kali
            if (this.dataset.started === '1') return;
            this.dataset.started = '1';

            // Kirim notifikasi WA ke ortu di background, tanpa reload halaman
            fetch('notify_buka.php?id=' + encodeURIComponent(modulId))
                .catch(function (err) { console.error('Gagal kirim notifikasi:', err); });

            var waktu = 100;
            cekpointBtn.innerHTML = '⏳ Tunggu ' + waktu + ' detik...';

            var hitung = setInterval(function () {
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
    });
    </script>
</body>
</html>
