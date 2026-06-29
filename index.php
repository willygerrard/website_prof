<?php
// 1. Amankan halaman dengan satpam session yang kemarin
include 'koneksi.php';
ini_set('session.cookie_httponly', 1); // Anti diintip skrip jahat
ini_set('session.cookie_use_only_cookies', 1);
session_start();
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
    $query_str .= " AND ('title' LIKE :search OR `description` LIKE :search)";
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

$query_str .= " ORDER BY id DESC";

$stmt = $pdo->prepare($query_str);
$stmt->execute($params);
$all_modules = $stmt->fetchAll(PDO::FETCH_ASSOC); // Variabel penampung looping kartu
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>Pusat Pembelajaran SIJA</title>
        <!-- Favicon-->
        <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
        <!-- Bootstrap icons-->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet" />
        <!-- Core theme CSS (includes Bootstrap)-->
        <link href="css/styles.css" rel="stylesheet" />
    </head>
    <body>
        <!-- Navigation-->
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container px-4 px-lg-5">
                <a class="navbar-brand" href="#!">Pusat Pembelajaran SIJA</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                           <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Materi</a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="index.php?kategori=Cloud computing">Cloud computing</a></li>
                                <li><hr class="dropdown-divider" /></li>
                                <li><a class="dropdown-item" href="index.php?kategori=Internet of Things">Internet of Things</a></li>
                                <li><hr class="dropdown-divider" /></li>
                                <li><a class="dropdown-item" href="index.php?kategori=Network">Network</a></li>
                                <li><hr class="dropdown-divider" /></li>
                                <li><a class="dropdown-item" href="index.php?kategori=DevOps">DevOps</a></li>
                                <li><hr class="dropdown-divider" /></li>
                                <li><a class="dropdown-item" href="index.php">Semua Materi</a></li>
                            </ul>
                             <?php if ($_SESSION['role'] === 'siswa'): ?>
                                <li class="nav-item"><a class="nav-link" href="akun_saya.php">Akun Saya</a></li>
                                <li class="nav-item"><a class="nav-link" href="rapor_siswa.php">Rapor Saya</a></li>
                                <?php endif; ?> 
                            </li>
                            
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" id="adminDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Admin Panel</a>
                                <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                                    <li><a class="dropdown-item" href="gerbang-rahasia-sija">Manage Module</a></li>
                                    <li><hr class="dropdown-divider" /></li>
                                    <li><a class="dropdown-item" href="pintu-belakang-sija">Manage User</a></li>
                                    <li><hr class="dropdown-divider" /></li>
                                    <li><a class="dropdown-item" href="pintu-rahasia-sija">Manage Kuis</a></li>
                                    <li><hr class="dropdown-divider" /></li>
                                    <li><a class="dropdown-item" href="toggle_notif.php">Toggle Notifikasi WA</a></li>
                                </ul>
                            </li>
                            <?php endif; ?>
                            <li class="nav-item"><a class="nav-link" href="kuis.php">Kuis</a></li>  <!-- ← tambah ini -->
                    </ul>
                    <div class="d-flex align-items-center gap-3">
    <?php if (isset($_SESSION['username'])) : ?>
        <span class="text-secondary fw-medium d-none d-md-inline small">
            👋 Hai, <strong class="text-dark"><?= htmlspecialchars($_SESSION['username']); ?></strong>
        </span>
    <?php endif; ?>

    <a href="logout.php" class="btn btn-outline-danger d-flex align-items-center gap-2 fw-semibold px-3 py-1.5 shadow-sm rounded-3" title="Keluar dari Sistem">
        <i class="bi bi-box-arrow-right"></i> 
        <span>Exit</span>
    </a>
</div>
                </div>
            </div>
        </nav>
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
                  <!-- Searching berdasarkan Sorting, sementara dimatikan -->
                  <!--   <input type="hidden" name="kategori" value="<?= htmlspecialchars($kategori_pilihan) ?>"> -->
                  <!--   <input type="hidden" name="jenis" value="<?= htmlspecialchars($filter_jenis) ?>"> -->
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
                        <div class="text-center">
                            <!-- Tombol ini langsung mengarah ke Link Google Drive -->
                            <a class="btn btn-outline-dark mt-auto w-100" href="buka_modul.php?id=<?= $modul['id'] ?>" target="_blank">
                                📂 Buka Modul
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
    </body>
</html>