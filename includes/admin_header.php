<!-- Navbar minimalis (brand + salam), dipakai di semua halaman admin/manajemen.
     Beda dari includes/navbar.php milik index.php: di sini sengaja tanpa menu
     item & tanpa toggler, karena halaman-halaman ini bukan navigasi utama situs. -->
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container px-4 px-lg-5">
        <a class="navbar-brand" href="index.php">Modul Pembelajaran SIJA</a>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4"></ul>
            <div class="d-flex align-items-center gap-3">
                <?php if (isset($_SESSION['username'])) : ?>
                    <span class="text-secondary fw-medium d-none d-md-inline small">
                        👋 Hai, <strong class="text-dark"><?= htmlspecialchars($_SESSION['username']); ?></strong>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Hero header. Boleh di-override per halaman dengan set $hero_title / $hero_subtitle
     SEBELUM include file ini; kalau tidak di-set, pakai teks default. -->
<header class="py-5" style="
    background: linear-gradient(to bottom, rgba(15, 23, 42, 0.85), rgba(30, 41, 59, 0.9)),
                url('https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=800');
    background-size: cover;
    background-position: center;">

    <div class="container px-4 px-lg-5 my-5">
        <div class="text-center text-white">
            <h1 class="display-4 fw-bolder">
                <?= htmlspecialchars($hero_title ?? 'Pusat Pembelajaran SIJA') ?>
            </h1>
            <p class="lead fw-normal text-white-50 mb-0">
                <?= htmlspecialchars($hero_subtitle ?? 'Selamat datang di portal lab kendali materi mandiri') ?>
            </p>
        </div>
    </div>
</header>