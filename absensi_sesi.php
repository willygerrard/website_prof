<?php
/**
 * absensi_sesi.php
 * Halaman guru/admin: kelola sesi absensi QR.
 *
 * Akses: admin (guard existing checkRole(['admin'])).
 * Guru hanya melihat dan mengelola sesi miliknya (guru_id = session user_id).
 *
 * Fitur:
 *  - Form buat sesi (POST + CSRF): mapel (kategori modul), kelas, tanggal,
 *    jam mulai, jam selesai.
 *  - Tampilkan QR (library lokal assets/vendor/qrcode.min.js), countdown,
 *    jumlah siswa yang sudah scan (auto refresh), tombol Tutup Sesi.
 *  - Endpoint JSON rotasi token (POST + CSRF) tiap 55 detik dari JS.
 *  - DataTables daftar kehadiran + form input absensi kertas.
 *
 * Waktu dihitung di PHP (bukan NOW() SQL). Semua query pakai prepared statement.
 */

date_default_timezone_set('Asia/Jakarta');

require_once 'session.php';      // session dulu
require_once 'csrf_helper.php';  // baru helper
require 'koneksi.php';

checkLogin();
checkRole(['admin']);

$user_id  = (int)($_SESSION['user_id'] ?? 0);
$username = (string)($_SESSION['username'] ?? '');

$pesan = '';
$pesan_type = '';

/**
 * Kelas yang boleh dipilih (dari data siswa existing).
 */
function absensi_daftar_kelas(PDO $pdo): array
{
    $rows = $pdo->query("SELECT DISTINCT kelas FROM users WHERE role = 'siswa' AND kelas IS NOT NULL AND status = 'aktif' AND kelas <> '' ORDER BY kelas")->fetchAll(PDO::FETCH_COLUMN);
    return $rows;
}

/**
 * Daftar mapel = kategori modul existing (keputusan user: Opsi A, tidak ada tabel mapel).
 */
