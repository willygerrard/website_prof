<?php
/**
 * seed_common.php
 * Library bersama untuk seed data dummy LMS di STAGING.
 *
 * Fitur:
 * - Membaca kredensial dari file .env.staging (beberapa lokasi) atau env var.
 * - Guard keras: menolak jalan kalau nama DB tidak mengandung "staging".
 * - Helper transaksi, hash password, marker dummy.
 * - find_dummy_ids() + purge_all_dummy() untuk membersihkan data dummy.
 *
 * CATATAN: file ini BUKAN script yang dijalankan sendiri. Pakai:
 *   php seed/seed_data.php   (buat dummy)
 *   php seed/seed_clean.php  (hapus dummy)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("seed_common.php hanya untuk CLI.\n");
}

// === Marker dummy (dipakai untuk cari & hapus) ===
define('DUMMY_USERNAME_PREFIX', 'dummy_');
define('DUMMY_TITLE_PREFIX', '[DUMMY]');
define('DUMMY_KATEGORI_PREFIX', '[DUMMY]');

// =====================================================================
// Pembacaan konfigurasi
// =====================================================================

/**
 * Parse file KEY=value sederhana (gaya .env). Abaikan komentar & baris kosong.
 */
function seed_parse_env_file(string $path): array {
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }
    $out = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        // Dukung awalan "export "
        if (strpos($line, 'export ') === 0) {
            $line = substr($line, 7);
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim(trim($value), "\"'");
        if ($key !== '') {
            $out[$key] = $value;
        }
    }
    return $out;
}

/**
 * Gabungan .env.staging dari beberapa lokasi (yang pertama menang).
 * SENGAJA hanya membaca .env.staging supaya tidak pernah "menarik" nilai
 * production dari .env biasa.
 */
function seed_env(): array {
    static $env = null;
    if ($env !== null) {
        return $env;
    }

    $paths = [
        __DIR__ . '/.env.staging',                 // seed/.env.staging
        dirname(__DIR__) . '/.env.staging',        // project/.env.staging
        dirname(__DIR__, 2) . '/.env.staging',     // /var/www/html/.env.staging
    ];

    $env = [];
    foreach ($paths as $path) {
        foreach (seed_parse_env_file($path) as $k => $v) {
            if (!array_key_exists($k, $env)) {
                $env[$k] = $v;
            }
        }
    }
    return $env;
}

/**
 * Ambil nilai pertama yang terisi dari daftar kunci.
 * Urutan: environment variable dulu, lalu file .env.staging.
 */
function seed_env_first(array $keys, ?string $default = null): ?string {
    $file = seed_env();
    foreach ($keys as $key) {
        $v = getenv($key);
        if ($v !== false && $v !== '') {
            return $v;
        }
        if (isset($file[$key]) && $file[$key] !== '') {
            return $file[$key];
        }
    }
    return $default;
}

function seed_is_container(): bool {
    return is_file('/.dockerenv') || is_file('/run/.containerenv');
}

/**
 * Konfigurasi koneksi staging (sudah dinormalisasi).
 */
function seed_config(): array {
    $in_container = seed_is_container();

    return [
        'host' => seed_env_first(['MYSQL_HOST'], $in_container ? 'db' : '127.0.0.1'),
        'port' => seed_env_first(['MYSQL_PORT'], $in_container ? '3306' : '3310'),
        'db'   => seed_env_first(['MYSQL_DB', 'MYSQL_DATABASE', 'MYSQL_DATABASE_STAGING'], 'db_lms_staging'),
        'user' => seed_env_first(['MYSQL_USER', 'MYSQL_USER_STAGING'], 'lms_staging_user'),
        'pass' => seed_env_first(['MYSQL_PASSWORD', 'MYSQL_PASSWORD_STAGING'], ''),
    ];
}

/**
 * Guard: pastikan target benar-benar staging, bukan production.
 */
