<?php
session_start();
require 'koneksi.php';
require 'csrf_helper.php';

// GUARD 1: Cek sudah login & role-nya ADMIN
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (strpos($_SERVER['REQUEST_URI'], 'pintu-belakang-sija') === false) {
    header("HTTP/1.1 404 Not Found");
    exit();
}

$pesan = "";

// Validasi CSRF token untuk aksi via GET
function validate_csrf_get() {
    $token = $_GET['csrf_token'] ?? '';
    if (!csrf_verify($token)) {
        die("❌ Token CSRF tidak valid. Silakan kembali ke halaman Management User.");
    }
}

// Helper: bangun query-string untuk link aksi (preserve filter)
function action_qs($extra = []) {
    global $search, $sort, $kelas_filter, $status_filter, $csrf_token;
    $qs = $extra;
    // Hanya tambahkan nilai global jika key-nya TIDAK ada di $extra
    if (!array_key_exists('search', $qs) && $search)         $qs['search'] = $search;
    if (!array_key_exists('sort',   $qs) && $sort)           $qs['sort']   = $sort;
    if (!array_key_exists('kelas',  $qs) && $kelas_filter)   $qs['kelas']  = $kelas_filter;
    if (!array_key_exists('status', $qs) && $status_filter)  $qs['status'] = $status_filter;
    if (!array_key_exists('csrf_token', $qs) && $csrf_token) $qs['csrf_token'] = $csrf_token;
    // Buang nilai kosong biar URL rapi
    $qs = array_filter($qs, fn($v) => $v !== '' && $v !== null);
    return http_build_query($qs);
}

// Helper: bangun query-string untuk link filter (preserve filter lain)
function filter_qs($extra = []) {
    global $search, $sort, $kelas_filter, $status_filter;
    $qs = $extra;
    // Hanya tambahkan nilai global jika key-nya TIDAK ada di $extra
    if (!array_key_exists('search', $qs) && $search)        $qs['search'] = $search;
    if (!array_key_exists('sort',   $qs) && $sort)          $qs['sort']   = $sort;
    if (!array_key_exists('kelas',  $qs) && $kelas_filter)  $qs['kelas']  = $kelas_filter;
    if (!array_key_exists('status', $qs) && $status_filter) $qs['status'] = $status_filter;
    // Buang nilai kosong biar URL rapi
    $qs = array_filter($qs, fn($v) => $v !== '' && $v !== null);
    return http_build_query($qs);
}

// --- AKSI: NONAKTIFKAN USER (SOFT DELETE) ---
if (isset($_GET['action']) && $_GET['action'] === 'nonaktifkan' && isset($_GET['id'])) {
    validate_csrf_get();
    $id_target = (int)$_GET['id'];
    try {
        $stmt_update = $pdo->prepare("UPDATE users SET status = 'nonaktif' WHERE id = :id AND role != 'admin'");
        $stmt_update->execute(['id' => $id_target]);
        if ($stmt_update->rowCount() > 0) {
            $pesan = "<div class='alert alert-warning'>🟠 Akun siswa dinonaktifkan. Riwayat nilai tetap tersimpan, siswa tidak bisa login lagi.</div>";
        } else {
            $pesan = "<div class='alert alert-danger'>❌ Gagal! Akun tidak ditemukan.</div>";
        }
    } catch (PDOException $e) {
        error_log('DB Error [nonaktifkan user]: ' . $e->getMessage());
        $pesan = "<div class='alert alert-danger'>Terjadi kesalahan pada sistem. Silakan hubungi administrator.</div>";
    }
}

// --- AKSI: AKTIFKAN ULANG USER ---
if (isset($_GET['action']) && $_GET['action'] === 'aktifkan' && isset($_GET['id'])) {
    validate_csrf_get();
    $id_target = (int)$_GET['id'];
    try {
        $stmt_update = $pdo->prepare("UPDATE users SET status = 'aktif' WHERE id = :id AND role != 'admin'");
        $stmt_update->execute(['id' => $id_target]);
        $pesan = "<div class='alert alert-success'>✅ Akun siswa diaktifkan kembali.</div>";
    } catch (PDOException $e) {
        error_log('DB Error [aktifkan user]: ' . $e->getMessage());
        $pesan = "<div class='alert alert-danger'>Terjadi kesalahan pada sistem. Silakan hubungi administrator.</div>";
    }
}

