<?php
include 'koneksi.php';
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("Sesi tidak valid. Silakan login ulang.");
}

$pesan = '';
$pesan_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no_wa_ortu = trim($_POST['no_wa_ortu'] ?? '');
    $no_wa_bersih = preg_replace('/[^0-9]/', '', $no_wa_ortu);

    if (!preg_match('/^(08|62)[0-9]{8,12}$/', $no_wa_bersih)) {
        $pesan = "Format nomor WA tidak valid. Contoh: 081234567890";
        $pesan_type = 'danger';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET no_wa_ortu = ? WHERE id = ?");
        $stmt->execute([$no_wa_bersih, $user_id]);
        $pesan = "✅ Nomor WA orang tua berhasil diperbarui!";
        $pesan_type = 'success';
    }
}

// Ambil data terkini
$stmt = $pdo->prepare("SELECT username, no_wa_ortu FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akun Saya - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- NAVBAR -->
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

    <!-- KONTEN -->
    <div class="container mt-5 mb-5" style="max-width: 500px;">

        <?php if ($pesan): ?>
        <div class="alert alert-<?= $pesan_type ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($pesan) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="card-title mb-0 fw-bold">⚙️ Akun Saya</h5>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted small">Username</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($data['username']) ?>" disabled>
                </div>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">No. WhatsApp Orang Tua/Wali</label>
                        <input type="text" class="form-control" name="no_wa_ortu"
                               value="<?= htmlspecialchars($data['no_wa_ortu'] ?? '') ?>"
                               placeholder="Contoh: 081234567890" required>
                        <div class="form-text">Pastikan nomor ini aktif untuk menerima notifikasi progress belajar.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2">
                        <i class="bi bi-check-circle"></i> Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>