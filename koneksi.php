<?php
/**
 * koneksi_baru.php
 * Versi aman untuk koneksi database tanpa menulis kredensial sensitif secara langsung.
 * Nilai koneksi dibaca dari environment variable atau file .env.
 */

function load_env_file($path) {
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $vars = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");

        if ($key !== '') {
            $vars[$key] = $value;
        }
    }

    return $vars;
}

$env_path = __DIR__ . '/.env';
if (!is_file($env_path)) {
    $env_path = dirname(__DIR__) . '/.env';
}
$env = load_env_file($env_path);

$host = getenv('MYSQL_HOST') ?: ($env['MYSQL_HOST'] ?? '');
$db   = getenv('MYSQL_DATABASE') ?: ($env['MYSQL_DATABASE'] ?? '');
$user = getenv('MYSQL_USER') ?: ($env['MYSQL_USER'] ?? '');
$pass = getenv('MYSQL_PASSWORD') ?: ($env['MYSQL_PASSWORD'] ?? '');
$port = getenv('MYSQL_PORT') ?: ($env['MYSQL_PORT'] ?? '3306');

if ($host === '' || $db === '' || $user === '' || $pass === '') {
    error_log('Konfigurasi database belum lengkap. Pastikan variabel MYSQL_HOST, MYSQL_DATABASE, MYSQL_USER, dan MYSQL_PASSWORD tersedia.');
    die('Aduh Pak, konfigurasi database belum lengkap ❌ Silakan hubungi administrator.');
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;port=$port;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Koneksi database GAGAL: ' . $e->getMessage());
    die('Aduh Pak, koneksi database GAGAL ❌ Silakan hubungi administrator.');
}

/**
 * db_error(PDOException $e)
 * Helper untuk menangani error database.
 * - Mencatat detail error ke server log (untuk debugging, tidak tampil ke user).
 * - Menampilkan pesan generik ke pengguna (tidak membocorkan detail DB).
 */
function db_error(PDOException $e) {
    error_log('DB Error [' . date('Y-m-d H:i:s') . ']: ' . $e->getMessage());
    die('Terjadi kesalahan pada sistem. Silakan hubungi administrator.');
}
?>
