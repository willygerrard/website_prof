<?php
/**
 * absensi_scan.php
 * Halaman siswa: konfirmasi kehadiran via scan QR absensi.
 *
 * Alur:
 *  - GET  ?token=...  : validasi format token (hex 64), cari sesi, tampilkan
 *    kartu konfirmasi (mapel, kelas, tanggal) + tombol "Konfirmasi Hadir".
 *    GET TIDAK menulis ke DB. (Info jumlah scan untuk guru: endpoint kecil di bawah.)
 *  - POST             : validasi berurutan CSRF → token → sesi buka →
 *    expired_at (waktu PHP) → kelas siswa → INSERT absensi (status='hadir').
 *    PDOException 1062 (duplikat) → "Kamu sudah absen di sesi ini".
 *
 * Semua query pakai prepared statement; waktu dihitung di PHP.
 */

date_default_timezone_set('Asia/Jakarta');

require_once 'session.php';      // session dulu
require_once 'csrf_helper.php';  // baru helper
require 'koneksi.php';

// ===== Endpoint kecil: jumlah scan (dipakai halaman guru untuk refresh angka) =====
// Hanya pembacaan agregat tanpa data pribadi; tetap butuh sesi login.
if (isset($_GET['info_jumlah'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
        echo json_encode(['ok' => false]); exit();
    }
    $sesi_id = (int)$_GET['info_jumlah'];
    if (!preg_match('/^\d+$/', (string)$sesi_id) || $sesi_id <= 0) {
        echo json_encode(['ok' => false]); exit();
    }
    $st = $pdo->prepare("SELECT COUNT(*) FROM absensi WHERE sesi_id = ?");
    $st->execute([$sesi_id]);
    echo json_encode(['ok' => true, 'jumlah' => (int)$st->fetchColumn()]);
    exit();
}

checkLogin();

$user_id  = (int)($_SESSION['user_id'] ?? 0);
$role     = (string)($_SESSION['role'] ?? '');
$token    = (string)($_GET['token'] ?? $_POST['token'] ?? '');

// Validasi format token: hex 64 karakter
$token_valid_format = (bool)preg_match('/^[0-9a-f]{64}$/', $token);

/**
 * Cari sesi berdasarkan token (prepared statement).
 */
function absensi_cari_sesi(PDO $pdo, string $token): ?array
{
    $st = $pdo->prepare("SELECT * FROM absensi_sesi WHERE token = ? LIMIT 1");
    $st->execute([$token]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Label mapel dari mapel_id (Opsi A: urutan kategori modules).
 */
function absensi_label_mapel(PDO $pdo, $mapel_id): string
{
    $rows = $pdo->query("SELECT DISTINCT category FROM modules ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
    $i = (int)$mapel_id - 1;
    return isset($rows[$i]) ? $rows[$i] : ('Mapel #' . (int)$mapel_id);
}

$hasil_post = null; // ['ok'=>bool, 'pesan'=>string, 'type'=>string]

// =========================
// PROSES POST (konfirmasi hadir)
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) CSRF
    csrf_require_valid_post();

    if (!$token_valid_format) {
        $hasil_post = ['ok' => false, 'pesan' => 'QR tidak valid (format token salah). Scan ulang QR terbaru.', 'type' => 'danger'];
    } else {
        try {
            // 2) Sesi ditemukan dari token
            $sesi = absensi_cari_sesi($pdo, $token);
            if (!$sesi) {
                $hasil_post = ['ok' => false, 'pesan' => 'QR tidak valid atau sudah dirotasi. Scan ulang QR terbaru di depan kelas.', 'type' => 'warning'];
            } elseif ($sesi['status'] !== 'buka') {
                // 3) Sesi harus buka
                $hasil_post = ['ok' => false, 'pesan' => 'Sesi absensi sudah ditutup.', 'type' => 'secondary'];
            } else {
                // 4) Expiry dihitung di PHP
                $now_ts = time();
                $expired_ts = strtotime($sesi['expired_at']);
                if ($expired_ts === false || $now_ts > $expired_ts) {
                    $hasil_post = ['ok' => false, 'pesan' => 'QR kadaluarsa. Minta guru menampilkan QR terbaru.', 'type' => 'warning'];
                } else {
                    // 5) Kelas siswa harus sama dengan kelas sesi
                    $st_u = $pdo->prepare("SELECT kelas, COALESCE(nama_asli, username) AS nama FROM users WHERE id = ? LIMIT 1");
                    $st_u->execute([$user_id]);
                    $u = $st_u->fetch(PDO::FETCH_ASSOC);
                    $kelas_siswa = trim((string)($u['kelas'] ?? ''));
                    if ($role !== 'siswa') {
                        $hasil_post = ['ok' => false, 'pesan' => 'Fitur ini khusus akun siswa.', 'type' => 'danger'];
                    } elseif ($kelas_siswa === '' || $kelas_siswa !== (string)$sesi['kelas']) {
                        $hasil_post = ['ok' => false, 'pesan' => 'Kamu bukan dari kelas ini (' . htmlspecialchars($sesi['kelas'], ENT_QUOTES, 'UTF-8') . ').', 'type' => 'danger'];
                    } else {
                        // 6) INSERT absensi
                        $now = date('Y-m-d H:i:s');
                        $ip  = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
                        $ins = $pdo->prepare("INSERT INTO absensi (sesi_id, user_id, nama, status, waktu_scan, ip_address, keterangan, created_at)
                                               VALUES (?, ?, ?, 'hadir', ?, ?, NULL, ?)");
                        $ins->execute([$sesi['id'], $user_id, mb_substr((string)$u['nama'], 0, 100), $now, $ip, $now]);
                        $hasil_post = ['ok' => true, 'pesan' => '✅ Kehadiran tercatat. Selamat belajar!', 'type' => 'success'];
                    }
                }
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' && (int)($e->errorInfo[1] ?? 0) === 1062) {
                $hasil_post = ['ok' => false, 'pesan' => 'Kamu sudah absen di sesi ini.', 'type' => 'info'];
            } else {
                error_log('absensi_scan gagal: ' . $e->getMessage());
                $hasil_post = ['ok' => false, 'pesan' => 'Terjadi kesalahan saat menyimpan absensi. Coba lagi.', 'type' => 'danger'];
            }
        }
    }
}

// =========================
// DATA UNTUK TAMPILAN (GET & POST)
// =========================
$sesi = ($token_valid_format) ? absensi_cari_sesi($pdo, $token) : null;
$now_ts = time();
$expired_ts = $sesi ? strtotime($sesi['expired_at']) : false;

$boleh_tampil_konfirmasi = ($sesi && $sesi['status'] === 'buka' && $expired_ts !== false && $now_ts <= $expired_ts);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi QR - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
        <a class="navbar-brand" href="index.php">Pusat Pembelajaran SIJA</a>
        <div class="d-flex gap-3 align-items-center">
            <?php if (isset($_SESSION['username'])) : ?>
                <span class="text-secondary fw-medium d-none d-md-inline small">
                    👋 Hai, <strong class="text-dark"><?= htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?></strong>
                </span>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container py-4" style="max-width: 520px;">

    <?php if (empty($_SESSION['is_login']) || $_SESSION['is_login'] !== true): ?>
        <div class="alert alert-warning">Login dulu, lalu scan ulang QR.</div>
        <a class="btn btn-primary w-100" href="login.php">Ke Halaman Login</a>
    <?php else: ?>

        <?php if ($hasil_post): ?>
            <div class="alert alert-<?= htmlspecialchars($hasil_post['type'], ENT_QUOTES, 'UTF-8') ?> fs-5 text-center">
                <?= htmlspecialchars($hasil_post['pesan'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (!$token_valid_format): ?>
            <div class="alert alert-danger text-center">QR tidak valid. Scan ulang QR yang ditampilkan guru.</div>
        <?php elseif (!$sesi): ?>
            <div class="alert alert-warning text-center">QR tidak dikenal atau sudah dirotasi. Scan ulang QR terbaru.</div>
        <?php else: ?>

            <div class="card shadow-sm border-0 rounded-3 mb-3">
                <div class="card-body text-center p-4">
                    <div class="fw-bold fs-5 mb-1"><?= htmlspecialchars(absensi_label_mapel($pdo, $sesi['mapel_id']), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="text-muted">Kelas <?= htmlspecialchars($sesi['kelas'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($sesi['tanggal'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="mt-2">
                        <?php if ($sesi['status'] !== 'buka'): ?>
                            <span class="badge bg-secondary">Sesi ditutup</span>
                        <?php elseif ($now_ts > $expired_ts): ?>
                            <span class="badge bg-warning text-dark">QR kadaluarsa</span>
                        <?php else: ?>
                            <span class="badge bg-success">QR aktif</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($boleh_tampil_konfirmasi): ?>
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-body p-4 text-center">
                        <p class="mb-4">Pastikan data di atas benar, lalu konfirmasi kehadiranmu:</p>
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn btn-success btn-lg w-100 fw-bold">
                                ✋ Konfirmasi Hadir
                            </button>
                        </form>
                    </div>
                </div>
            <?php elseif ($sesi['status'] === 'tutup'): ?>
                <div class="alert alert-secondary text-center">Sesi absensi sudah ditutup.</div>
            <?php else: ?>
                <div class="alert alert-warning text-center">QR kadaluarsa. Minta guru menampilkan QR terbaru.</div>
            <?php endif; ?>

        <?php endif; ?>
    <?php endif; ?>

    <div class="text-center mt-4">
        <a href="index.php" class="text-muted small text-decoration-none">← Kembali ke Beranda</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
