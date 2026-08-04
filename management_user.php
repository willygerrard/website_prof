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

// --- AKSI BARU: EDIT NAMA ASLI (backfill siswa lama yang belum punya nama) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_nama') {
    csrf_require_valid_post();
    $id_target = (int)($_POST['id'] ?? 0);
    $nama_baru = trim($_POST['nama_asli'] ?? '');

    if ($id_target && $nama_baru !== '') {
        try {
            $stmt_nama = $pdo->prepare("UPDATE users SET nama_asli = ? WHERE id = ? AND role != 'admin'");
            $stmt_nama->execute([$nama_baru, $id_target]);
            $pesan = "<div style='color: #00ccff; margin-bottom: 15px;'>✅ Nama siswa diperbarui.</div>";
        } catch (PDOException $e) {
            $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $pesan = "<div style='color: #ff9933; margin-bottom: 15px;'>⚠️ Nama tidak boleh kosong.</div>";
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

// --- SEARCH & SORT ---
$search = trim($_GET['search'] ?? '');
$sort   = $_GET['sort'] ?? ''; // 'kelas_asc' atau 'kelas_desc'

// --- READ USER (TAMPILNO DAFTAR SISWA) ---
try {
    $sql = "SELECT id, username, nama_asli, kelas, role, no_wa_ortu, status, created_at FROM users WHERE role = 'siswa'";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (username LIKE ? OR nama_asli LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    if ($sort === 'kelas_asc') {
        $sql .= " ORDER BY kelas ASC, status ASC, id DESC";
    } elseif ($sort === 'kelas_desc') {
        $sql .= " ORDER BY kelas DESC, status ASC, id DESC";
    } else {
        $sql .= " ORDER BY status ASC, id DESC";
    }

    $stmt_read = $pdo->prepare($sql);
    $stmt_read->execute($params);
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
    <h2>Dashboard Admin: Management User</h2>
    <p>Halo <strong><?= htmlspecialchars($_SESSION['username']); ?></strong>, Ini daftar siswa yang terdaftar.</p>
    <p style="color: #888; font-size: 13px;">💡 Nonaktifkan siswa yang sudah lulus/keluar — riwayat nilai tetap tersimpan untuk arsip.</p>

    <?= $pesan; ?>

    <form method="GET" style="display: flex; gap: 8px; margin: 15px 0; align-items: center;">
        <?php if ($sort): ?>
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
        <?php endif; ?>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
               placeholder="Cari username atau nama siswa..."
               style="flex: 1; max-width: 300px; padding: 8px 12px; border-radius: 4px; border: 1px solid #333; background: #2a2a2a; color: #fff;">
        <button type="submit" class="btn-action" style="background: #00cc66; color: #1a1a1a; border: none; cursor: pointer;">
            🔍 Cari
        </button>
        <?php if ($search): ?>
        <a href="?<?= $sort ? 'sort=' . urlencode($sort) : '' ?>" class="btn-action" style="background: #444; color: #fff;">
            ✕ Reset
        </a>
        <?php endif; ?>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username Siswa</th>
                <th>
                    <a href="?<?= $search ? 'search=' . urlencode($search) . '&' : '' ?>sort=<?= $sort === 'kelas_asc' ? 'kelas_desc' : 'kelas_asc' ?>"
                       style="color: #00cc66; text-decoration: none;">
                        Kelas
                        <?php if ($sort === 'kelas_asc'): ?>▲<?php elseif ($sort === 'kelas_desc'): ?>▼<?php else: ?>⇅<?php endif; ?>
                    </a>
                </th>
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
                        <td>
                            <?= htmlspecialchars($siswa['username']); ?>
                            <?php if (!empty($siswa['nama_asli'])): ?>
                                <br><span style="color: #888; font-size: 12px;" id="nama-view-<?= $siswa['id'] ?>"><?= htmlspecialchars($siswa['nama_asli']) ?></span>
                            <?php else: ?>
                                <br><span class="wa-kosong" id="nama-view-<?= $siswa['id'] ?>">nama belum diisi</span>
                            <?php endif; ?>
                            <a href="javascript:void(0)" onclick="toggleEditNama(<?= $siswa['id'] ?>)" style="color: #0099cc; font-size: 11px; text-decoration: none;">✏️ edit</a>

                            <form method="POST" id="form-nama-<?= $siswa['id'] ?>" style="display:none; margin-top: 4px;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_nama">
                                <input type="hidden" name="id" value="<?= $siswa['id'] ?>">
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                                <input type="text" name="nama_asli" value="<?= htmlspecialchars($siswa['nama_asli'] ?? '') ?>"
                                       placeholder="Nama lengkap..." required
                                       style="width: 140px; padding: 4px 6px; font-size: 12px; border-radius: 4px; border: 1px solid #555; background: #222; color: #fff;">
                                <button type="submit" style="font-size: 11px; padding: 4px 8px; background: #00cc66; color: #1a1a1a; border: none; border-radius: 4px; cursor: pointer;">Simpan</button>
                            </form>
                        </td>
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
                                <a href="?action=aktifkan&id=<?= $siswa['id']; ?>&csrf_token=<?= urlencode($csrf_token) ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>"
                                   class="btn-action btn-aktif"
                                   onclick="return confirm('Aktifkan kembali akun ini?')">
                                   🔄 AKTIFKAN
                                </a>
                            <?php else: ?>
                                <a href="?action=nonaktifkan&id=<?= $siswa['id']; ?>&csrf_token=<?= urlencode($csrf_token) ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>"
                                   class="btn-action btn-nonaktif"
                                   onclick="return confirm('Nonaktifkan akun ini? Riwayat nilai tetap tersimpan, tapi siswa tidak bisa login lagi.')">
                                   🔴 NONAKTIFKAN
                                </a>
                            <?php endif; ?>

                            <!-- 🔑 RESET PASSWORD (muncul untuk semua status) -->
                            <a href="?action=reset_password&id=<?= $siswa['id']; ?>&csrf_token=<?= urlencode($csrf_token) ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>"
                               class="btn-action"
                               style="background: #0099cc; color: #fff;"
                               onclick="return confirm('Reset password jadi \"sija2026\"? Beritahu siswa untuk ganti password setelah login.')">
                               🔑 Reset PW
                            </a>

                            <!-- 🗑️ HAPUS PERMANEN (hanya untuk akun NONAKTIF) -->
                            <?php if ($is_nonaktif): ?>
                            <a href="?action=hapus_permanen&id=<?= $siswa['id']; ?>&csrf_token=<?= urlencode($csrf_token) ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>"
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
                    <td colspan="7" style="text-align: center; color: #aaa;">
                        <?= $search ? 'Tidak ada siswa dengan username/nama mengandung "' . htmlspecialchars($search) . '".' : 'Belum ada siswa yang mendaftar.' ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <a href="index.php" class="back-link">← Kembali ke Dashboard Materi</a>
    </div>
</div>

<script>
    function toggleEditNama(id) {
        const form = document.getElementById('form-nama-' + id);
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
</script>

</body>
</html>