// --- AKSI: HAPUS PERMANEN (hanya jika belum punya riwayat nilai) ---
if (isset($_GET['action']) && $_GET['action'] === 'hapus_permanen' && isset($_GET['id'])) {
    validate_csrf_get();
    $id_target = (int)$_GET['id'];
    try {
        $stmt_cek_kuis = $pdo->prepare("SELECT COUNT(*) FROM kuis_hasil WHERE user_id = ?");
        $stmt_cek_kuis->execute([$id_target]);
        $ada_kuis = (int)$stmt_cek_kuis->fetchColumn() > 0;

        $stmt_cek_checkpoint = $pdo->prepare("SELECT COUNT(*) FROM checkpoint_hasil WHERE user_id = ?");
        $stmt_cek_checkpoint->execute([$id_target]);
        $ada_checkpoint = (int)$stmt_cek_checkpoint->fetchColumn() > 0;

        if ($ada_kuis || $ada_checkpoint) {
            $pesan = "<div class='alert alert-warning'>⚠️ Akun ini sudah punya riwayat nilai/kuis. Hapus permanen tidak diizinkan. Gunakan Nonaktifkan saja.</div>";
        } else {
            $pdo->beginTransaction();
            $stmt_del_notif = $pdo->prepare("DELETE FROM notifikasi_modul WHERE user_id = ?");
            $stmt_del_notif->execute([$id_target]);

            $stmt_del_user = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
            $stmt_del_user->execute([$id_target]);
            $pdo->commit();

            $pesan = "<div class='alert alert-danger'>🗑️ Akun permanen dihapus! (Belum ada riwayat nilai, aman dihapus).</div>";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('DB Error [hapus permanen user]: ' . $e->getMessage());
        $pesan = "<div class='alert alert-danger'>Terjadi kesalahan pada sistem. Silakan hubungi administrator.</div>";
    }
}

// --- AKSI: EDIT NAMA ASLI ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_nama') {
    csrf_require_valid_post();
    $id_target = (int)($_POST['id'] ?? 0);
    $nama_baru = trim($_POST['nama_asli'] ?? '');

    if ($id_target && $nama_baru !== '') {
        try {
            $stmt_nama = $pdo->prepare("UPDATE users SET nama_asli = ? WHERE id = ? AND role != 'admin'");
            $stmt_nama->execute([$nama_baru, $id_target]);
            $pesan = "<div class='alert alert-info'>✅ Nama siswa diperbarui.</div>";
        } catch (PDOException $e) {
            error_log('DB Error [update nama user]: ' . $e->getMessage());
            $pesan = "<div class='alert alert-danger'>Terjadi kesalahan pada sistem. Silakan hubungi administrator.</div>";
        }
    } else {
        $pesan = "<div class='alert alert-warning'>⚠️ Nama tidak boleh kosong.</div>";
    }
}

// --- AKSI: RESET PASSWORD ---
if (isset($_GET['action']) && $_GET['action'] === 'reset_password' && isset($_GET['id'])) {
    validate_csrf_get();
    $id_target = (int)$_GET['id'];
    $hash_baru = password_hash(DEFAULT_RESET_PASSWORD, PASSWORD_DEFAULT);
    try {
        $stmt_reset = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role != 'admin'");
        $stmt_reset->execute([$hash_baru, $id_target]);

        if ($stmt_reset->rowCount() > 0) {
            $stmt_nama_reset = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt_nama_reset->execute([$id_target]);
            $nama_reset = $stmt_nama_reset->fetchColumn();
            $pesan = "<div class='alert alert-info'>🔑 Reset password untuk user <strong>" . htmlspecialchars($nama_reset) . "</strong> berhasil. Siswa diminta mengganti password saat login.</div>";
        } else {
            $pesan = "<div class='alert alert-danger'>❌ Gagal reset password. Akun tidak ditemukan.</div>";
        }
    } catch (PDOException $e) {
        error_log('DB Error [reset password user]: ' . $e->getMessage());
        $pesan = "<div class='alert alert-danger'>Terjadi kesalahan pada sistem. Silakan hubungi administrator.</div>";
    }
}

