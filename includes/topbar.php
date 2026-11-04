<?php
if (!isset($_SESSION['role'])) {
    return;
}

$topbar_is_siswa = ($_SESSION['role'] === 'siswa');

$topbar_user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$topbar_nama    = $_SESSION['username'] ?? 'User';
if ($topbar_user_id && isset($pdo)) {
    try {
        $stmt_topbar = $pdo->prepare("SELECT COALESCE(NULLIF(TRIM(nama_asli), ''), username) FROM users WHERE id = ? LIMIT 1");
        $stmt_topbar->execute([$topbar_user_id]);
        $topbar_nama = $stmt_topbar->fetchColumn() ?: $topbar_nama;
    } catch (Throwable $e) {
        $topbar_nama = $_SESSION['username'] ?? 'User';
    }
}
$topbar_initial = $topbar_nama !== '' ? strtoupper(mb_substr($topbar_nama, 0, 1)) : '?';
$topbar_portal  = $topbar_is_siswa ? 'LMS Siswa' : 'LMS Admin Portal';
$topbar_page    = $topbar_page ?? 'Dashboard Utama';
$topbar_env     = (stripos(__DIR__, 'staging') !== false || stripos($_SERVER['HTTP_HOST'] ?? '', 'staging') !== false) ? 'Staging' : 'Production';
?>
<header class="sj-topbar d-flex justify-content-between align-items-center gap-2 gap-md-3">
    <div class="d-flex align-items-center gap-2 gap-md-3">
        <button class="sj-topbar-icon d-none d-lg-inline-flex" type="button" id="sjSidebarToggle"
                aria-controls="sjSidebar" aria-expanded="true" title="Sembunyikan sidebar">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <div class="sj-topbar-crumb d-none d-md-flex align-items-center gap-2">
            <span class="sj-topbar-portal"><?= htmlspecialchars($topbar_portal) ?></span>
            <i class="bi bi-chevron-right sj-topbar-sep" aria-hidden="true"></i>
            <span class="sj-topbar-page"><?= htmlspecialchars($topbar_page) ?></span>
        </div>
        <span class="sj-env-badge <?= $topbar_env === 'Staging' ? 'sj-env-staging' : 'sj-env-production' ?>">
            <span class="sj-env-dot" aria-hidden="true"></span><?= htmlspecialchars($topbar_env) ?>
        </span>
    </div>

    <div class="d-flex align-items-center gap-2 gap-md-3">
        <a href="<?= $topbar_is_siswa ? 'daftar_tugas_siswa.php' : 'management_pending.php' ?>" class="sj-topbar-icon" title="Notifikasi" aria-label="Notifikasi">
            <i class="bi bi-bell" aria-hidden="true"></i>
            <?php if (!$topbar_is_siswa && ($sj_pending ?? 0) > 0): ?>
                <span class="sj-topbar-dot" aria-hidden="true"></span>
            <?php endif; ?>
        </a>

        <div class="d-flex align-items-center gap-2">
            <span class="sj-topbar-avatar" aria-hidden="true"><?= htmlspecialchars($topbar_initial) ?></span>
            <span class="sj-topbar-name d-none d-md-inline"><?= htmlspecialchars($topbar_nama) ?></span>
            <span class="badge rounded-pill <?= $topbar_is_siswa ? 'bg-secondary' : 'bg-primary' ?>"><?= $topbar_is_siswa ? 'Siswa' : 'Admin' ?></span>
        </div>

        <a href="logout.php" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1" title="Keluar dari sistem">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
            <span class="d-none d-sm-inline">Logout</span>
        </a>
    </div>
</header>
