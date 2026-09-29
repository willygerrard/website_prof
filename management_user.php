<?php
require_once 'session.php';      // session dulu
require_once 'csrf_helper.php';  // baru helper
require 'koneksi.php';

checkLogin();
checkRole(['admin']);

if (strpos($_SERVER['REQUEST_URI'], 'pintu-belakang-sija') === false) {
    header("HTTP/1.1 404 Not Found");
    exit();
}

$pesan = "";

// ============================================================
// HELPER: CSRF & URL builders
// ============================================================
function validate_csrf_get() {
    $token = $_GET['csrf_token'] ?? '';
    if (!csrf_verify($token)) {
        die("❌ Token CSRF tidak valid. Silakan kembali ke halaman Management User.");
    }
}

function action_qs($extra = []) {
    global $search, $sort, $kelas_filter, $status_filter, $csrf_token;
    $qs = $extra;
    if (!array_key_exists('search', $qs) && $search)         $qs['search'] = $search;
    if (!array_key_exists('sort',   $qs) && $sort)           $qs['sort']   = $sort;
    if (!array_key_exists('kelas',  $qs) && $kelas_filter)   $qs['kelas']  = $kelas_filter;
    if (!array_key_exists('status', $qs) && $status_filter)  $qs['status'] = $status_filter;
    if (!array_key_exists('csrf_token', $qs) && $csrf_token) $qs['csrf_token'] = $csrf_token;
    $qs = array_filter($qs, fn($v) => $v !== '' && $v !== null);
    return http_build_query($qs);
}

function filter_qs($extra = []) {
    global $search, $sort, $kelas_filter, $status_filter;
    $qs = $extra;
    if (!array_key_exists('search', $qs) && $search)        $qs['search'] = $search;
    if (!array_key_exists('sort',   $qs) && $sort)          $qs['sort']   = $sort;
    if (!array_key_exists('kelas',  $qs) && $kelas_filter)  $qs['kelas']  = $kelas_filter;
    if (!array_key_exists('status', $qs) && $status_filter) $qs['status'] = $status_filter;
    $qs = array_filter($qs, fn($v) => $v !== '' && $v !== null);
    return http_build_query($qs);
}

// ============================================================
// HELPER: Parsing kelas & aturan kenaikan
// ============================================================

/** Rapikan spasi ganda: "XII   SIJA " → "XII SIJA" */
function normalisasi_kelas(string $kelas): string {
    return trim(preg_replace('/\s+/u', ' ', $kelas));
}

/** Bandingkan dua nama kelas tanpa peduli huruf besar/kecil & spasi ganda. */
function kelas_sama(string $a, string $b): bool {
    return mb_strtolower(normalisasi_kelas($a), 'UTF-8') === mb_strtolower(normalisasi_kelas($b), 'UTF-8');
}

/**
 * Parse "X TKJ 1" → ['tingkat'=>'X', 'jurusan'=>'TKJ', 'nomor'=>'1']
 * Huruf jurusan DIPERTAHANKAN apa adanya (tidak di-uppercase) supaya hasil
 * kelas_berikutnya() persis sama dengan penulisan di database.
 * Return null kalau format tidak dikenali.
 */
function parse_kelas(string $kelas): ?array {
    $kelas = normalisasi_kelas($kelas);
    // Tingkat: XIII|XII|XI|X (longest-first biar aman)
    if (!preg_match('/^(XIII|XII|XI|X)\s+(.+?)(?:\s+(\d+))?$/iu', $kelas, $m)) {
        return null;
    }
    return [
        'tingkat' => strtoupper($m[1]),
        'jurusan' => trim($m[2]),
        'nomor'   => $m[3] ?? null,
    ];
}

/**
 * Hitung kelas berikutnya.
 *   "X TKJ 1"   → "XI TKJ 1"
 *   "XI Sija"   → "XII Sija"   (huruf jurusan tidak diubah)
 *   "XIII SIJA" → null  (kelas akhir)
 *   "ngawur"    → null  (format salah)
 */