// --- SEARCH, SORT, FILTER KELAS, FILTER STATUS ---
$search        = trim($_GET['search'] ?? '');
$sort          = $_GET['sort'] ?? '';
$kelas_filter  = $_GET['kelas'] ?? '';
$status_filter = $_GET['status'] ?? '';
if (!in_array($status_filter, ['aktif', 'nonaktif'], true)) {
    $status_filter = '';
}

// --- READ USER (TAMPILKAN DAFTAR SISWA) ---
try {
    $sql = "SELECT id, username, nama_asli, kelas, role, no_wa_ortu, status, created_at FROM users WHERE role = 'siswa'";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (username LIKE ? OR nama_asli LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    if ($kelas_filter !== '') {
        $sql .= " AND kelas = ?";
        $params[] = $kelas_filter;
    }
    if ($status_filter !== '') {
        $sql .= " AND status = ?";
        $params[] = $status_filter;
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
    db_error($e);
}

// Ambil daftar unik kelas
$list_kelas_sql = "SELECT DISTINCT kelas FROM users WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC";
$daftar_kelas = $pdo->query($list_kelas_sql)->fetchAll(PDO::FETCH_COLUMN);

// --- STATISTIK DASHBOARD (GLOBAL — tidak mengikuti filter) ---
$stat_total    = 0;
$stat_aktif    = 0;
$stat_nonaktif = 0;
try {
    $stmt_stat = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'aktif'    THEN 1 ELSE 0 END) AS aktif,
            SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) AS nonaktif
        FROM users
        WHERE role = 'siswa'
    ");
    $row_stat = $stmt_stat->fetch(PDO::FETCH_ASSOC);
    $stat_total    = (int)($row_stat['total']    ?? 0);
    $stat_aktif    = (int)($row_stat['aktif']    ?? 0);
    $stat_nonaktif = (int)($row_stat['nonaktif'] ?? 0);
} catch (PDOException $e) {
    error_log('DB Error [statistik user]: ' . $e->getMessage());
}
$stat_kelas = count($daftar_kelas);

