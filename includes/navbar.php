<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container px-4 px-lg-5">
        <a class="navbar-brand" href="index.php">Modul Pembelajaran SIJA</a>
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
                </li>
                <?php if ($_SESSION['role'] === 'siswa'): ?>
                <li class="nav-item"><a class="nav-link" href="akun_saya.php">Akun Saya</a></li>
                <li class="nav-item"><a class="nav-link" href="rapor_siswa.php">Rapor Saya</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($link_tugas) ?>" target="_blank" rel="noopener noreferrer">Pengumpulan Tugas</a></li>
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
                        <li><a class="dropdown-item" href="pintu-rahasia-modul">Manage Cekpoint</a></li>
                        <li><hr class="dropdown-divider" /></li>
                        <li><a class="dropdown-item" href="pintu-game-sija">Manage Game</a></li>
                        <li><hr class="dropdown-divider" /></li>
                        <li><a class="dropdown-item" href="toggle_notif.php">Toggle Notifikasi WA</a></li>
                        <li><hr class="dropdown-divider" /></li>
                        <li><a class="dropdown-item" href="pintu-pendaftaran-sija">🔓 Buka/Tutup Registrasi</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($_SESSION['role'] === 'siswa'): ?>
                <li class="nav-item"><a class="nav-link" href="kuis.php">Kuis</a></li>
                <li class="nav-item"><a class="nav-link fw-bold text-primary" href="game_edukasi.php">🎮 Game Edukasi</a></li>
                <?php endif; ?>
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
