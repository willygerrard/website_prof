<?php
// 1. Amankan halaman dengan satpam session yang kemarin
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

// 2. Hubungkan ke database MariaDB internal Docker Bapak
$host = 'db';       // Menggunakan nama service di docker-compose
$db   = 'db_website_pribadi'; // Sesuaikan dengan nama DB Bapak
$user = 'willy';     // Sesuaikan dengan user DB Bapak
$pass = 'RahasiaPro2026!'; // Sesuaikan dengan password DB Bapak
$port = '3306';     // Wajib port internal karena sesama container

$dsn = "mysql:host=$host;dbname=$db;port=$port;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     
     // --- BAGIAN YANG DIGANTI/DITAMBAH ---
     // Tangkap kiriman kategori dari URL menu dropdown, kalau kosong set ke 'Semua'
     $kategori_pilihan = $_GET['kategori'] ?? 'Semua';

     if ($kategori_pilihan !== 'Semua') {
         // Jika siswa memilih kategori tertentu, saring pake query WHERE (Aman dari SQL Injection)
         $stmt = $pdo->prepare("SELECT * FROM modules WHERE category = ?");
         $stmt->execute([$kategori_pilihan]);
     } else {
         // Jika tidak memilih atau klik "Semua Materi", tampilkan semua
         $stmt = $pdo->query("SELECT * FROM modules");
     }
     
     $all_modules = $stmt->fetchAll();
     // ------------------------------------
     
} catch (\PDOException $e) {
     die("Aduh Pak, koneksi database gagal lagi: " . $e->getMessage());
}
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
                <a class="navbar-brand" href="#!">Modul Pembelajaran SIJA</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="#!">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
               
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Materi</a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="index.php?kategori=Cloud computing">Cloud computing</a></li>
                                <li><hr class="dropdown-divider" /></li>
                                <li><a class="dropdown-item" href="index.php?kategori=Internet of Things">Internet of Things</a></li>
                                <li><hr class="dropdown-divider" /></li>
                                <li><a class="dropdown-item" href="index.php?kategori=Network">Network</a></li>
                            </ul>
                                <li><a class="dropdown-item" href="index.php">Semua Materi</a></li>
                        </li>
                          <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                    </ul>
                    <form class="d-flex">
                        <button class="btn btn-outline-dark" type="submit">
                            <i class="bi-cart-fill me-1"></i>
                            Cart
                            <span class="badge bg-dark text-white ms-1 rounded-pill">0</span>
                        </button>
                    </form>
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
            <?php if ($_SESSION['role'] === 'admin'): ?>
    <div class="container mt-3 text-end">
        <a href="tambah_modul.php" class="btn btn-success"> Tambah Modul Baru</a>
    </div>
<?php endif; ?>
                <?php echo $kategori_pilihan === 'Semua' ? 'Pusat Pembelajaran SIJA' : 'Materi: ' . htmlspecialchars($kategori_pilihan); ?>
            </h1>
            <p class="lead fw-normal text-white-50 mb-0">
                <?php echo $kategori_pilihan === 'Semua' ? 'Selamat datang di portal lab kendali materi mandiri.' : 'Menampilkan modul khusus kategori ' . htmlspecialchars($kategori_pilihan); ?>
            </p>
        </div>
    </div>
</header>
        <!-- Section-->
        <section class="py-5">
            <div class="container px-4 px-lg-5 mt-5">
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
                            <!-- Tombol ini langsung mengarah ke Link Google Drive Bapak -->
                            <a class="btn btn-outline-dark mt-auto w-100" href="<?php echo htmlspecialchars($modul['file_path']); ?>" target="_blank">
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
