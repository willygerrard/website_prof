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

$is_siswa = ($_SESSION['role'] === 'siswa');
$is_lulus = ($is_siswa && ($_SESSION['status'] ?? '') === 'lulus');

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
if ($is_siswa) {
    $tingkat_siswa = $_SESSION['tingkat'] ?? '';
    $query_str .= " AND (kelas_target = 'semua' OR FIND_IN_SET(:tingkat, kelas_target))";
    $params['tingkat'] = $tingkat_siswa;
}
$query_str .= " ORDER BY id DESC";

$stmt = $pdo->prepare($query_str);
$stmt->execute($params);
$all_modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil daftar modul yang sudah tuntas untuk user ini
$modul_tuntas_arr = [];
if ($is_siswa) {
    $user_id_query = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
    if ($user_id_query) {
        $stmt_tuntas = $pdo->prepare("SELECT modul_id FROM modul_tuntas WHERE user_id = ?");
        $stmt_tuntas->execute([$user_id_query]);
        $modul_tuntas_arr = $stmt_tuntas->fetchAll(PDO::FETCH_COLUMN);
    }
}

// === Progres belajar per kategori (untuk siswa) ===
$progress_per_kategori = [];
$progress_total = ['done' => 0, 'total' => 0, 'pct' => 0];

