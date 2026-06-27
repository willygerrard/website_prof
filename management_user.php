<?php
session_start();
require 'koneksi.php'; // Nggae $pdo sing wingi

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

// --- AKSI: NONAKTIFKAN USER (SOFT DELETE) ---
if (isset($_GET['action']) && $_GET['action'] === 'nonaktifkan' && isset($_GET['id'])) {
    $id_target = $_GET['id'];

    try {
        $stmt_update = $pdo->prepare("UPDATE users SET status = 'nonaktif' WHERE id = :id AND role != 'admin'");
        $stmt_update->execute(['id' => $id_target]);

        if ($stmt_update->rowCount() > 0) {
            $pesan = "<div style='color: #ffaa00; margin-bottom: 15px;'>Akun siswa dinonaktifkan. Riwayat nilai tetap tersimpan, siswa tidak bisa login lagi.</div>";
        } else {
            $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Gagal! Akun tidak ditemukan.</div>";
        }
    } catch (PDOException $e) {
        $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// --- AKSI: AKTIFKAN ULANG USER ---
if (isset($_GET['action']) && $_GET['action'] === 'aktifkan' && isset($_GET['id'])) {
    $id_target = $_GET['id'];

    try {
        $stmt_update = $pdo->prepare("UPDATE users SET status = 'aktif' WHERE id = :id AND role != 'admin'");
        $stmt_update->execute(['id' => $id_target]);
        $pesan = "<div style='color: #00ff66; margin-bottom: 15px;'>Akun siswa diaktifkan kembali.</div>";
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
                        <td>
                            <?php if ($is_nonaktif): ?>
                                <a href="?action=aktifkan&id=<?= $siswa['id']; ?>"
                                   class="btn-action btn-aktif"
                                   onclick="return confirm('Aktifkan kembali akun ini?')">
                                   AKTIFKAN
                                </a>
                            <?php else: ?>
                                <a href="?action=nonaktifkan&id=<?= $siswa['id']; ?>"
                                   class="btn-action btn-nonaktif"
                                   onclick="return confirm('Nonaktifkan akun ini? Riwayat nilai tetap tersimpan, tapi siswa tidak bisa login lagi.')">
                                   NONAKTIFKAN
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