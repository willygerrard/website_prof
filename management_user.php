<?php
session_start();
require 'koneksi.php';
require 'csrf_helper.php';

// GUARD 1: Cek opo wis login lan opo role-ne beneran ADMIN
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (strpos($_SERVER['REQUEST_URI'], 'pintu-belakang-sija') === false) {
    header("HTTP/1.1 404 Not Found");
    exit();
}

$pesan = "";
$pesan_type = "info";

// Validasi CSRF token untuk semua aksi via GET
function validate_csrf_get() {
    $token = $_GET['csrf_token'] ?? '';
    if (!csrf_verify($token)) {
        die("❌ Token CSRF tidak valid. Silakan kembali ke halaman Management User.");
    }
}

// --- AKSI: NONAKTIFKAN USER (SOFT DELETE) ---
if (isset($_GET['action']) && $_GET['action'] === 'nonaktifkan' && isset($_GET['id'])) {
    validate_csrf_get();
    $id_target = (int)$_GET['id'];

    try {
        $stmt_update = $pdo->prepare("UPDATE users SET status = 'nonaktif' WHERE id = :id AND role != 'admin'");
        $stmt_update->execute(['id' => $id_target]);

        if ($stmt_update->rowCount() > 0) {
            $pesan = "<div style='color: #ffaa00; margin-bottom: 15px;'>🟠 Akun siswa dinonaktifkan. Riwayat nilai tetap tersimpan, siswa tidak bisa login lagi.</div>";
        } else {
            $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>❌ Gagal! Akun tidak ditemukan.</div>";
        }
    } catch (PDOException $e) {
        $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// --- AKSI: AKTIFKAN ULANG USER ---
if (isset($_GET['action']) && $_GET['action'] === 'aktifkan' && isset($_GET['id'])) {
    validate_csrf_get();
    $id_target = (int)$_GET['id'];

    try {
        $stmt_update = $pdo->prepare("UPDATE users SET status = 'aktif' WHERE id = :id AND role != 'admin'");
        $stmt_update->execute(['id' => $id_target]);
        $pesan = "<div style='color: #00ff66; margin-bottom: 15px;'>✅ Akun siswa diaktifkan kembali.</div>";
    } catch (PDOException $e) {
        $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// --- AKSI BARU: HAPUS PERMANEN (hanya jika belum punya riwayat nilai) ---
if (isset($_GET['action']) && $_GET['action'] === 'hapus_permanen' && isset($_GET['id'])) {
    validate_csrf_get();
    $id_target = (int)$_GET['id'];

    try {
        // Cek apakah siswa punya riwayat kuis atau checkpoint
        $stmt_cek_kuis = $pdo->prepare("SELECT COUNT(*) FROM kuis_hasil WHERE user_id = ?");
        $stmt_cek_kuis->execute([$id_target]);
        $ada_kuis = (int)$stmt_cek_kuis->fetchColumn() > 0;

        $stmt_cek_checkpoint = $pdo->prepare("SELECT COUNT(*) FROM checkpoint_hasil WHERE user_id = ?");
        $stmt_cek_checkpoint->execute([$id_target]);
        $ada_checkpoint = (int)$stmt_cek_checkpoint->fetchColumn() > 0;

        if ($ada_kuis || $ada_checkpoint) {
            $pesan = "<div style='color: #ff9933; margin-bottom: 15px;'>⚠️ Akun ini sudah punya riwayat nilai/kuis. Hapus permanen tidak diizinkan. Gunakan Nonaktifkan saja.</div>";
        } else {
            // Aman — belum ada riwayat nilai, hapus semua data terkait
            $pdo->beginTransaction();
            $stmt_del_notif = $pdo->prepare("DELETE FROM notifikasi_modul WHERE user_id = ?");
            $stmt_del_notif->execute([$id_target]);

            $stmt_del_user = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
            $stmt_del_user->execute([$id_target]);
            $pdo->commit();

            $pesan = "<div style='color: #ff4444; margin-bottom: 15px;'>🗑️ Akun permanen dihapus! (Belum ada riwayat nilai, aman dihapus).</div>";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// --- AKSI BARU: RESET PASSWORD ---
if (isset($_GET['action']) && $_GET['action'] === 'reset_password' && isset($_GET['id'])) {
    validate_csrf_get();
    $id_target = (int)$_GET['id'];
    $password_default = 'sija2026';
    $hash_baru = password_hash($password_default, PASSWORD_DEFAULT);

    try {
        $stmt_reset = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role != 'admin'");
        $stmt_reset->execute([$hash_baru, $id_target]);

        if ($stmt_reset->rowCount() > 0) {
            $pesan = "<div style='color: #00ccff; margin-bottom: 15px;'>🔑 Password berhasil direset menjadi: <strong>$password_default</strong> — beritahu siswa untuk login dengan password baru ini.</div>";
        } else {
            $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>❌ Gagal reset password. Akun tidak ditemukan.</div>";
        }
    } catch (PDOException $e) {
        $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// --- READ USER (TAMPILNO DAFTAR SISWA) ---
try {
    $stmt_read = $pdo->query("SELECT id, username, kelas, role, no_wa_ortu, status, created_at FROM users WHERE role = 'siswa' ORDER BY status ASC, id DESC");
    $daftar_siswa = $stmt_read->fetchAll();
} catch (PDOException $e) {
    die("Gagal njupuk data siswa: " . $e->getMessage());
}

// Token CSRF untuk semua link aksi
$csrf_token = csrf_token();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Management User</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #121212; color: #fff; padding: 30px; }
        .container { max-width: 900px; margin: auto; background: #1e1e1e; padding: 20px; border-radius: 8px; border: 1px solid #333; box-sizing: border-box; }
        h2 { color: #00cc66; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #333; }
        th { background: #2a2a2a; color: #00cc66; }
        tr:hover { background: #252525; }
        tr.nonaktif { opacity: 0.5; }
        .btn-action { padding: 6px 12px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 13px; display: inline-block; }
        .btn-nonaktif { background: #ff9933; color: #1a1a1a; }
        .btn-nonaktif:hover { background: #cc7a29; }
        .btn-aktif { background: #00cc66; color: #1a1a1a; }
        .btn-aktif:hover { background: #00994d; }
        .back-link { margin-top: 20px; display: block; color: #aaa; text-decoration: none; font-size: 14px; }
        .back-link:hover { color: #fff; }
        .wa-kosong { color: #ff9933; font-style: italic; font-size: 13px; }
        .badge-status { padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .badge-aktif { background: #003311; color: #00cc66; }
        .badge-nonaktif { background: #332211; color: #ff9933; }
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; margin-top: 15px; }
    </style>
</head>
<body>

<div class="table-responsive">
    <h2>Dashboard Admin: Management User</h2>
    <p>Halo <strong><?= htmlspecialchars($_SESSION['username']); ?></strong>, Ini daftar siswa yang terdaftar.</p>
    <p style="color: #888; font-size: 13px;">💡 Nonaktifkan siswa yang sudah lulus/keluar — riwayat nilai tetap tersimpan untuk arsip.</p>

    <?= $pesan; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username Siswa</th>
                <th>Kelas</th>
                <th>No. WA Ortu</th>
                <th>Status</th>
                <th>Tanggal Registrasi</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($daftar_siswa) > 0): ?>
                <?php foreach ($daftar_siswa as $siswa): 
                    $is_nonaktif = ($siswa['status'] ?? 'aktif') === 'nonaktif';
                ?>
                    <tr class="<?= $is_nonaktif ? 'nonaktif' : '' ?>">
                        <td><?= $siswa['id']; ?></td>
                        <td><?= htmlspecialchars($siswa['username']); ?></td>
                        <td>
                            <?php if (!empty($siswa['kelas'])): ?>
                                <?= htmlspecialchars($siswa['kelas']); ?>
                            <?php else: ?>
                                <span class="wa-kosong">belum diisi</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($siswa['no_wa_ortu'])): ?>
                                <?= htmlspecialchars($siswa['no_wa_ortu']); ?>
                            <?php else: ?>
                                <span class="wa-kosong">belum diisi</span>
                            <?php endif; ?>
                        </td>
                          
                        <td>
                            <span class="badge-status <?= $is_nonaktif ? 'badge-nonaktif' : 'badge-aktif' ?>">
                                <?= $is_nonaktif ? 'Nonaktif' : 'Aktif' ?>
                            </span>
                        </td>
                        <td><?= $siswa['created_at']; ?></td>
                        <td style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center;">
                            <?php if ($is_nonaktif): ?>
                                <a href="?action=aktifkan&id=<?= $siswa['id']; ?>&csrf_token=<?= urlencode($csrf_token) ?>"
                                   class="btn-action btn-aktif"
                                   onclick="return confirm('Aktifkan kembali akun ini?')">
                                   🔄 AKTIFKAN
                                </a>
                            <?php else: ?>
                                <a href="?action=nonaktifkan&id=<?= $siswa['id']; ?>&csrf_token=<?= urlencode($csrf_token) ?>"
                                   class="btn-action btn-nonaktif"
                                   onclick="return confirm('Nonaktifkan akun ini? Riwayat nilai tetap tersimpan, tapi siswa tidak bisa login lagi.')">
                                   🔴 NONAKTIFKAN
                                </a>
                            <?php endif; ?>

                            <!-- 🔑 RESET PASSWORD (muncul untuk semua status) -->
                            <a href="?action=reset_password&id=<?= $siswa['id']; ?>&csrf_token=<?= urlencode($csrf_token) ?>"
                               class="btn-action"
                               style="background: #0099cc; color: #fff;"
                               onclick="return confirm('Reset password jadi \"sija2026\"? Beritahu siswa untuk ganti password setelah login.')">
                               🔑 Reset PW
                            </a>

                            <!-- 🗑️ HAPUS PERMANEN (hanya untuk akun NONAKTIF) -->
                            <?php if ($is_nonaktif): ?>
                            <a href="?action=hapus_permanen&id=<?= $siswa['id']; ?>&csrf_token=<?= urlencode($csrf_token) ?>"
                               class="btn-action"
                               style="background: #cc0000; color: #fff;"
                               onclick="return confirm('⚠️ HAPUS PERMANEN akun ini? Hanya bisa dilakukan jika belum ada riwayat nilai/kuis. Data akan hilang selamanya!')">
                               🗑️ Hapus
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: #aaa;">Belum ada siswa yang mendaftar.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <a href="index.php" class="back-link">← Kembali ke Dashboard Materi</a>
</div>

</body>
</html>