// Token CSRF untuk semua link aksi
$csrf_token = csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Management User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .stat-card { transition: transform .15s ease, box-shadow .15s ease; cursor: pointer; text-decoration: none; color: inherit; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.1) !important; }
        .stat-card.static { cursor: default; }
        .stat-card.static:hover { transform: none; box-shadow: 0 .125rem .25rem rgba(0,0,0,.075) !important; }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/admin_header.php'; ?>

    <div class="container mt-5 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold m-0 text-dark">👥 Management User</h4>
            <a href="index.php" class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width:40px;height:40px;" title="Kembali">
                <i class="bi bi-arrow-left"></i>
            </a>
        </div>

        <!-- 📊 DASHBOARD STATISTIK USER -->
        <div class="row g-3 mb-4">
            <!-- Total Siswa (statis) -->
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 stat-card static">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:48px;height:48px;">
                            <i class="bi bi-people-fill text-primary fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Total Siswa</div>
                            <div class="fw-bold fs-4 lh-1"><?= $stat_total ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Aktif (klik untuk filter) -->
            <div class="col-6 col-md-3">
                <a href="?<?= filter_qs(['status' => $status_filter === 'aktif' ? '' : 'aktif']) ?>"
                   class="card border-0 shadow-sm h-100 stat-card <?= $status_filter === 'aktif' ? 'border border-success border-2' : '' ?>">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:48px;height:48px;">
                            <i class="bi bi-check-circle-fill text-success fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Aktif <?= $status_filter === 'aktif' ? '<i class="bi bi-funnel-fill text-success"></i>' : '' ?></div>
                            <div class="fw-bold fs-4 lh-1 text-success"><?= $stat_aktif ?></div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Nonaktif (klik untuk filter) -->
            <div class="col-6 col-md-3">
                <a href="?<?= filter_qs(['status' => $status_filter === 'nonaktif' ? '' : 'nonaktif']) ?>"
                   class="card border-0 shadow-sm h-100 stat-card <?= $status_filter === 'nonaktif' ? 'border border-warning border-2' : '' ?>">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:48px;height:48px;">
                            <i class="bi bi-pause-circle-fill text-warning fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Nonaktif <?= $status_filter === 'nonaktif' ? '<i class="bi bi-funnel-fill text-warning"></i>' : '' ?></div>
                            <div class="fw-bold fs-4 lh-1 text-warning"><?= $stat_nonaktif ?></div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Total Kelas (statis) -->
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 stat-card static">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:48px;height:48px;">
                            <i class="bi bi-box-seam-fill text-info fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Total Kelas</div>
                            <div class="fw-bold fs-4 lh-1 text-info"><?= $stat_kelas ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info aktif filter -->
        <?php if ($status_filter !== ''): ?>
        <div class="alert alert-<?= $status_filter === 'aktif' ? 'success' : 'warning' ?> d-flex align-items-center justify-content-between py-2">
            <div>
                <i class="bi bi-funnel-fill me-1"></i>
                Menampilkan siswa <strong><?= $status_filter === 'aktif' ? 'Aktif' : 'Nonaktif' ?></strong> saja.
            </div>
            <a href="?<?= filter_qs(['status' => '']) ?>" class="btn btn-sm btn-outline-dark">
                <i class="bi bi-x-circle"></i> Hapus filter
            </a>
        </div>
        <?php endif; ?>

        <p class="text-muted small mb-4">💡 Nonaktifkan siswa yang sudah lulus/keluar — riwayat nilai tetap tersimpan untuk arsip.</p>

        <?= $pesan ?>

        <h5 class="fw-bold mb-3">📁 Filter Per Kelas</h5>
        <div class="mb-4">
            <a href="?<?= filter_qs(['kelas' => '']) ?>"
               class="btn btn-sm <?= empty($kelas_filter) ? 'btn-dark' : 'btn-outline-dark' ?> me-2 mb-1">
                🌍 Semua Kelas
            </a>
            <?php foreach ($daftar_kelas as $k): ?>
                <a href="?<?= filter_qs(['kelas' => $k]) ?>"
                   class="btn btn-sm <?= $kelas_filter === $k ? 'btn-dark' : 'btn-outline-dark' ?> me-2 mb-1">
                    📦 Kelas <?= htmlspecialchars($k) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="GET" class="row g-2 mb-3 bg-white p-3 rounded-3 shadow-sm border align-items-center">
            <?php if ($sort): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>"><?php endif; ?>
            <?php if ($kelas_filter): ?><input type="hidden" name="kelas" value="<?= htmlspecialchars($kelas_filter) ?>"><?php endif; ?>
            <?php if ($status_filter): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>"><?php endif; ?>
            <div class="col-md-8">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari username atau nama..." class="form-control form-control-sm">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-search"></i> Cari</button>
                <?php if ($search || $kelas_filter || $status_filter): ?>
                    <a href="?" class="btn btn-outline-secondary btn-sm">✕ Reset Semua</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive bg-white p-4 rounded-3 shadow-sm border">
            <table id="tableUser" class="table table-hover align-middle m-0 w-100">
                <thead class="table-light">
                    <tr>
                        <th data-orderable="false">ID</th>
                        <th data-orderable="false">Username</th>
                        <th data-orderable="false">
                            <a href="?<?= filter_qs(['sort' => $sort === 'kelas_asc' ? 'kelas_desc' : 'kelas_asc']) ?>"
                               class="text-decoration-none text-dark">
                                Kelas
                                <?php if ($sort === 'kelas_asc'): ?>
                                    <i class="bi bi-caret-up-fill"></i>
                                <?php elseif ($sort === 'kelas_desc'): ?>
                                    <i class="bi bi-caret-down-fill"></i>
                                <?php else: ?>
                                    <i class="bi bi-arrow-down-up small text-muted"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th data-orderable="false">WA Ortu</th>
                        <th data-orderable="false">Status</th>
                        <th data-orderable="false">Registrasi</th>
                        <th data-orderable="false">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar_siswa as $siswa): $is_nonaktif = ($siswa['status'] ?? 'aktif') === 'nonaktif'; ?>
                    <tr class="<?= $is_nonaktif ? 'table-secondary' : '' ?>">
                        <td><?= $siswa['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($siswa['username']) ?></strong><br>
                            <small class="text-muted" id="nama-view-<?= $siswa['id'] ?>">
                                <?= htmlspecialchars($siswa['nama_asli'] ?: 'nama belum diisi') ?>
                            </small>
                            <a href="javascript:void(0)" onclick="toggleEditNama(<?= $siswa['id'] ?>)" class="small text-decoration-none ms-1" title="Edit nama">✏️</a>

                            <form method="POST" id="form-nama-<?= $siswa['id'] ?>" class="mt-1 d-none">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_nama">
                                <input type="hidden" name="id" value="<?= $siswa['id'] ?>">
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                                <input type="hidden" name="kelas" value="<?= htmlspecialchars($kelas_filter) ?>">
                                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                                <div class="input-group input-group-sm" style="max-width: 230px;">
                                    <input type="text" name="nama_asli" value="<?= htmlspecialchars($siswa['nama_asli'] ?? '') ?>"
                                           placeholder="Nama lengkap..." required class="form-control form-control-sm">
                                    <button type="submit" class="btn btn-success btn-sm">Simpan</button>
                                </div>
                            </form>
                        </td>
                        <td><?= htmlspecialchars($siswa['kelas'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($siswa['no_wa_ortu'] ?: '-') ?></td>
                        <td>
                            <span class="badge <?= $is_nonaktif ? 'bg-warning text-dark' : 'bg-success' ?>">
                                <?= $is_nonaktif ? 'Nonaktif' : 'Aktif' ?>
                            </span>
                        </td>
                        <td><small><?= htmlspecialchars($siswa['created_at']) ?></small></td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <?php if ($is_nonaktif): ?>
                                    <a href="?<?= action_qs(['action' => 'aktifkan', 'id' => $siswa['id']]) ?>"
                                       class="btn btn-sm btn-outline-success"
                                       onclick="return confirm('Aktifkan kembali akun ini?')"
                                       title="Aktifkan">🔄</a>
                                <?php else: ?>
                                    <a href="?<?= action_qs(['action' => 'nonaktifkan', 'id' => $siswa['id']]) ?>"
                                       class="btn btn-sm btn-outline-warning"
                                       onclick="return confirm('Nonaktifkan akun ini? Riwayat nilai tetap tersimpan, tapi siswa tidak bisa login lagi.')"
                                       title="Nonaktifkan">🔴</a>
                                <?php endif; ?>

                                <a href="?<?= action_qs(['action' => 'reset_password', 'id' => $siswa['id']]) ?>"
                                   class="btn btn-sm btn-outline-info"
                                   onclick="return confirm('Yakin reset password untuk siswa ini? Siswa akan diminta mengganti password saat login berikutnya.')"
                                   title="Reset Password">🔑</a>

                                <?php if ($is_nonaktif): ?>
                                    <a href="?<?= action_qs(['action' => 'hapus_permanen', 'id' => $siswa['id']]) ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('⚠️ HAPUS PERMANEN akun ini? Hanya bisa jika belum ada riwayat nilai/kuis. Data akan hilang selamanya!')"
                                       title="Hapus Permanen">🗑️</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($daftar_siswa)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <?php
                            $kondisi = [];
                            if ($search)        $kondisi[] = 'username/nama mengandung "' . htmlspecialchars($search) . '"';
                            if ($kelas_filter)  $kondisi[] = 'kelas ' . htmlspecialchars($kelas_filter);
                            if ($status_filter) $kondisi[] = 'status ' . htmlspecialchars($status_filter);
                            echo $kondisi
                                ? 'Tidak ada siswa dengan ' . implode(' dan ', $kondisi) . '.'
                                : 'Belum ada siswa yang mendaftar.';
                            ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#tableUser').DataTable({
                "order": [], // biarkan urutan dari server (ORDER BY di PHP)
                "columnDefs": [
                    { "orderable": false, "targets": [0, 1, 3, 4, 5, 6] }
                ],
                "language": {
                    "search": "🔍 Cari:",
                    "lengthMenu": "Tampilkan _MENU_",
                    "info": "_START_ - _END_ dari _TOTAL_",
                    "paginate": {
                        "first": "Awal", "last": "Akhir",
                        "next": "›", "previous": "‹"
                    },
                    "emptyTable": "Tidak ada data"
                }
            });
        });

        function toggleEditNama(id) {
            const form = document.getElementById('form-nama-' + id);
            form.classList.toggle('d-none');
        }
    </script>
</body>
</html>