function absensi_daftar_mapel(PDO $pdo): array
{
    return $pdo->query("SELECT DISTINCT category FROM modules ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
}

// ================================
// AKSI POST (semua wajib CSRF)
// ================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid_post();

    // --- Aksi 1: buat sesi baru ---
    if (isset($_POST['buat_sesi'])) {
        $mapel      = trim($_POST['mapel'] ?? '');
        $kelas      = trim($_POST['kelas'] ?? '');
        $tanggal    = trim($_POST['tanggal'] ?? '');
        $jam_mulai  = trim($_POST['jam_mulai'] ?? '');
        $jam_selesai = trim($_POST['jam_selesai'] ?? '');

        $kelas_boleh  = absensi_daftar_kelas($pdo);
        $mapel_boleh  = absensi_daftar_mapel($pdo);

        if ($mapel === '' || $kelas === '' || !in_array($kelas, $kelas_boleh, true) || !in_array($mapel, $mapel_boleh, true)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)
            || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $jam_mulai)
            || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $jam_selesai)) {
            $pesan = '⚠️ Data sesi tidak lengkap atau tidak valid.';
            $pesan_type = 'danger';
        } else {
            // Normalisasi jam ke HH:MM:SS
            $jam_mulai = substr($jam_mulai . ':00', 0, 8);
            $jam_selesai = substr($jam_selesai . ':00', 0, 8);

            // mapel_id: id kategori semu = nomor urut kategori pada daftar modules
            // (Opsi A — tidak ada tabel mapel; tanpa FK ke tabel mana pun).
            $idx = array_search($mapel, $mapel_boleh, true);
            $mapel_id = ($idx !== false) ? ((int)$idx + 1) : 0;

            $token = bin2hex(random_bytes(32));
            $now   = date('Y-m-d H:i:s');
            $expired = date('Y-m-d H:i:s', time() + 60);

            try {
                $stmt = $pdo->prepare("INSERT INTO absensi_sesi (mapel_id, guru_id, kelas, tanggal, jam_mulai, jam_selesai, token, expired_at, status, created_at)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'buka', ?)");
                $stmt->execute([$mapel_id, $user_id, $kelas, $tanggal, $jam_mulai, $jam_selesai, $token, $expired, $now]);
                $sesi_id_baru = (int)$pdo->lastInsertId();
                header('Location: absensi_sesi.php?sesi=' . $sesi_id_baru);
                exit();
            } catch (PDOException $e) {
                error_log('absensi_sesi buat sesi gagal: ' . $e->getMessage());
                $pesan = '❌ Gagal membuat sesi. Coba lagi.';
                $pesan_type = 'danger';
            }
        }
    }

    // --- Aksi 2: tutup sesi ---
    if (isset($_POST['tutup_sesi'])) {
        $sesi_id = (int)($_POST['sesi_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE absensi_sesi SET status = 'tutup' WHERE id = ? AND guru_id = ? AND status = 'buka'");
        $stmt->execute([$sesi_id, $user_id]);
        if ($stmt->rowCount() > 0) {
            $pesan = '🔴 Sesi ditutup. QR tidak berlaku lagi.';
            $pesan_type = 'secondary';
        } else {
            $pesan = '⚠️ Sesi tidak ditemukan / bukan milik Anda / sudah tutup.';
            $pesan_type = 'danger';
        }
    }

    // --- Aksi 3: rotasi token (fetch JSON dari JS) ---
    if (isset($_POST['aksi_rotasi'])) {
        header('Content-Type: application/json; charset=utf-8');
        $sesi_id = (int)($_POST['sesi_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id FROM absensi_sesi WHERE id = ? AND guru_id = ? AND status = 'buka'");
        $stmt->execute([$sesi_id, $user_id]);
        if (!$stmt->fetchColumn()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'forbidden']);
            exit();
        }
        $token = bin2hex(random_bytes(32));
        $expired = date('Y-m-d H:i:s', time() + 60);
        $upd = $pdo->prepare("UPDATE absensi_sesi SET token = ?, expired_at = ? WHERE id = ? AND status = 'buka'");
        $upd->execute([$token, $expired, $sesi_id]);
        echo json_encode(['ok' => true, 'token' => $token, 'expired_at' => $expired]);
        exit();
    }

    // --- Aksi 4: simpan absensi kertas (status + keterangan per siswa) ---
    if (isset($_POST['simpan_kertas'])) {
        $sesi_id = (int)($_POST['sesi_id'] ?? 0);
        $st_sesi = $pdo->prepare("SELECT kelas, tanggal FROM absensi_sesi WHERE id = ? AND guru_id = ?");
        $st_sesi->execute([$sesi_id, $user_id]);
        $sesi = $st_sesi->fetch(PDO::FETCH_ASSOC);
        if (!$sesi) {
            $pesan = '⚠️ Sesi tidak ditemukan / bukan milik Anda.';
            $pesan_type = 'danger';
        } else {
            $status_boleh = ['hadir', 'sakit', 'izin', 'alpa', 'terlambat'];
            $now = date('Y-m-d H:i:s');
            $st_siswa = $pdo->prepare("SELECT id, COALESCE(nama_asli, username) AS nama FROM users WHERE role = 'siswa' AND status = 'aktif' AND kelas = ?");
            $st_siswa->execute([$sesi['kelas']]);
            $daftar = $st_siswa->fetchAll(PDO::FETCH_ASSOC);

            $st_cek = $pdo->prepare("SELECT id FROM absensi WHERE sesi_id = ? AND user_id = ?");
            $st_ins = $pdo->prepare("INSERT INTO absensi (sesi_id, user_id, nama, status, waktu_scan, ip_address, keterangan, created_at)
                                      VALUES (?, ?, ?, ?, NULL, NULL, ?, ?)");
            $st_upd = $pdo->prepare("UPDATE absensi SET status = ?, keterangan = ? WHERE id = ?");

            $jumlah = 0;
            foreach ($daftar as $s) {
                $inp_status = $_POST['status_' . $s['id']] ?? '';
                if (!in_array($inp_status, $status_boleh, true)) {
                    continue; // tidak dipilih → tidak diubah/diinsert
                }
                $inp_ket = mb_substr(trim((string)($_POST['ket_' . $s['id']] ?? '')), 0, 255);

                $st_cek->execute([$sesi_id, $s['id']]);
                $baris = $st_cek->fetchColumn();
                if ($baris) {
                    // Guru hanya boleh UPDATE status & keterangan — waktu_scan TIDAK disentuh.
                    $st_upd->execute([$inp_status, $inp_ket, $baris]);
                } else {
                    // Siswa belum punya baris: INSERT dengan waktu_scan = NULL.
                    $st_ins->execute([$sesi_id, $s['id'], mb_substr((string)$s['nama'], 0, 100), $inp_status, $inp_ket, $now]);
                }
                $jumlah++;
            }
            $pesan = "✅ Absensi kertas tersimpan untuk {$jumlah} siswa.";
            $pesan_type = 'success';
        }
    }
}

// ================================
// DATA UNTUK TAMPILAN
// ================================
$daftar_kelas = absensi_daftar_kelas($pdo);
$daftar_mapel = absensi_daftar_mapel($pdo);

// Daftar sesi milik guru yang login (terbaru dulu)
$st_list = $pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM absensi a WHERE a.sesi_id = s.id) AS jumlah_scan
                           FROM absensi_sesi s WHERE s.guru_id = ? ORDER BY s.id DESC LIMIT 100");
$st_list->execute([$user_id]);
$daftar_sesi = $st_list->fetchAll(PDO::FETCH_ASSOC);

// Sesi aktif yang sedang dilihat (milik guru saja)
$sesi_aktif = null;
$sesi_id_view = (int)($_GET['sesi'] ?? 0);
if ($sesi_id_view > 0) {
    foreach ($daftar_sesi as $s) {
        if ((int)$s['id'] === $sesi_id_view) { $sesi_aktif = $s; break; }
    }
}

// Label mapel dari mapel_id (urutan kategori modules)
function absensi_label_mapel(array $daftar_mapel, $mapel_id): string
{
    $i = (int)$mapel_id - 1;
    return isset($daftar_mapel[$i]) ? $daftar_mapel[$i] : ('Mapel #' . (int)$mapel_id);
}

// Base URL untuk isi QR (tidak ada konstanta base URL existing → bangun dari HTTP_HOST)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

// Daftar kehadiran sesi aktif (untuk DataTables + form kertas)
$daftar_hadir = [];
$daftar_siswa_kelas = [];
if ($sesi_aktif) {
    $st_h = $pdo->prepare("SELECT a.*, COALESCE(NULLIF(a.nama, ''), u.nama_asli, u.username) AS nama_tampil
                            FROM absensi a LEFT JOIN users u ON u.id = a.user_id
                            WHERE a.sesi_id = ? ORDER BY nama_tampil");
    $st_h->execute([$sesi_aktif['id']]);
    $daftar_hadir = $st_h->fetchAll(PDO::FETCH_ASSOC);

    $st_sw = $pdo->prepare("SELECT id, COALESCE(nama_asli, username) AS nama FROM users WHERE role = 'siswa' AND status = 'aktif' AND kelas = ? ORDER BY nama");
    $st_sw->execute([$sesi_aktif['kelas']]);
    $daftar_siswa_kelas = $st_sw->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi QR - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/datatables/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        #qrBox { min-height: 260px; display: flex; align-items: center; justify-content: center; }
        #qrBox img { image-rendering: pixelated; }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/admin_header.php'; ?>

    <div class="container mt-4 mb-5">

        <?php if ($pesan): ?>
        <div class="alert alert-<?= htmlspecialchars($pesan_type, ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- KARTU: Buat Sesi Baru -->
        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="card-title mb-0 fw-bold">➕ Buat Sesi Absensi QR</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-md-4">
                        <label for="mapel" class="form-label fw-semibold">Mapel (Kategori)</label>
                        <select class="form-select" id="mapel" name="mapel" required>
                            <option value="" selected disabled>-- Pilih Mapel --</option>
                            <?php foreach ($daftar_mapel as $m): ?>
                                <option value="<?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="kelas" class="form-label fw-semibold">Kelas</label>
                        <select class="form-select" id="kelas" name="kelas" required>
                            <option value="" selected disabled>-- Pilih Kelas --</option>
                            <?php foreach ($daftar_kelas as $k): ?>
                                <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="tanggal" class="form-label fw-semibold">Tanggal</label>
                        <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-1">
                        <label for="jam_mulai" class="form-label fw-semibold">Mulai</label>
                        <input type="time" class="form-control" id="jam_mulai" name="jam_mulai" required>
                    </div>
                    <div class="col-md-2">
                        <label for="jam_selesai" class="form-label fw-semibold">Selesai</label>
                        <input type="time" class="form-control" id="jam_selesai" name="jam_selesai" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="buat_sesi" value="1" class="btn btn-primary fw-bold">
                            <i class="bi bi-plus-circle"></i> Buat Sesi
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($sesi_aktif): ?>
        <!-- KARTU: QR Sesi Aktif -->
        <div class="card shadow-sm border-0 rounded-3 mb-4" id="kartuQR"
             data-sesi-id="<?= (int)$sesi_aktif['id'] ?>"
             data-token="<?= htmlspecialchars($sesi_aktif['token'], ENT_QUOTES, 'UTF-8') ?>"
             data-status="<?= htmlspecialchars($sesi_aktif['status'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="card-header bg-success text-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold">
                    📷 QR Absensi — <?= htmlspecialchars(absensi_label_mapel($daftar_mapel, $sesi_aktif['mapel_id']), ENT_QUOTES, 'UTF-8') ?>
                    / <?= htmlspecialchars($sesi_aktif['kelas'], ENT_QUOTES, 'UTF-8') ?>
                    / <?= htmlspecialchars($sesi_aktif['tanggal'], ENT_QUOTES, 'UTF-8') ?>
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <a href="index.php" class="btn btn-outline-light btn-sm">Beranda</a>
                    <a href="laporan_silang.php" class="btn btn-outline-primary btn-sm">📊 Laporan Silang</a>
                    <span class="badge fs-6 px-3 py-2 <?= $sesi_aktif['status'] === 'buka' ? 'bg-light text-success' : 'bg-dark' ?>" id="badgeStatus">
                        <?= $sesi_aktif['status'] === 'buka' ? '🟢 BUKA' : '🔴 TUTUP' ?>
                    </span>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-4 align-items-center">
                    <div class="col-md-5 text-center">
                        <div id="qrBox" class="border rounded-3 bg-white p-3">
                            <?php if ($sesi_aktif['status'] === 'buka'): ?>
                                <div class="text-muted small" id="qrPlaceholder">Memuat QR…</div>
                            <?php else: ?>
                                <div class="text-muted small">Sesi sudah ditutup — QR tidak aktif.</div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-3 fw-bold fs-4" id="countdown">--</div>
                        <div class="text-muted small">Token otomatis dirotasi tiap 55 detik</div>
                    </div>
                    <div class="col-md-7">
                        <div class="card bg-light border-0 rounded-3 mb-3">
                            <div class="card-body text-center py-3">
                                <div class="fs-2 fw-bold" id="jumlahScan"><?= (int)$sesi_aktif['jumlah_scan'] ?></div>
                                <div class="text-muted small">siswa sudah scan</div>
                            </div>
                        </div>
                        <form method="POST" onsubmit="return confirm('Tutup sesi ini? QR tidak akan berlaku lagi.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="sesi_id" value="<?= (int)$sesi_aktif['id'] ?>">
                            <?php if ($sesi_aktif['status'] === 'buka'): ?>
                                <button type="submit" name="tutup_sesi" value="1" class="btn btn-danger w-100 fw-bold py-2">
                                    <i class="bi bi-stop-circle"></i> Tutup Sesi
                                </button>
                            <?php endif; ?>
                        </form>
                        <div class="text-muted small mt-3">
                            Token aktif: <code id="tokenLabel"><?= htmlspecialchars(substr($sesi_aktif['token'], 0, 12) . '…', ENT_QUOTES, 'UTF-8') ?></code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($sesi_aktif): ?>
        <!-- KARTU: Daftar Kehadiran + Absensi Kertas -->
        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-header bg-dark text-white py-3">
                <h5 class="card-title mb-0 fw-bold">📋 Daftar Kehadiran &amp; Input Absensi Kertas</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="sesi_id" value="<?= (int)$sesi_aktif['id'] ?>">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle" id="tabelHadir">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Scan LMS</th>
                                    <th>Status (kertas)</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($daftar_siswa_kelas as $sw):
                                    $baris = null;
                                    foreach ($daftar_hadir as $h) {
                                        if ((int)$h['user_id'] === (int)$sw['id']) { $baris = $h; break; }
                                    }
                                ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($sw['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if ($baris && !empty($baris['waktu_scan'])): ?>
                                            <span class="badge bg-success">📱 <?= htmlspecialchars($baris['waktu_scan'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Belum scan</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php $st_now = $baris['status'] ?? ''; ?>
                                        <select class="form-select form-select-sm" name="status_<?= (int)$sw['id'] ?>" style="max-width:150px;">
                                            <option value="">— pilih —</option>
                                            <?php foreach (['hadir','sakit','izin','alpa','terlambat'] as $opt): ?>
                                                <option value="<?= $opt ?>" <?= $st_now === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if ($st_now !== ''): ?>
                                            <div class="small text-muted">tersimpan: <?= htmlspecialchars($st_now, ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" name="ket_<?= (int)$sw['id'] ?>"
                                               maxlength="255" value="<?= htmlspecialchars($baris['keterangan'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="max-width:280px;">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" name="simpan_kertas" value="1" class="btn btn-primary fw-bold">
                        <i class="bi bi-save"></i> Simpan Absensi Kertas
                    </button>
                    <div class="form-text text-muted small mt-2">
                        Status kertas mengubah kolom <code>status</code>/<code>keterangan</code> saja — <code>waktu_scan</code> tidak pernah diubah.
                        Siswa yang belum punya baris akan dibuat dengan <code>waktu_scan</code> kosong.
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- KARTU: Daftar Semua Sesi -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-secondary text-white py-3">
                <h5 class="card-title mb-0 fw-bold">🗓️ Semua Sesi Saya</h5>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="tabelSesi">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Mapel</th>
                                <th>Kelas</th>
                                <th>Tanggal</th>
                                <th>Jam</th>
                                <th>Status</th>
                                <th>Sudah Scan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daftar_sesi as $s): ?>
                            <tr>
                                <td><?= (int)$s['id'] ?></td>
                                <td><?= htmlspecialchars(absensi_label_mapel($daftar_mapel, $s['mapel_id']), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($s['kelas'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($s['tanggal'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(substr($s['jam_mulai'], 0, 5), ENT_QUOTES, 'UTF-8') ?>–<?= htmlspecialchars(substr($s['jam_selesai'], 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="badge <?= $s['status'] === 'buka' ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $s['status'] === 'buka' ? 'BUKA' : 'TUTUP' ?>
                                    </span>
                                </td>
                                <td><span class="badge bg-primary"><?= (int)$s['jumlah_scan'] ?></span></td>
                                <td><a class="btn btn-sm btn-outline-primary" href="absensi_sesi.php?sesi=<?= (int)$s['id'] ?>">Kelola</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/vendor/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
    <script src="assets/vendor/datatables/js/dataTables.bootstrap5.min.js"></script>
    <script src="assets/vendor/qrcode.min.js"></script>
    <script>
    (function () {
        'use strict';

        $('#tabelSesi').DataTable();
        $('#tabelHadir').DataTable({
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: [2, 3] }]
        });

        var kartu = document.getElementById('kartuQR');
        if (!kartu) return;

        var sesiId   = kartu.dataset.sesiId;
        var status   = kartu.dataset.status;
        var token    = kartu.dataset.token;
        var csrf     = document.querySelector('#kartuQR') ? (document.querySelector('input[name="csrf_token"]') ? document.querySelector('input[name="csrf_token"]').value : '') : '';
        // Ambil CSRF token dari form Tutup Sesi (paling dekat dengan kartu QR)
        var csrfInput = kartu.parentElement.querySelector('input[name="csrf_token"]')
            || document.querySelector('input[name="csrf_token"]');
        if (csrfInput) csrf = csrfInput.value;

        var qrBox = document.getElementById('qrBox');
        var countdownEl = document.getElementById('countdown');
        var jumlahScanEl = document.getElementById('jumlahScan');
        var tokenLabel = document.getElementById('tokenLabel');
        var expiredAtMs = 0;
        var qr = null;

        function renderQR(t) {
            qrBox.innerHTML = '';
            qr = new QRCode(qrBox, {
                text: t,
                width: 240,
                height: 240,
                correctLevel: QRCode.CorrectLevel.M
            });
            tokenLabel.textContent = t.substring(0, 12) + '…';
        }

        function tickCountdown() {
            if (!expiredAtMs) return;
            var sisa = Math.max(0, Math.floor((expiredAtMs - Date.now()) / 1000));
            countdownEl.textContent = sisa > 0 ? ('⏱ ' + sisa + ' detik') : '⏱ kadaluarsa…';
            countdownEl.className = 'mt-3 fw-bold fs-4 ' + (sisa > 10 ? 'text-success' : 'text-danger');
        }
        setInterval(tickCountdown, 500);

        function refreshJumlahScan() {
            fetch('absensi_scan.php?info_jumlah=' + encodeURIComponent(sesiId), { headers: { 'X-Requested-With': 'fetch' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.ok) jumlahScanEl.textContent = d.jumlah;
                })
                .catch(function () {});
        }
        setInterval(refreshJumlahScan, 10000);
        refreshJumlahScan();

        function rotasiToken() {
            if (status !== 'buka') return;
            var body = new URLSearchParams();
            body.append('aksi_rotasi', '1');
            body.append('sesi_id', sesiId);
            body.append('csrf_token', csrf);
            fetch('absensi_sesi.php', { method: 'POST', body: body })
                .then(function (r) {
                    if (!r.ok) throw new Error('http ' + r.status);
                    return r.json();
                })
                .then(function (d) {
                    if (d && d.ok) {
                        token = d.token;
                        var parts = d.expired_at.split(/[- :]/);
                        expiredAtMs = new Date(parts[0], parts[1] - 1, parts[2], parts[3], parts[4], parts[5]).getTime();
                        renderQR(location.origin + location.pathname.replace(/absensi_sesi\.php.*$/, '') + 'absensi_scan.php?token=' + encodeURIComponent(token));
                        tickCountdown();
                    }
                })
                .catch(function (e) { console.error('Rotasi token gagal:', e); });
        }

        if (status === 'buka') {
            // QR pertama dari token yang dimuat server, expired_at dihitung ulang via rotasi pertama
            expiredAtMs = Date.now() + 60000;
            renderQR(location.origin + location.pathname.replace(/absensi_sesi\.php.*$/, '') + 'absensi_scan.php?token=' + encodeURIComponent(token));
            tickCountdown();
            setInterval(rotasiToken, 55000);
        }
    })();
    </script>
</body>
</html>