if ($is_siswa) {
    $user_id_progress = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
    if ($user_id_progress) {
        $tingkat_progress = $_SESSION['tingkat'] ?? '';
        $sql_progress = "SELECT m.category,
                       COUNT(*) AS total,
                       COALESCE(SUM(mt.id IS NOT NULL), 0) AS done
                FROM modules m
                LEFT JOIN modul_tuntas mt
                       ON mt.modul_id = m.id AND mt.user_id = :uid
                WHERE (m.kelas_target = 'semua' OR FIND_IN_SET(:tingkat, m.kelas_target))
                    GROUP BY m.category";
        $st_progress = $pdo->prepare($sql_progress);
        $st_progress->execute(['uid' => $user_id_progress, 'tingkat' => $tingkat_progress]);
        foreach ($st_progress->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pct = (int)$row['total'] > 0 ? (int)round($row['done'] / $row['total'] * 100) : 0;
            $progress_per_kategori[$row['category']] = [
                'done'  => (int)$row['done'],
                'total' => (int)$row['total'],
                'pct'   => $pct,
            ];
            $progress_total['done']  += (int)$row['done'];
            $progress_total['total'] += (int)$row['total'];
        }
        $progress_total['pct'] = $progress_total['total'] > 0
            ? (int)round($progress_total['done'] / $progress_total['total'] * 100) : 0;
    }
}

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
if ($is_siswa) {
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

$html_lang  = 'id';
$page_title = 'Pusat Pembelajaran SIJA';
$body_class = 'bg-light';
$extra_head = '<meta name="description" content="Pusat Pembelajaran SIJA" />
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet" />';

include __DIR__ . '/includes/head.php';
?>

<!-- Bungkus dengan shell untuk sidebar -->
<div class="sj-shell flex-grow-1">
    
    <!-- Include Sidebar -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Konten Utama -->
    <main class="sj-main bg-light pb-5">

        <?php include __DIR__ . '/includes/topbar.php'; ?>
        
        <!-- Header Sapaan -->
        <div class="container-fluid px-4 pt-4 pb-2">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-0 text-dark">Halo, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>! 👋</h4>
                    <p class="text-muted small mb-0">Lanjutkan belajar, modul yang kamu pelajari hari ini menentukan masa depanmu.</p>
                </div>
                <?php if ($is_lulus): ?>
                    <span class="badge bg-success py-2 px-3">Lulus</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Area AI Navigator -->
        <div class="container-fluid px-4 mt-2 mb-4">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-3">
                    <?php include 'ai_sandbox.php'; ?>
                </div>
            </div>
        </div>

        <!-- Progress Belajar (Hanya Siswa) -->
        <?php if ($is_siswa && $progress_total['total'] > 0): ?>
        <div class="container-fluid px-4 mb-4">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <a class="text-decoration-none text-dark fw-bold d-flex align-items-center gap-2" data-bs-toggle="collapse" href="#collapseProgress" role="button" aria-expanded="true" aria-controls="collapseProgress">
                            📊 Progres belajar kamu
                        </a>
                        <div class="d-flex align-items-center gap-3">
                            <span class="fw-bold fs-5 text-dark"><?= $progress_total['pct'] ?>%</span>
                            <a class="text-muted small text-decoration-none" data-bs-toggle="collapse" href="#collapseProgress" role="button">Tutup</a>
                        </div>
                    </div>
                    
                    <div class="collapse show" id="collapseProgress">
                        <div class="mb-4">
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar <?= $progress_total['pct'] == 100 ? 'bg-success' : 'bg-primary' ?>" style="width: <?= $progress_total['pct'] ?>%;"></div>
                            </div>
                        </div>
                        <div class="row g-3">
                            <?php foreach ($progress_per_kategori as $kategori => $p): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="fw-medium"><?= htmlspecialchars($kategori ?? 'Lainnya') ?></span>
                                        <span class="text-muted"><?= $p['done'] ?>/<?= $p['total'] ?> modul · <?= $p['pct'] ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar <?= $p['pct'] == 100 ? 'bg-success' : 'bg-primary' ?>" style="width: <?= $p['pct'] ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-muted small mt-3">
                            <?php if ($progress_total['pct'] < 100): ?>
                                Lanjutkan: <?= $progress_total['done'] ?> dari <?= $progress_total['total'] ?> modul telah diselesaikan. 💪
                            <?php else: ?>
                                🏆 Semua modul sudah dipelajari. Hebat!
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Area Pencarian & Filter -->
        <div class="container-fluid px-4 mb-4">
            <div class="row g-2 align-items-center mb-3">
                <div class="col-12 col-md-6 col-lg-4">
                    <form method="GET" action="index.php">
                        <div class="input-group">
                            <input type="text" name="keyword" class="form-control" placeholder="Cari modul..." value="<?= htmlspecialchars($search) ?>">
                            <button class="btn btn-outline-secondary bg-white" type="submit"><i class="bi bi-search"></i></button>
                        </div>
                    </form>
                </div>
                <div class="col-12 col-md-6 col-lg-8">
                    <div class="d-flex overflow-auto pb-1 pb-md-0 justify-content-md-end" style="gap: 8px; white-space: nowrap; -webkit-overflow-scrolling: touch;">
                        <a href="index.php?jenis=semua&kategori=<?= urlencode($kategori_pilihan) ?>&keyword=<?= urlencode($search) ?>" 
                           class="btn btn-sm px-3 <?= $filter_jenis == 'semua' ? 'btn-primary' : 'btn-outline-secondary bg-white' ?>">Semua Materi</a>
                        <a href="index.php?jenis=modul&kategori=<?= urlencode($kategori_pilihan) ?>&keyword=<?= urlencode($search) ?>" 
                           class="btn btn-sm px-3 <?= $filter_jenis == 'modul' ? 'btn-primary' : 'btn-outline-secondary bg-white' ?>">Modul (PDF)</a>
                        <a href="index.php?jenis=media&kategori=<?= urlencode($kategori_pilihan) ?>&keyword=<?= urlencode($search) ?>" 
                           class="btn btn-sm px-3 <?= $filter_jenis == 'media' ? 'btn-primary' : 'btn-outline-secondary bg-white' ?>">Media (PPT)</a>
                        <a href="index.php?jenis=video&kategori=<?= urlencode($kategori_pilihan) ?>&keyword=<?= urlencode($search) ?>" 
                           class="btn btn-sm px-3 <?= $filter_jenis == 'video' ? 'btn-primary' : 'btn-outline-secondary bg-white' ?>">Video</a>
                    </div>
                </div>
            </div>
            <?php if ($kategori_pilihan !== 'Semua Materi'): ?>
                <div class="mb-3">
                    <span class="badge bg-primary text-light px-3 py-2 rounded-pill">
                        Kategori: <?= htmlspecialchars($kategori_pilihan) ?> 
                        <a href="index.php" class="text-white ms-2 text-decoration-none" title="Hapus filter"><i class="bi bi-x-circle"></i></a>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Grid Modul -->
        <div class="container-fluid px-4">
            <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-xl-4" id="modulGrid">
                <?php if (empty($all_modules)): ?>
                    <div class="col-12 text-center py-5">
                        <div class="text-muted">
                            <i class="bi bi-folder-x fs-1 d-block mb-3"></i>
                            <p>Belum ada modul yang ditemukan.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($all_modules as $modul): ?>
                    <?php
                                           ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm border border-light-subtle rounded-4 position-relative bg-white">
                            
                            <!-- Badge Kategori -->
                            <div class="position-absolute top-0 end-0 m-3 z-2">
                                <span class="badge bg-dark rounded-pill px-3 py-2 small fw-normal">
                                    <?= htmlspecialchars($modul['category']) ?>
                                </span>
                            </div>
                            
                            <!-- Badge Tuntas -->
                            <?php if (in_array($modul['id'], $modul_tuntas_arr)): ?>
                                <div class="position-absolute top-0 start-0 m-3 z-2">
                                    <span class="badge bg-success rounded-pill px-3 py-2 small shadow-sm">
                                        🏅 Tuntas
                                    </span>
                                </div>
                            <?php endif; ?>

                            <!-- Area Gambar/Ikon -->
                            <div class="d-flex align-items-center justify-content-center p-3 mt-2" style="height: 180px; background-color: #fff;">
                                <?php if (!empty($modul['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($modul['image_path']) ?>" class="w-100 h-100" style="object-fit: contain;" alt="Thumbnail">
                                <?php else: ?>
                                    <i class="bi bi-file-earmark-text text-secondary opacity-25" style="font-size: 5rem;"></i>
                                <?php endif; ?>
                            </div>

                            <!-- Body Card -->
                            <div class="card-body p-4 text-center">
                                <h5 class="fw-bold mb-2 text-dark" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="<?= htmlspecialchars($modul['title']) ?>">
                                    <?= htmlspecialchars($modul['title']) ?>
                                </h5>
                                <p class="text-muted small mb-0" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.5;">
                                    <?= htmlspecialchars($modul['description']) ?>
                                </p>
                            </div>
                            
                            <!-- Footer Card (Tombol) -->
                            <div class="card-footer bg-transparent border-top-0 p-4 pt-0 mt-auto">
                                <div class="d-grid gap-2">
                                    <!-- Tombol Buka Modul (Semua Role) -->
                                    <a class="btn btn-outline-dark fw-bold buka-modul-btn rounded-2 py-2" 
                                       href="<?= htmlspecialchars($modul['file_path']) ?>" 
                                       target="_blank" rel="noopener noreferrer" 
                                       data-id="<?= (int) $modul['id'] ?>">
                                        📂 Buka Modul
                                    </a>
                                    
                                    <!-- Tombol Cek Point (HANYA UNTUK SISWA) -->
                                    <?php if ($is_siswa): ?>
                                        <a class="btn fw-bold cekpoint-btn rounded-2 py-2 <?= in_array($modul['id'], $modul_tuntas_arr) ? 'btn-success text-white' : 'btn-success text-white' ?>" 
                                           id="cekpoint-<?= (int) $modul['id'] ?>" 
                                           style="pointer-events: <?= in_array($modul['id'], $modul_tuntas_arr) ? 'auto' : 'none' ?>; opacity: <?= in_array($modul['id'], $modul_tuntas_arr) ? '1' : '0.8' ?>;" 
                                           href="<?= in_array($modul['id'], $modul_tuntas_arr) ? 'checkpoint_quiz.php?modul_id=' . (int)$modul['id'] : '#' ?>">
                                            <?php if (in_array($modul['id'], $modul_tuntas_arr)): ?>
                                                ✅ Cek Point (1 Pertanyaan)
                                            <?php else: ?>
                                                ⏳ Buka dan Baca Modul Terlebih Dahulu
                                            <?php endif; ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Script Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Event delegation untuk Buka Modul dan Timer Checkpoint
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.buka-modul-btn');
    if (!btn) return;

    const modulId = btn.dataset.id;
    if (!modulId) return;

    if (btn.dataset.started === '1') return;
    btn.dataset.started = '1';

    // Kirim notifikasi WA
    fetch('notify_buka.php?id=' + encodeURIComponent(modulId))
        .catch(err => console.error('Gagal kirim notifikasi:', err));

    const cekpointBtn = document.getElementById('cekpoint-' + modulId);
    if (!cekpointBtn) return;

    // Jika tombol checkpoint sudah aktif, jangan jalankan timer
    if (!cekpointBtn.classList.contains('disabled')) return;

    let waktu = 100;
    cekpointBtn.innerHTML = '⏳ Tunggu ' + waktu + ' detik...';

    const hitung = setInterval(function () {
        waktu--;
        if (waktu > 0) {
            cekpointBtn.innerHTML = '⏳ Tunggu ' + waktu + ' detik...';
        } else {
            clearInterval(hitung);
            // Ubah styling menjadi aktif
            cekpointBtn.style.opacity = '1';
            cekpointBtn.style.pointerEvents = 'auto';
            cekpointBtn.href = 'checkpoint_quiz.php?modul_id=' + modulId;
            cekpointBtn.innerHTML = '✅ Cek Point (1 Pertanyaan)';
        }
    }, 1000);
});
</script>

</body>
</html>