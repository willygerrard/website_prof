<?php
// Fallback: kalau halaman yang include navbar.php ini belum menghitung $link_tugas
// (misal daftar_tugas_siswa.php), hitung di sini supaya navbar tetap jalan di mana pun.
if (!isset($link_tugas)) {
    $link_tugas_default = 'https://acesse.one/3xcdcbh';
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
}
?>
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
                        <li><a class="dropdown-item" href="index.php?kategori=System Administration">System Administration</a></li>
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
                <?php if ($_SESSION['role'] === 'siswa'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" id="navbarTugasDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        📋 Pengumpulan Tugas
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarTugasDropdown">
                        <li><a class="dropdown-item" href="daftar_tugas_siswa.php">Tugas Aktif</a></li>
                        <li><a class="dropdown-item" href="<?= htmlspecialchars($link_tugas) ?>" target="_blank" rel="noopener noreferrer">Arsip Tugas (Google Drive)</a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($link_tugas) ?>" target="_blank" rel="noopener noreferrer">Pengumpulan Tugas</a></li>
                <?php endif; ?>
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
                        <li><a class="dropdown-item" href="management_tugas.php">Manage Tugas</a></li>
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