function kelas_berikutnya(string $kelas): ?string {
    $parsed = parse_kelas($kelas);
    if ($parsed === null) return null;

    $peta = ['X' => 'XI', 'XI' => 'XII', 'XII' => 'XIII', 'XIII' => null];
    $tingkat_baru = $peta[$parsed['tingkat']] ?? null;
    if ($tingkat_baru === null) return null;

    $kelas_baru = $tingkat_baru . ' ' . $parsed['jurusan'];
    if ($parsed['nomor'] !== null) $kelas_baru .= ' ' . $parsed['nomor'];
    return $kelas_baru;
}

/** Cek apakah kelas adalah kelas akhir (XIII). */
function is_kelas_akhir(string $kelas): bool {
    $parsed = parse_kelas($kelas);
    return $parsed !== null && $parsed['tingkat'] === 'XIII';
}

// ============================================================
// AKSI: NONAKTIFKAN USER
// ============================================================
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

// ============================================================
// AKSI: AKTIFKAN ULANG USER
// ============================================================
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

// ============================================================
// AKSI: HAPUS PERMANEN
// ============================================================
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

// ============================================================
// AKSI: EDIT NAMA ASLI
// ============================================================
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

// ============================================================
// AKSI: RESET PASSWORD
// ============================================================
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

// ============================================================
// AKSI: PROMOSI BULK (Naik Kelas) — DENGAN VALIDASI BERJENJANG
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'promosi_bulk') {
    csrf_require_valid_post();
    $id_targets = array_map('intval', array_filter($_POST['ids'] ?? []));
    $kelas_baru = trim($_POST['kelas_tujuan'] ?? '');

    if (empty($id_targets) || $kelas_baru === '') {
        $pesan = "<div class='alert alert-warning'>⚠️ Pilih minimal 1 siswa dan tentukan kelas tujuan.</div>";
    } else {
        try {
            // 1. Ambil semua kelas asal siswa yang dipilih ('' = belum punya kelas)
            $in_placeholders = implode(',', array_fill(0, count($id_targets), '?'));
            $stmt = $pdo->prepare("
                SELECT DISTINCT COALESCE(kelas, '') FROM users
                WHERE id IN ($in_placeholders) AND role = 'siswa'
            ");
            $stmt->execute($id_targets);
            $kelas_asal_list = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // 2. Validasi: harus dari 1 kelas yang sama & sudah punya kelas
            if (count($kelas_asal_list) === 0) {
                $pesan = "<div class='alert alert-danger'>⚠️ Siswa yang dipilih tidak ditemukan.</div>";
            } elseif (in_array('', $kelas_asal_list, true)) {
                $pesan = "<div class='alert alert-danger'>⚠️ Ada siswa yang belum punya kelas. Isi kelasnya dulu sebelum dinaikkan.</div>";
            } elseif (count($kelas_asal_list) > 1) {
                $pesan = "<div class='alert alert-danger'>⚠️ Semua siswa yang dipilih harus dari <strong>kelas yang sama</strong>. Sekarang ada "
                       . count($kelas_asal_list) . " kelas berbeda: <strong>"
                       . htmlspecialchars(implode(', ', $kelas_asal_list)) . "</strong>.</div>";
            } else {
                $kelas_asal = $kelas_asal_list[0];

                // 3. Cek kelas akhir (XIII)
                if (is_kelas_akhir($kelas_asal)) {
                    $pesan = "<div class='alert alert-warning'>⚠️ Kelas <strong>" . htmlspecialchars($kelas_asal)
                           . "</strong> adalah kelas akhir. Siswa harus <strong>DILULUSKAN</strong> (ubah status), bukan dinaikkan kelasnya.</div>";
                } else {
                    // 4. Hitung kelas yang seharusnya
                    $kelas_seharusnya = kelas_berikutnya($kelas_asal);

                    if ($kelas_seharusnya === null) {
                        $pesan = "<div class='alert alert-danger'>⚠️ Format kelas <strong>"
                               . htmlspecialchars($kelas_asal) . "</strong> tidak dikenali. Tidak bisa tentukan kelas berikutnya.</div>";
                    } elseif (!kelas_sama($kelas_baru, $kelas_seharusnya)) {
                        // 5. Kelas tujuan kiriman user harus sama dengan hasil hitungan
                        $pesan = "<div class='alert alert-danger'>⚠️ Siswa dari <strong>" . htmlspecialchars($kelas_asal)
                               . "</strong> hanya bisa naik ke <strong>" . htmlspecialchars($kelas_seharusnya)
                               . "</strong>, bukan ke <strong>" . htmlspecialchars($kelas_baru) . "</strong>.</div>";
                    } else {
                        // 6. Lolos semua validasi — simpan kelas HASIL HITUNGAN server,
                        //    bukan string kiriman browser. "AND kelas = ?" menjaga data tidak
                        //    berubah di antara validasi dan update.
                        //    Sekaligus catat riwayat kenaikan (kelas_lama, kelas_baru) per siswa
                        //    ATOMIK dalam 1 transaksi — update + log jalan atau gagal bersama.
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare("UPDATE users SET kelas = ? WHERE id IN ($in_placeholders) AND role = 'siswa' AND kelas = ?");
                        $stmt->execute(array_merge([$kelas_seharusnya], $id_targets, [$kelas_asal]));
                        $terupdate = $stmt->rowCount();

                        $admin = $_SESSION['username'] ?? 'admin';
                        $stmt_log = $pdo->prepare(
                            "INSERT INTO riwayat_kelas (user_id, kelas_lama, kelas_baru, aksi, dibuat_oleh)
                             VALUES (?, ?, ?, 'naik', ?)"
                        );
                        foreach ($id_targets as $id_siswa) {
                            $stmt_log->execute([$id_siswa, $kelas_asal, $kelas_seharusnya, $admin]);
                        }

                        $pdo->commit();
                        $pesan = "<div class='alert alert-success'>✅ <strong>$terupdate siswa</strong> dari <strong>"
                               . htmlspecialchars($kelas_asal) . "</strong> berhasil dinaikkan ke <strong>"
                               . htmlspecialchars($kelas_seharusnya) . "</strong>.</div>";
                    }
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('DB Error [promosi bulk]: ' . $e->getMessage());
            $pesan = "<div class='alert alert-danger'>Terjadi kesalahan pada sistem. Silakan hubungi administrator.</div>";
        }
    }
}

// ============================================================
// AKSI: LULUSKAN BULK (khusus kelas XIII)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'luluskan_bulk') {
    csrf_require_valid_post();
    $id_targets = array_map('intval', array_filter($_POST['ids'] ?? []));

    if (empty($id_targets)) {
        $pesan = "<div class='alert alert-warning'>⚠️ Pilih minimal 1 siswa.</div>";
    } else {
        try {
            $in_placeholders = implode(',', array_fill(0, count($id_targets), '?'));
            $stmt = $pdo->prepare("
                SELECT DISTINCT kelas FROM users 
                WHERE id IN ($in_placeholders) AND role = 'siswa' AND kelas IS NOT NULL
            ");
            $stmt->execute($id_targets);
            $kelas_list = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $semua_kelas_akhir = !empty($kelas_list) 
                && count(array_filter($kelas_list, 'is_kelas_akhir')) === count($kelas_list);

            if (!$semua_kelas_akhir) {
                $pesan = "<div class='alert alert-danger'>⚠️ Aksi 'Luluskan' hanya untuk siswa kelas <strong>XIII</strong>. Ditemukan: <strong>" 
                       . htmlspecialchars(implode(', ', $kelas_list)) . "</strong>.</div>";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET status = 'lulus' WHERE id IN ($in_placeholders) AND role = 'siswa'");
                $stmt->execute($id_targets);
                $terupdate = $stmt->rowCount();
                $pesan = "<div class='alert alert-info'>🎓 <strong>$terupdate siswa</strong> berhasil ditandai <strong>LULUS</strong>. Data nilai mereka tetap tersimpan untuk arsip.</div>";
            }
        } catch (PDOException $e) {
            error_log('DB Error [luluskan bulk]: ' . $e->getMessage());
            $pesan = "<div class='alert alert-danger'>Terjadi kesalahan pada sistem. Silakan hubungi administrator.</div>";
        }
    }
}

// ============================================================
// AKSI: TURUNKAN BULK (UNDO NAIK KELAS)
// ============================================================
// Aturan undo (interpretasi disepakati):
//   Seorang siswa hanya boleh di-undo JIKA baris riwayat TERBARU-nya
//   (baris log terakhir untuk siswa tsb) ber-aksi 'naik'.
//   - Baris terbaru 'naik'      → undo diizinkan: kelas dikembalikan ke
//                                 kelas_lama baris itu, lalu catat log 'turun'.
//   - Baris terbaru 'turun'     → SUDAH pernah di-undo (atau di-turunkan
//                                 manual) → DITOLAK (anti double-undo).
//   - Tidak ada riwayat         → DITOLAK (belum pernah naik kelas).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'turunkan_bulk') {
    csrf_require_valid_post();
    $id_targets = array_map('intval', array_filter($_POST['ids'] ?? []));

    if (empty($id_targets)) {
        $pesan = "<div class='alert alert-warning'>⚠️ Pilih minimal 1 siswa.</div>";
    } else {
        try {
            $pdo->beginTransaction();

            $admin = $_SESSION['username'] ?? 'admin';
            $terturunkan = 0;
            $ditolak = [];

            $stmt_cek = $pdo->prepare(
                "SELECT kelas_lama, aksi FROM riwayat_kelas
                 WHERE user_id = ? ORDER BY id DESC LIMIT 1"
            );
            $stmt_undo = $pdo->prepare(
                "UPDATE users SET kelas = ? WHERE id = ? AND role = 'siswa'"
            );
            $stmt_log = $pdo->prepare(
                "INSERT INTO riwayat_kelas (user_id, kelas_lama, kelas_baru, aksi, dibuat_oleh)
                 VALUES (?, ?, ?, 'turun', ?)"
            );

            foreach ($id_targets as $id_siswa) {
                $stmt_cek->execute([$id_siswa]);
                $log_terbaru = $stmt_cek->fetch();

                // Baris riwayat terbaru harus 'naik' — kalau kosong / sudah 'turun' → tolak.
                if ($log_terbaru === false || $log_terbaru['aksi'] !== 'naik') {
                    $ditolak[] = $id_siswa;
                    continue;
                }

                $kelas_kembali = $log_terbaru['kelas_lama'];
                $kelas_sekarang = $log_terbaru['kelas_baru'];

                $stmt_undo->execute([$kelas_kembali, $id_siswa]);
                $stmt_log->execute([$id_siswa, $kelas_sekarang, $kelas_kembali, $admin]);
                $terturunkan++;
            }

            $pdo->commit();

            $pesan = "<div class='alert alert-warning'>↩️ <strong>$terturunkan siswa</strong> berhasil dikembalikan kelasnya (undo).";
            if (!empty($ditolak)) {
                $pesan .= " <strong>" . count($ditolak) . " siswa ditolak</strong> (sudah pernah di-undo / belum pernah naik kelas): ID " . implode(', ', $ditolak) . ".";
            }
            $pesan .= "</div>";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('DB Error [turunkan bulk]: ' . $e->getMessage());
            $pesan = "<div class='alert alert-danger'>Terjadi kesalahan pada sistem. Silakan hubungi administrator.</div>";
        }
    }
}

// ============================================================
// SEARCH, SORT, FILTER
// ============================================================
$search        = trim($_GET['search'] ?? '');
$sort          = $_GET['sort'] ?? '';
$kelas_filter  = $_GET['kelas'] ?? '';
$status_filter = $_GET['status'] ?? '';
if (!in_array($status_filter, ['aktif', 'nonaktif', 'lulus'], true)) {
    $status_filter = '';
}

// ============================================================
// READ USER
// ============================================================
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

// ============================================================
// DAFTAR KELAS UNIK & PETA KENAIKAN (untuk JS)
// ============================================================
$list_kelas_sql = "SELECT DISTINCT kelas FROM users WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC";
$daftar_kelas = $pdo->query($list_kelas_sql)->fetchAll(PDO::FETCH_COLUMN);

// Peta kelas → kelas berikutnya (null kalau kelas akhir / tidak valid)
$peta_kenaikan = [];
foreach ($daftar_kelas as $k) {
    $peta_kenaikan[$k] = kelas_berikutnya($k);
}
// Daftar kelas yang termasuk kelas akhir (XIII) — untuk aksi "Luluskan"
$daftar_kelas_akhir = array_values(array_filter($daftar_kelas, 'is_kelas_akhir'));

// ============================================================
// STATISTIK DASHBOARD
// ============================================================
$stat_total    = 0;
$stat_aktif    = 0;
$stat_nonaktif = 0;
$stat_lulus    = 0;
try {
    $stmt_stat = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'aktif'    THEN 1 ELSE 0 END) AS aktif,
            SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) AS nonaktif,
            SUM(CASE WHEN status = 'lulus'    THEN 1 ELSE 0 END) AS lulus
        FROM users
        WHERE role = 'siswa'
    ");
    $row_stat = $stmt_stat->fetch(PDO::FETCH_ASSOC);
    $stat_total    = (int)($row_stat['total']    ?? 0);
    $stat_aktif    = (int)($row_stat['aktif']    ?? 0);
    $stat_nonaktif = (int)($row_stat['nonaktif'] ?? 0);
    $stat_lulus    = (int)($row_stat['lulus']    ?? 0);
} catch (PDOException $e) {
    error_log('DB Error [statistik user]: ' . $e->getMessage());
}
$stat_kelas = count($daftar_kelas);

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
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 stat-card static">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;">
                            <i class="bi bi-people-fill text-primary fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Total Siswa</div>
                            <div class="fw-bold fs-4 lh-1"><?= $stat_total ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <a href="?<?= filter_qs(['status' => $status_filter === 'aktif' ? '' : 'aktif']) ?>"
                   class="card border-0 shadow-sm h-100 stat-card <?= $status_filter === 'aktif' ? 'border border-success border-2' : '' ?>">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;">
                            <i class="bi bi-check-circle-fill text-success fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Aktif <?= $status_filter === 'aktif' ? '<i class="bi bi-funnel-fill text-success"></i>' : '' ?></div>
                            <div class="fw-bold fs-4 lh-1 text-success"><?= $stat_aktif ?></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-6 col-md-3">
                <a href="?<?= filter_qs(['status' => $status_filter === 'nonaktif' ? '' : 'nonaktif']) ?>"
                   class="card border-0 shadow-sm h-100 stat-card <?= $status_filter === 'nonaktif' ? 'border border-warning border-2' : '' ?>">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;">
                            <i class="bi bi-pause-circle-fill text-warning fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Nonaktif <?= $status_filter === 'nonaktif' ? '<i class="bi bi-funnel-fill text-warning"></i>' : '' ?></div>
                            <div class="fw-bold fs-4 lh-1 text-warning"><?= $stat_nonaktif ?></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 stat-card static">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;">
                            <i class="bi bi-mortarboard-fill text-info fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Lulus</div>
                            <div class="fw-bold fs-4 lh-1 text-info"><?= $stat_lulus ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 stat-card static">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;">
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
        <div class="alert alert-<?= $status_filter === 'aktif' ? 'success' : ($status_filter === 'lulus' ? 'info' : 'warning') ?> d-flex align-items-center justify-content-between py-2">
            <div>
                <i class="bi bi-funnel-fill me-1"></i>
                Menampilkan siswa <strong><?= $status_filter === 'aktif' ? 'Aktif' : ($status_filter === 'lulus' ? 'Lulus' : 'Nonaktif') ?></strong> saja.
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

        <!-- PROMOSI BULK -->
        <form method="POST" id="formBulk" class="row g-2 mb-4 bg-white p-3 rounded-3 shadow-sm border align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Aksi Massal</label>
                <select class="form-select form-select-sm" name="action" id="bulkActionSelect" onchange="window.refreshBulkUI && window.refreshBulkUI()">
                    <option value="">-- Pilih Aksi --</option>
                    <option value="promosi_bulk">🚀 Naik Kelas</option>
                    <option value="luluskan_bulk">🎓 Luluskan (Kelas XIII)</option>
                    <option value="turunkan_bulk">↩️ Turunkan Kelas (Undo)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Kelas Tujuan</label>
                <select class="form-select form-select-sm" name="kelas_tujuan" id="kelasTujuanSelect" disabled>
                    <?php /* Opsi diisi otomatis oleh JS sesuai kelas siswa yang dicentang */ ?>
                    <option value="">-- Pilih --</option>
                </select>
            </div>
            <div class="col-md-6 d-flex gap-2 align-items-end">
                <button type="submit" class="btn btn-primary btn-sm" id="btnPromosiBulk" disabled>
                    <i class="bi bi-arrow-right-circle"></i> Eksekusi
                </button>
                <p class="text-muted small mb-0 ps-2 hint-promosi">Centang siswa di tabel, pilih aksi & kelas tujuan di atas.</p>
            </div>
        </form>

        <div class="table-responsive bg-white p-4 rounded-3 shadow-sm border">
            <table id="tableUser" class="table table-hover align-middle m-0 w-100">
                <thead class="table-light">
                    <tr>
                        <th style="width: 4%" data-orderable="false">
                            <input type="checkbox" class="form-check-input" id="checkAllSiswa" title="Pilih semua">
                        </th>
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
                    <?php foreach ($daftar_siswa as $siswa): 
                        $status_val = $siswa['status'] ?? 'aktif';
                        $is_nonaktif = $status_val === 'nonaktif';
                        $is_lulus    = $status_val === 'lulus';
                        if ($is_lulus)      $statusBadge = 'bg-info text-dark';
                        elseif ($is_nonaktif) $statusBadge = 'bg-warning text-dark';
                        else                $statusBadge = 'bg-success';
                        $statusLabel = $is_lulus ? 'Lulus' : ($is_nonaktif ? 'Nonaktif' : 'Aktif');
                    ?>
                    <tr class="<?= $is_nonaktif || $is_lulus ? 'table-secondary' : '' ?>">
                        <td>
                            <input type="checkbox" class="form-check-input siswa-check"
                                   name="ids[]" form="formBulk"
                                   onchange="window.refreshBulkUI && window.refreshBulkUI()"
                                   value="<?= (int)$siswa['id'] ?>"
                                   data-kelas="<?= htmlspecialchars($siswa['kelas'] ?? '') ?>">
                        </td>
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
                            <span class="badge <?= $statusBadge ?>">
                                <?= $statusLabel ?>
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
                                <?php elseif ($is_lulus): ?>
                                    <span class="text-muted small me-1"><i class="bi bi-check-circle"></i> Lulus</span>
                                <?php else: ?>
                                    <a href="?<?= action_qs(['action' => 'nonaktifkan', 'id' => $siswa['id']]) ?>"
                                       class="btn btn-sm btn-outline-warning"
                                       onclick="return confirm('Nonaktifkan akun ini? Riwayat nilai tetap tersimpan, tapi siswa tidak bisa login lagi.')"
                                       title="Nonaktifkan">🔴</a>
                                <?php endif; ?>

                                <?php if (!($is_nonaktif || $is_lulus)): ?>
                                <a href="?<?= action_qs(['action' => 'reset_password', 'id' => $siswa['id']]) ?>"
                                   class="btn btn-sm btn-outline-info"
                                   onclick="return confirm('Yakin reset password untuk siswa ini? Siswa akan diminta mengganti password saat login berikutnya.')"
                                   title="Reset Password">🔑</a>
                                <?php endif; ?>

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
                        <td colspan="8" class="text-center text-muted py-4">
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
        // Peta kelas → kelas berikutnya (dihitung PHP). null = kelas akhir / format tidak dikenali.
        const PETA_KENAIKAN = <?= json_encode($peta_kenaikan, JSON_UNESCAPED_UNICODE) ?>;
        const KELAS_AKHIR   = <?= json_encode($daftar_kelas_akhir, JSON_UNESCAPED_UNICODE) ?>;

        function toggleEditNama(id) {
            const form = document.getElementById('form-nama-' + id);
            if (form) form.classList.toggle('d-none');
        }

        // ------------------------------------------------------------
        // 1) DataTables — DIPISAH dari aksi massal. Kalau init gagal (mis. jQuery dobel
        //    dimuat), aksi massal di bawah tetap jalan.
        // ------------------------------------------------------------
        try {
            jQuery(function ($) {
                $('#tableUser').DataTable({
                    "order": [],
                    "columnDefs": [
                        { "orderable": false, "targets": [0, 1, 4, 5, 6, 7] }
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
        } catch (err) {
            console.error('[DataTables] gagal init:', err);
        }

        // ------------------------------------------------------------
        // 2) AKSI MASSAL — vanilla JS, tanpa ketergantungan jQuery/DataTables.
        //    Dipicu dari 3 jalur sekaligus (idempoten, aman dipanggil berulang):
        //    inline onchange, addEventListener, dan poller ringan sebagai jaring pengaman.
        // ------------------------------------------------------------
        (function () {
            const actionSel = document.getElementById('bulkActionSelect');
            const tujuanSel = document.getElementById('kelasTujuanSelect');
            const btn       = document.getElementById('btnPromosiBulk');
            const hintEl    = document.querySelector('.hint-promosi');
            const checkAll  = document.getElementById('checkAllSiswa');
            const formBulk  = document.getElementById('formBulk');
            const HINT_AWAL = 'Centang siswa di tabel, pilih aksi & kelas tujuan di atas.';

            if (!actionSel || !tujuanSel || !btn || !formBulk) {
                console.error('[bulk] elemen form tidak ditemukan');
                return;
            }

            // Kalau DataTables aktif, ambil SEMUA baris (termasuk halaman lain yang tidak ada di DOM).
            function dtApi() {
                try {
                    if (window.jQuery && jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable('#tableUser')) {
                        return jQuery('#tableUser').DataTable();
                    }
                } catch (e) { /* abaikan, pakai DOM biasa */ }
                return null;
            }
            function semuaCek(opsi) {
                const t = dtApi();
                return t ? t.$('.siswa-check', opsi || {}).toArray()
                         : Array.from(document.querySelectorAll('.siswa-check'));
            }
            function terpilih() { return semuaCek().filter(function (cb) { return cb.checked; }); }
            function kelasTerpilih() {
                const set = new Set();
                terpilih().forEach(function (cb) { set.add(cb.dataset.kelas || ''); });
                return Array.from(set);
            }

            function setHint(t) { if (hintEl) hintEl.textContent = t; }
            function setBtn(aktif, teks) { btn.disabled = !aktif; btn.textContent = teks; }

            // Dropdown kelas tujuan dibangun dari aturan kenaikan, BUKAN dari kelas yang kebetulan ada di DB.
            function isiTujuan(daftar, placeholder) {
                tujuanSel.innerHTML = '';
                tujuanSel.add(new Option(placeholder || '-- Pilih --', ''));
                daftar.forEach(function (k) { tujuanSel.add(new Option(k, k)); });
                if (daftar.length === 1) tujuanSel.value = daftar[0];
            }

            function refreshBulkUI() {
                const mode = actionSel.value;
                const ks   = kelasTerpilih();

                tujuanSel.disabled = true;
                setBtn(false, 'Eksekusi');
                isiTujuan([], '-- Pilih --');
                setHint(HINT_AWAL);

                if (mode === '') return;

                if (ks.length === 0) {
                    isiTujuan([], '-- Centang siswa dulu --');
                    setHint('Centang dulu siswa yang mau diproses.');
                    return;
                }
                if (mode === 'turunkan_bulk') {
                    // Undo boleh lintas kelas — revert berdasarkan log riwayat terbaru per siswa.
                    tujuanSel.disabled = true;
                    isiTujuan([], '-- Tidak perlu --');
                    setBtn(true, '↩️ Undo Naik Kelas');
                    setHint('💡 Kelas ' + terpilih().length + ' siswa dikembalikan ke kelas sebelum naik (berdasar log terbaru). Yang sudah di-undo / belum pernah naik akan DITOLAK.');
                    return;
                }
                if (ks.length > 1) {
                    isiTujuan([], '-- Kelas campur --');
                    setHint('⚠️ Siswa yang dicentang berasal dari ' + ks.length + ' kelas berbeda: '
                            + ks.map(function (k) { return k || '(tanpa kelas)'; }).join(', ')
                            + '. Pilih dari 1 kelas yang sama.');
                    return;
                }

                const asal = ks[0];
                if (asal === '') {
                    isiTujuan([], '-- Belum punya kelas --');
                    setHint('⚠️ Siswa yang dicentang belum punya kelas. Isi kelasnya dulu.');
                    return;
                }

                if (mode === 'promosi_bulk') {
                    const tujuan = PETA_KENAIKAN[asal] || null;
                    if (tujuan === null) {
                        const akhir = KELAS_AKHIR.indexOf(asal) !== -1;
                        isiTujuan([], akhir ? '-- Kelas akhir --' : '-- Format tak dikenali --');
                        setHint(akhir
                            ? '⚠️ Kelas ' + asal + ' adalah kelas akhir. Gunakan aksi "Luluskan".'
                            : '⚠️ Format kelas "' + asal + '" tidak dikenali, tidak bisa ditentukan kelas berikutnya.');
                        return;
                    }
                    isiTujuan([tujuan]);
                    tujuanSel.disabled = false;
                    setBtn(true, 'Eksekusi Naik Kelas');
                    setHint('💡 Siswa dari ' + asal + ' hanya bisa naik ke ' + tujuan + '.');
                } else if (mode === 'luluskan_bulk') {
                    if (KELAS_AKHIR.indexOf(asal) === -1) {
                        setHint('⚠️ "Luluskan" hanya untuk kelas XIII. Yang dicentang: ' + asal + '.');
                        return;
                    }
                    setBtn(true, '🎓 Luluskan Sekarang');
                    setHint('💡 ' + terpilih().length + ' siswa kelas ' + asal + ' akan ditandai LULUS.');
                }
            }
            window.refreshBulkUI = refreshBulkUI;   // dipakai oleh inline onchange

            function syncCheckAll() {
                if (!checkAll) return;
                const semua = semuaCek();
                checkAll.checked = semua.length > 0 && semua.every(function (cb) { return cb.checked; });
            }
            function onUbah() { syncCheckAll(); refreshBulkUI(); }

            // Jalur 1: addEventListener (delegasi di document, tahan terhadap redraw DataTables)
            document.addEventListener('change', function (e) {
                const t = e.target;
                if (!t) return;
                if (t.id === 'bulkActionSelect' || (t.classList && t.classList.contains('siswa-check'))) onUbah();
            });

            if (checkAll) {
                checkAll.addEventListener('change', function () {
                    // hanya baris yang lolos pencarian DataTables, di semua halaman
                    semuaCek({ search: 'applied' }).forEach(function (cb) { cb.checked = checkAll.checked; });
                    refreshBulkUI();
                });
            }

            // Jalur 2: poller ringan — hanya memicu refresh kalau kondisi benar-benar berubah
            let ttd = '';
            setInterval(function () {
                const now = actionSel.value + '|' + terpilih().map(function (cb) { return cb.value; }).join(',');
                if (now !== ttd) { ttd = now; refreshBulkUI(); }
            }, 300);

            // Submit: checkbox di halaman aktif ikut terkirim lewat atribut form="formBulk".
            // Checkbox di halaman lain (tidak ada di DOM) ditambahkan sebagai hidden input.
            formBulk.addEventListener('submit', function (e) {
                Array.from(formBulk.querySelectorAll('input[type="hidden"][data-bulk-id]')).forEach(function (n) { n.remove(); });
                const dipilih = terpilih();
                if (dipilih.length === 0) {
                    e.preventDefault();
                    alert('Pilih minimal 1 siswa.');
                    return;
                }
                dipilih.forEach(function (cb) {
                    if (document.body.contains(cb)) return;   // sudah ikut terkirim secara native
                    const h = document.createElement('input');
                    h.type = 'hidden'; h.name = 'ids[]'; h.value = cb.value;
                    h.setAttribute('data-bulk-id', '1');
                    formBulk.appendChild(h);
                });
            });

            refreshBulkUI();
            console.log('[bulk] siap — aksi massal aktif');
        })();
    </script>
</body>
</html>