function seed_guard_config(array $cfg): void {
    $db = (string)$cfg['db'];

    if (stripos($db, 'staging') === false) {
        fwrite(STDERR, "ABORT: nama DB '$db' tidak mengandung 'staging'. Seeder hanya boleh jalan di staging.\n");
        exit(1);
    }

    // Tolak kalau ada env production yang menunjuk ke DB yang sama.
    $prod_db = getenv('PROD_DB') ?: getenv('MYSQL_DATABASE_PRODUCTION');
    if ($prod_db && $prod_db === $db) {
        fwrite(STDERR, "ABORT: DB target sama dengan production ($prod_db).\n");
        exit(1);
    }
}

/**
 * Koneksi PDO ke staging + verifikasi database aktif.
 */
function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = seed_config();
    seed_guard_config($cfg);

    if ($cfg['pass'] === '') {
        fwrite(STDERR, "ABORT: password DB staging kosong. Set MYSQL_PASSWORD / MYSQL_PASSWORD_STAGING, atau sediakan .env.staging.\n");
        exit(1);
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $cfg['host'],
        $cfg['port'],
        $cfg['db']
    );

    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        fwrite(STDERR, "GAGAL koneksi ke staging ({$cfg['host']}:{$cfg['port']}/{$cfg['db']}): " . $e->getMessage() . "\n");
        exit(1);
    }

    $actual = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if (stripos($actual, 'staging') === false) {
        fwrite(STDERR, "ABORT: terhubung ke database '$actual' yang bukan staging.\n");
        exit(1);
    }

    return $pdo;
}

// =====================================================================
// Helper transaksi
// =====================================================================

function begin_transaction(PDO $pdo): bool {
    return $pdo->beginTransaction() !== false;
}

function commit_transaction(PDO $pdo): bool {
    return $pdo->commit() !== false;
}

function rollback_transaction(PDO $pdo): bool {
    if ($pdo->inTransaction()) {
        return $pdo->rollBack() !== false;
    }
    return true;
}

// =====================================================================
// Helper umum
// =====================================================================

