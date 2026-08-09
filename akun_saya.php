<?php
include 'koneksi.php';
session_start();
include 'csrf_helper.php';
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
    csrf_require_valid_post();

    // --- Update nama_asli ---
    $nama_asli = trim($_POST['nama_asli'] ?? '');
    if ($nama_asli !== '') {
        $stmt = $pdo->prepare("UPDATE users SET nama_asli = ? WHERE id = ?");
        $stmt->execute([$nama_asli, $user_id]);
    }

    // --- Update no_wa_ortu ---
    $no_wa_ortu = trim($_POST['no_wa_ortu'] ?? '');
    $no_wa_bersih = preg_replace('/[^0-9]/', '', $no_wa_ortu);

    if (!preg_match('/^(08|62)[0-9]{8,12}$/', $no_wa_bersih)) {
        $pesan = "Format nomor WA tidak valid. Contoh: 081234567890";
        $pesan_type = 'danger';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET no_wa_ortu = ? WHERE id = ?");
        $stmt->execute([$no_wa_bersih, $user_id]);
        $pesan = "✅ Data berhasil diperbarui!";
        $pesan_type = 'success';
    }

    // --- Update password (jika diisi) ---
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $password_konfirmasi = $_POST['password_konfirmasi'] ?? '';

    if ($password_lama !== '' || $password_baru !== '' || $password_konfirmasi !== '') {
        // Ambil hash password dari database
        $stmt_pass = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt_pass->execute([$user_id]);
        $row_pass = $stmt_pass->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($password_lama, $row_pass['password'])) {
            $pesan = "❌ Password lama salah.";
            $pesan_type = 'danger';
        } elseif (strlen($password_baru) < 6) {
            $pesan = "❌ Password baru minimal 6 karakter.";
            $pesan_type = 'danger';
        } elseif ($password_baru !== $password_konfirmasi) {
            $pesan = "❌ Konfirmasi password baru tidak cocok.";
            $pesan_type = 'danger';
        } else {
            $hash_baru = password_hash($password_baru, PASSWORD_DEFAULT);
            $stmt_up = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_up->execute([$hash_baru, $user_id]);

            // Password sudah diganti, hapus tanda "wajib ganti password" dari session.
            unset($_SESSION['butuh_ganti_password']);

            $pesan = "✅ Data & password berhasil diperbarui!";
            $pesan_type = 'success';
        }
    }
}

// Ambil data terkini
$stmt = $pdo->prepare("SELECT username, nama_asli, no_wa_ortu FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

$html_lang = 'id';
$page_title = 'Akun Saya - Pusat Pembelajaran SIJA';
$body_class = 'bg-light';
$extra_head = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">';

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
    $stmtKelasNavbar = $pdo->prepare("SELECT kelas FROM users WHERE id = ?");
    $stmtKelasNavbar->execute([$user_id]);
    $kelas_navbar = $stmtKelasNavbar->fetchColumn();
    if ($kelas_navbar && isset($link_tugas_per_kelas[$kelas_navbar])) {
        $link_tugas = $link_tugas_per_kelas[$kelas_navbar];
    }
}

include __DIR__ . '/includes/head.php';
?>

    <?php include __DIR__ . '/includes/navbar.php'; ?>

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
                    <p class="form-control-plaintext fw-bold ps-2"><?= htmlspecialchars($data['username']) ?></p>
                </div>

                <form method="POST">
                    <?= csrf_field() ?>

                    <!-- NAMA ASLI -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Lengkap (Asli)</label>
                        <input type="text" class="form-control" name="nama_asli"
                               value="<?= htmlspecialchars($data['nama_asli'] ?? '') ?>"
                               placeholder="Masukkan nama lengkap Anda" required>
                        <div class="form-text">Nama asli yang akan muncul di rapor/game.</div>
                    </div>

                    <!-- NO WA ORTU -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">No. WhatsApp Orang Tua/Wali</label>
                        <input type="text" class="form-control" name="no_wa_ortu"
                               value="<?= htmlspecialchars($data['no_wa_ortu'] ?? '') ?>"
                               placeholder="Contoh: 081234567890" required>
                        <div class="form-text">Pastikan nomor ini aktif untuk menerima notifikasi progress belajar.</div>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-bold text-muted mb-3">🔒 Ubah Password <span class="text-muted small fw-normal">(kosongkan jika tidak ingin ubah)</span></h6>

                    <!-- PASSWORD LAMA -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password Lama</label>
                        <input type="password" class="form-control" name="password_lama" placeholder="Masukkan password saat ini" autocomplete="current-password">
                    </div>

                    <!-- PASSWORD BARU -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password Baru</label>
                        <input type="password" class="form-control" name="password_baru" placeholder="Minimal 6 karakter" autocomplete="new-password">
                    </div>

                    <!-- KONFIRMASI PASSWORD BARU -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Konfirmasi Password Baru</label>
                        <input type="password" class="form-control" name="password_konfirmasi" placeholder="Ketik ulang password baru" autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2">
                        <i class="bi bi-check-circle"></i> Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>

        <div class="text-center mt-3">
            <a href="index.php" class="btn btn-outline-secondary px-4">
                <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>

    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>