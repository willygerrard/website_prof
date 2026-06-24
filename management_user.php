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

// --- AKSI 1: PROSES DELETE USER (PROSES HAPUS) ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id_hapus = $_GET['id'];

    try {
        $stmt_delete = $pdo->prepare("DELETE FROM users WHERE id = :id AND role != 'admin'");
        $stmt_delete->execute(['id' => $id_hapus]);
        
        if ($stmt_delete->rowCount() > 0) {
            $pesan = "<div style='color: #00ff66; margin-bottom: 15px;'>Sukses! Akun siswa sudah dihapus dari sistem.</div>";
        } else {
            $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Gagal! Akun tidak ditemukan.</div>";
        }
    } catch (PDOException $e) {
        $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Error: " . $e->getMessage() . "</div>";
    }
}

// --- AKSI 2: PROSES READ USER (TAMPILNO DAFTAR SISWA) ---
try {
    // Mung nampilno user sing role-ne siswa wae, dadi luwih rapi
    $stmt_read = $pdo->query("SELECT id, username, role, no_wa_ortu, created_at FROM users WHERE role = 'siswa' ORDER BY id DESC");
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
        .container { max-width: 800px; margin: auto; background: #1e1e1e; padding: 20px; border-radius: 8px; border: 1px solid #333; }
        h2 { color: #00cc66; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #333; }
        th { background: #2a2a2a; color: #00cc66; }
        tr:hover { background: #252525; }
        .btn-delete { background: #ff3333; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 13px; }
        .btn-delete:hover { background: #cc0000; }
        .back-link { margin-top: 20px; display: block; color: #aaa; text-decoration: none; font-size: 14px; }
        .back-link:hover { color: #fff; }
        .wa-kosong { color: #ff9933; font-style: italic; font-size: 13px; }

        /* Tambahno kelas iki nggo nahan tabel ben gak offside */
.table-responsive {
    width: 100%;
    overflow-x: auto; /* Otomatis nggawe scroll horizontal mung ing area tabel wae */
    -webkit-overflow-scrolling: touch; /* Biar scroll-e lancar lan empuk ing HP Android */
    margin-top: 15px;
}

/* Pastikno container-mu duwe box-sizing ben aman soko padding overflow */
.container {
    max-width: 800px;
    margin: auto;
    background: #1e1e1e;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #333;
    box-sizing: border-box; /* Nahan lebar kothak tetep presisi */
}
    </style>
</head>
<body>

<div class="table-responsive">
    <h2>Dashboard Admin: Management User</h2>
    <p>Halo <strong><?= htmlspecialchars($_SESSION['username']); ?></strong>, Ini daftar siswa yang terdaftar.</p>
    
    <?= $pesan; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username Siswa</th>
                <th>Role</th>
                <th>No. WA Ortu</th>
                <th>Tanggal Registrasi</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($daftar_siswa) > 0): ?>
                <?php foreach ($daftar_siswa as $siswa): ?>
                    <tr>
                        <td><?= $siswa['id']; ?></td>
                        <td><?= htmlspecialchars($siswa['username']); ?></td>
                        <td><span style="color: #00cc66; background: #003311; padding: 2px 6px; border-radius: 4px; font-size: 12px;"><?= $siswa['role']; ?></span></td>
                        <td>
                            <?php if (!empty($siswa['no_wa_ortu'])): ?>
                                <?= htmlspecialchars($siswa['no_wa_ortu']); ?>
                            <?php else: ?>
                                <span class="wa-kosong">belum diisi</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $siswa['created_at']; ?></td>
                        <td>
                            <a href="?action=delete&id=<?= $siswa['id']; ?>" 
                               class="btn-delete" 
                               onclick="return confirm('Yakin hapus user?')">
                               DEL
                            </a>
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