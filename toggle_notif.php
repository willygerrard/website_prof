<?php
require_once 'session.php';      // session dulu
require_once 'csrf_helper.php';  // baru helper
require 'koneksi.php';

checkLogin();
checkRole(['admin']);

if ($_SESSION['role'] !== 'admin') {
    header("HTTP/1.1 404 Not Found");
    exit();
}

$pesan = '';
$pesan_type = '';

// Toggle status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle'])) {
    csrf_require_valid_post();
    
    $status_baru = $_POST['toggle'] === 'aktif' ? 'aktif' : 'nonaktif';
    $now = date('Y-m-d H:i:s');

    $row_id = $pdo->query("SELECT id FROM notifikasi_sesi ORDER BY id DESC LIMIT 1")->fetchColumn();

    if ($row_id === false) {
        $stmt = $pdo->prepare("INSERT INTO notifikasi_sesi (status, diaktifkan_at, dinonaktifkan_at) VALUES (?, ?, ?)");
        $stmt->execute([
            $status_baru,
            $status_baru === 'aktif' ? $now : null,
            $status_baru === 'aktif' ? null : $now,
        ]);
    } elseif ($status_baru === 'aktif') {
        $stmt = $pdo->prepare("UPDATE notifikasi_sesi SET status = 'aktif', diaktifkan_at = ? WHERE id = ?");
        $stmt->execute([$now, (int)$row_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE notifikasi_sesi SET status = 'nonaktif', dinonaktifkan_at = ? WHERE id = ?");
        $stmt->execute([$now, (int)$row_id]);
    }

    if ($status_baru === 'aktif') {
        $pesan = "🟢 Notifikasi WA modul AKTIF. Siswa yang buka modul sekarang akan mengirim WA ke ortu.";
        $pesan_type = 'success';
    } else {
        $pesan = "🔴 Notifikasi WA modul NONAKTIF. Siswa tetap bisa akses modul bebas, tapi WA tidak terkirim.";
        $pesan_type = 'secondary';
    }
}

// Ambil status terkini
$status_sekarang = $pdo->query("SELECT status, diaktifkan_at, dinonaktifkan_at FROM notifikasi_sesi ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$is_aktif = ($status_sekarang['status'] ?? 'nonaktif') === 'aktif';

$topbar_page = 'Toggle Notifikasi WA';

$html_lang  = 'id';
$page_title = 'Toggle Notifikasi WA - Pusat Pembelajaran SIJA';
$body_class = 'bg-light';
$extra_head = '<link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />';

include __DIR__ . '/includes/head.php';
?>

<!-- Bungkus dengan shell untuk sidebar -->
<div class="sj-shell flex-grow-1">

    <!-- Include Sidebar -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Konten Utama -->
    <main class="sj-main bg-light pb-5">

        <?php include __DIR__ . '/includes/topbar.php'; ?>

        <!-- KONTEN -->
        <div class="container py-4 mx-auto" style="max-width: 560px;">

        <?php if ($pesan): ?>
        <div class="alert alert-<?= $pesan_type ?> alert-dismissible fade show" role="alert">
            <?= $pesan ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="card-title mb-0 fw-bold">📲 Toggle Notifikasi WA Modul</h5>
            </div>
            <div class="card-body p-4 text-center">

                <p class="text-muted small mb-4">
                    Nyalakan saat sedang mengajar (KBM berlangsung) agar siswa yang membuka modul
                    otomatis mengirim notifikasi WA ke orang tua. Matikan setelah KBM selesai —
                    siswa tetap bisa akses modul bebas tanpa mengirim WA.
                </p>

                <div class="mb-4">
                    <span class="badge fs-5 px-4 py-2 <?= $is_aktif ? 'bg-success' : 'bg-secondary' ?>">
                        <?= $is_aktif ? '🟢 SEDANG AKTIF' : '🔴 SEDANG NONAKTIF' ?>
                    </span>
                </div>

                <?php if (!empty($status_sekarang['diaktifkan_at']) && $is_aktif): ?>
                <p class="text-muted small">Diaktifkan pada: <?= date('d M Y, H:i', strtotime($status_sekarang['diaktifkan_at'])) ?></p>
                <?php endif; ?>

                <form method="POST">
                    <?= csrf_field(); ?>
                    
                    <?php if ($is_aktif): ?>
                        <button type="submit" name="toggle" value="nonaktif" class="btn btn-danger w-100 fw-bold py-3">
                            <i class="bi bi-stop-circle"></i> Matikan Notifikasi
                        </button>
                    <?php else: ?>
                        <button type="submit" name="toggle" value="aktif" class="btn btn-success w-100 fw-bold py-3">
                            <i class="bi bi-play-circle"></i> Aktifkan Notifikasi
                        </button>
                    <?php endif; ?>
                </form>

            </div>
        </div>

        <div class="text-center mt-3">
            <a href="index.php" class="text-muted small text-decoration-none">← Kembali ke Beranda</a>
        </div>
        </div>
    </main>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>