function hash_password(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Ambil satu kolom sebagai array dari query.
 */
function seed_col(PDO $pdo, string $sql, array $params = []): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Jalankan query & kembalikan baris pertama (atau null).
 */
function seed_row(PDO $pdo, string $sql, array $params = []): ?array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * DELETE ... WHERE col IN (...) dengan placeholder aman.
 * Mengembalikan jumlah baris terhapus (0 kalau daftar kosong).
 */
function seed_delete_in(PDO $pdo, string $table, string $col, array $ids): int {
    if (empty($ids)) {
        return 0;
    }
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM `$table` WHERE `$col` IN ($ph)");
    $stmt->execute($ids);
    return $stmt->rowCount();
}

// =====================================================================
// Pencarian & pembersihan data dummy
// =====================================================================

/**
 * Ambil ID baris bertanda dummy dari tabel tertentu.
 * Dipertahankan untuk kompatibilitas; purge_all_dummy() lebih lengkap.
 */
function find_dummy_ids(PDO $pdo, string $table): array {
    switch ($table) {
        case 'users':
            return seed_col($pdo, "SELECT id FROM users WHERE username LIKE ?", [DUMMY_USERNAME_PREFIX . '%']);
        case 'modules':
            return seed_col($pdo, "SELECT id FROM modules WHERE title LIKE ?", [DUMMY_TITLE_PREFIX . '%']);
        case 'tugas':
            return seed_col($pdo, "SELECT id FROM tugas WHERE judul LIKE ?", [DUMMY_TITLE_PREFIX . '%']);
        case 'kuis_soal':
            return seed_col($pdo, "SELECT id FROM kuis_soal WHERE pertanyaan LIKE ?", [DUMMY_TITLE_PREFIX . '%']);
        case 'kuis_sesi':
            return seed_col($pdo, "SELECT id FROM kuis_sesi WHERE kategori LIKE ?", [DUMMY_KATEGORI_PREFIX . '%']);
        case 'games':
            return seed_col($pdo, "SELECT id FROM games WHERE title LIKE ?", [DUMMY_TITLE_PREFIX . '%']);
        case 'checkpoint_modul':
            return seed_col($pdo, "SELECT id FROM checkpoint_modul WHERE modul_id IN (SELECT id FROM modules WHERE title LIKE ?)", [DUMMY_TITLE_PREFIX . '%']);
        default:
            return [];
    }
}

/**
 * Hapus SEMUA data dummy (users dummy_%, modules/tugas/kuis/games [DUMMY]%)
 * beserta seluruh turunannya, dalam satu transaksi.
 *
 * @return array<string,int> jumlah baris terhapus per tabel
 */
function purge_all_dummy(PDO $pdo): array {
    $report = [];

    // --- Kumpulkan ID dummy ---
    $user_ids   = find_dummy_ids($pdo, 'users');
    $module_ids = find_dummy_ids($pdo, 'modules');
    $tugas_ids  = find_dummy_ids($pdo, 'tugas');
    $soal_ids   = find_dummy_ids($pdo, 'kuis_soal');
    $game_ids   = find_dummy_ids($pdo, 'games');

    $sesi_ids = seed_col($pdo, "SELECT id FROM kuis_sesi WHERE kategori LIKE ?", [DUMMY_KATEGORI_PREFIX . '%']);
    if (!empty($user_ids)) {
        $ph = implode(',', array_fill(0, count($user_ids), '?'));
        $extra = seed_col($pdo, "SELECT DISTINCT sesi_id FROM kuis_hasil WHERE sesi_id IS NOT NULL AND user_id IN ($ph)", $user_ids);
        $sesi_ids = array_merge($sesi_ids, $extra);
    }
    $sesi_ids = array_values(array_unique(array_map('intval', $sesi_ids)));

    $checkpoint_modul_ids = seed_col(
        $pdo,
        "SELECT id FROM checkpoint_modul WHERE modul_id IN (SELECT id FROM modules WHERE title LIKE ?)",
        [DUMMY_TITLE_PREFIX . '%']
    );

    $hasil_ids = [];
    if (!empty($user_ids)) {
        $ph = implode(',', array_fill(0, count($user_ids), '?'));
        $hasil_ids = array_merge($hasil_ids, seed_col($pdo, "SELECT id FROM kuis_hasil WHERE user_id IN ($ph)", $user_ids));
    }
    if (!empty($sesi_ids)) {
        $ph = implode(',', array_fill(0, count($sesi_ids), '?'));
        $hasil_ids = array_merge($hasil_ids, seed_col($pdo, "SELECT id FROM kuis_hasil WHERE sesi_id IN ($ph)", $sesi_ids));
    }
    $hasil_ids = array_values(array_unique(array_map('intval', $hasil_ids)));

    $pengumpulan_ids = [];
    if (!empty($user_ids)) {
        $ph = implode(',', array_fill(0, count($user_ids), '?'));
        $pengumpulan_ids = array_merge($pengumpulan_ids, seed_col($pdo, "SELECT id FROM pengumpulan_tugas WHERE user_id IN ($ph)", $user_ids));
    }
    if (!empty($tugas_ids)) {
        $ph = implode(',', array_fill(0, count($tugas_ids), '?'));
        $pengumpulan_ids = array_merge($pengumpulan_ids, seed_col($pdo, "SELECT id FROM pengumpulan_tugas WHERE tugas_id IN ($ph)", $tugas_ids));
    }

    // --- Hapus (child -> parent) dalam transaksi ---
    begin_transaction($pdo);
    try {
        // kuis_jawaban_isian: tergantung hasil & soal
        $jawaban_ids = [];
        if (!empty($hasil_ids)) {
            $ph = implode(',', array_fill(0, count($hasil_ids), '?'));
            $jawaban_ids = array_merge($jawaban_ids, seed_col($pdo, "SELECT id FROM kuis_jawaban_isian WHERE hasil_id IN ($ph)", $hasil_ids));
        }
        if (!empty($soal_ids)) {
            $ph = implode(',', array_fill(0, count($soal_ids), '?'));
            $jawaban_ids = array_merge($jawaban_ids, seed_col($pdo, "SELECT id FROM kuis_jawaban_isian WHERE soal_id IN ($ph)", $soal_ids));
        }
        $report['kuis_jawaban_isian'] = seed_delete_in($pdo, 'kuis_jawaban_isian', 'id', $jawaban_ids);

        // anak kuis_sesi
        $report['kuis_sesi_kelas']  = seed_delete_in($pdo, 'kuis_sesi_kelas', 'sesi_id', $sesi_ids);
        $report['kuis_sesi_materi'] = seed_delete_in($pdo, 'kuis_sesi_materi', 'sesi_id', $sesi_ids);

        // kuis_hasil
        $report['kuis_hasil'] = seed_delete_in($pdo, 'kuis_hasil', 'id', $hasil_ids);

        // checkpoint
        $report['checkpoint_hasil'] = 0;
        if (!empty($user_ids) || !empty($module_ids)) {
            $conds = [];
            $params = [];
            if (!empty($user_ids)) {
                $conds[] = 'user_id IN (' . implode(',', array_fill(0, count($user_ids), '?')) . ')';
                $params = array_merge($params, $user_ids);
            }
            if (!empty($module_ids)) {
                $conds[] = 'modul_id IN (' . implode(',', array_fill(0, count($module_ids), '?')) . ')';
                $params = array_merge($params, $module_ids);
            }
            $stmt = $pdo->prepare('DELETE FROM checkpoint_hasil WHERE ' . implode(' OR ', $conds));
            $stmt->execute($params);
            $report['checkpoint_hasil'] = $stmt->rowCount();
        }
        $report['checkpoint_modul'] = seed_delete_in($pdo, 'checkpoint_modul', 'id', $checkpoint_modul_ids);

        // notifikasi
        $report['notifikasi_modul'] = 0;
        if (!empty($user_ids) || !empty($module_ids)) {
            $conds = [];
            $params = [];
            if (!empty($user_ids)) {
                $conds[] = 'user_id IN (' . implode(',', array_fill(0, count($user_ids), '?')) . ')';
                $params = array_merge($params, $user_ids);
            }
            if (!empty($module_ids)) {
                $conds[] = 'modul_id IN (' . implode(',', array_fill(0, count($module_ids), '?')) . ')';
                $params = array_merge($params, $module_ids);
            }
            $stmt = $pdo->prepare('DELETE FROM notifikasi_modul WHERE ' . implode(' OR ', $conds));
            $stmt->execute($params);
            $report['notifikasi_modul'] = $stmt->rowCount();
        }

        // pengumpulan tugas
        $report['pengumpulan_tugas'] = seed_delete_in($pdo, 'pengumpulan_tugas', 'id', $pengumpulan_ids);

        // kuis sesi & soal
        $report['kuis_sesi'] = seed_delete_in($pdo, 'kuis_sesi', 'id', $sesi_ids);
        $report['kuis_soal_alternatif_isian'] = seed_delete_in($pdo, 'kuis_soal_alternatif_isian', 'soal_id', $soal_ids);
        $report['kuis_soal'] = seed_delete_in($pdo, 'kuis_soal', 'id', $soal_ids);

        // tugas
        $report['tugas_kelas'] = seed_delete_in($pdo, 'tugas_kelas', 'tugas_id', $tugas_ids);
        $report['tugas'] = seed_delete_in($pdo, 'tugas', 'id', $tugas_ids);

        // games, modules, users
        $report['games'] = seed_delete_in($pdo, 'games', 'id', $game_ids);
        $report['modules'] = seed_delete_in($pdo, 'modules', 'id', $module_ids);
        $report['users'] = seed_delete_in($pdo, 'users', 'id', $user_ids);

        commit_transaction($pdo);
    } catch (Throwable $e) {
        rollback_transaction($pdo);
        fwrite(STDERR, "GAGAL membersihkan data dummy: " . $e->getMessage() . "\n");
        exit(1);
    }

    return $report;
}
