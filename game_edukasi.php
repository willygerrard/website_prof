<?php
include 'koneksi.php';
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

$search = trim($_GET['keyword'] ?? '');
$query_str = "SELECT * FROM games WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query_str .= " AND (title LIKE :search OR description LIKE :search)";
    $params['search'] = "%" . $search . "%";
}

$query_str .= " ORDER BY id DESC";
$stmt = $pdo->prepare($query_str);
$stmt->execute($params);
$all_games = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Game Edukasi - Pusat Pembelajaran SIJA</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />
</head>
<body>
    <!-- Navigation (Sama dengan Index) -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand" href="index.php">Pusat Pembelajaran SIJA</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item"><a class="nav-link" href="index.php">Materi</a></li>
                    <li class="nav-item"><a class="nav-link" href="https://acesse.one/umdz1td">Pengumpulan Tugas</a></li>
                    <li class="nav-item"><a class="nav-link" href="kuis.php">Kuis</a></li>
                    <li class="nav-item"><a class="nav-link active fw-bold text-primary" href="game_edukasi.php">🎮 Game Edukasi</a></li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <?php if (isset($_SESSION['username'])) : ?>
                        <span class="text-secondary fw-medium d-none d-md-inline small">
                            👋 Hai, <strong class="text-dark"><?= htmlspecialchars($_SESSION['username']); ?></strong>
                        </span>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm d-flex align-items-center gap-2">
                        <i class="bi bi-box-arrow-right"></i><span>Exit</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Header Banner -->
    <header class="py-5" style="
        background: linear-gradient(to bottom, rgba(15, 23, 42, 0.85), rgba(30, 41, 59, 0.9)), 
                    url('https://images.unsplash.com/photo-1550745165-9bc0b252726f?q=80&w=800'); 
        background-size: cover; background-position: center;">
        <div class="container px-4 px-lg-5 my-4">
            <div class="text-center text-white">
                <h1 class="display-5 fw-bolder">🎮 Portal Game Edukasi SIJA</h1>
                <p class="lead fw-normal text-white-50 mb-0">Asah logika dan pemahaman jaringanmu lewat interaktif mini-games berbasis HTML</p>
            </div>
        </div>
    </header>

    <!-- Content Section -->
    <section class="py-5">
        <div class="container px-4 px-lg-5 mt-1 mb-1">
            <!-- Form Pencarian -->
            <div class="row justify-content-center">
                <div class="col-md-8 text-center mb-4">
                    <form action="game_edukasi.php" method="GET" class="d-flex gap-2 shadow-sm p-2 bg-white rounded-3">
                        <input type="text" name="keyword" class="form-control border-0" placeholder="Cari nama game (misal: Subnetting, TKJ Game)..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-primary px-4 rounded-2">Cari</button>
                        <?php if (!empty($search)): ?>
                            <a href="game_edukasi.php" class="btn btn-outline-secondary">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Row Daftar Game (Model Kartu) -->
            <div class="row gx-4 gx-lg-5 row-cols-1 row-cols-md-2 row-cols-xl-3 justify-content-center">
                <?php if (empty($all_games)): ?>
                    <div class="col-12 text-center my-5">
                        <div class="p-5 bg-light rounded-3 shadow-sm">
                            <i class="bi bi-controller display-4 text-secondary mb-3 d-block"></i>
                            <h5 class="fw-bold text-muted">Belum ada Game Edukasi yang tersedia saat ini.</h5>
                            <p class="small text-secondary">Nantikan pembaruan game interaktif berikutnya!</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($all_games as $game): ?>
                    <div class="col mb-5">
                        <div class="card h-100 shadow-sm border-0 rounded-3 overflow-hidden">
                            <!-- Badge Kategori -->
                            <div class="badge bg-primary text-white position-absolute" style="top: 0.8rem; right: 0.8rem">
                                <?= htmlspecialchars($game['category']); ?>
                            </div>
                            
                            <!-- Cover Gambar -->
                            <img class="card-img-top" 
                                 src="<?= !empty($game['image_path']) ? htmlspecialchars($game['image_path']) : 'https://images.unsplash.com/photo-1511512578047-dfb367046420?q=80&w=450&h=300&fit=crop'; ?>" 
                                 alt="Cover Game" style="height: 200px; object-fit: cover;" />
                            
                            <!-- Isi Kartu -->
                            <div class="card-body p-4 d-flex flex-column">
                                <h5 class="fw-bolder mb-2"><?= htmlspecialchars($game['title']); ?></h5>
                                <p class="text-muted small flex-grow-1"><?= htmlspecialchars($game['description']); ?></p>
                            </div>
                            
                            <!-- Footer Tombol -->
                            <div class="card-footer p-4 pt-0 border-top-0 bg-transparent">
                                <div class="d-grid">
                                    <!-- Link langsung menuju file Google Drive/Web game -->
                                    <a class="btn btn-primary fw-bold py-2 shadow-sm d-flex align-items-center justify-content-center gap-2"
                                    href="<?= htmlspecialchars($game['file_path']) ?>"
                                     target="_blank"
                                    rel="noopener noreferrer">
                                        <i class="bi bi-play-circle-fill fs-5"></i> Mainkan Game
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
</body>
</html>