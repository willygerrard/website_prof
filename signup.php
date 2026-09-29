<?php
// ============================================================
// Session (inline, tanpa file eksternal)
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 7200;
    ini_set('session.gc_maxlifetime', $lifetime);
    ini_set('session.cookie_lifetime', $lifetime);

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require 'koneksi.php';

$pesan = "";

// Daftar kelas yang sah, sinkron dengan <select> di HTML
$daftar_kelas_sah = [
    'X TKJ 1', 'X TKJ 2', 'X TKJ 3', 'X TKJ 4',
    'XI SIJA', 'XII SIJA', 'XIII SIJA',
];

// ============================================================
// Helper: normalisasi nama untuk cek duplikat
// "Budi   SANTOSO." -> "budi santoso"
// (tidak pakai return type nullable & aman kalau mbstring mati)
// ============================================================
function normalize_nama($nama) {
    $nama = trim((string)$nama);
    $nama = preg_replace('/\s+/u', ' ', $nama);
    if (function_exists('mb_strtolower')) {
        $nama = mb_strtolower($nama, 'UTF-8');
    } else {
        $nama = strtolower($nama);
    }
    return trim($nama, ".,;:!?\"' ");
}

// ============================================================
// Cari siswa AKTIF dengan nama sama (setelah normalisasi).
// Dibungkus try-catch sendiri: kalau kolom `status` belum ada
// atau query gagal, cek dilewati (dicatat di log) dan
// signup tetap jalan, tidak 500.
// ============================================================
function cari_nama_kembar($pdo, $nama_normalized) {
    try {
        $stmt = $pdo->query("
            SELECT nama_asli, kelas
            FROM users
            WHERE role = 'siswa' AND status = 'aktif'
        ");
        if ($stmt === false) {
            return null;
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (normalize_nama($row['nama_asli']) === $nama_normalized) {
                return $row;
            }
        }
    } catch (Throwable $e) {
        error_log('DB Error [signup cek nama kembar]: ' . $e->getMessage());
    }
    return null;
}

// ============================================================
// Baca status & token dari tabel pengaturan
// ============================================================
$registrasi_status = 'tutup';
$token_sah         = 'gak berlaku';

try {
    $stmt_status = $pdo->query("SELECT `value` FROM pengaturan WHERE `key` = 'registrasi_status'");
    if ($stmt_status !== false) {
        $val = $stmt_status->fetchColumn();
        if ($val !== false && $val !== null) {
            $registrasi_status = $val;
        }
    }

    $stmt_token = $pdo->query("SELECT `value` FROM pengaturan WHERE `key` = 'registrasi_token_sekarang'");
    if ($stmt_token !== false) {
        $val = $stmt_token->fetchColumn();
        if ($val !== false && $val !== null) {
            $token_sah = $val;
        }
    }
} catch (Throwable $e) {
    error_log('DB Error [signup pengaturan]: ' . $e->getMessage());
    // default: tutup + token gak berlaku
}

// ============================================================
// Proses submit
// ============================================================
if (isset($_POST['register'])) {

    $csrf_input = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], (string)$csrf_input)) {
        $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Sesi form tidak valid. Silakan muat ulang halaman.</div>";
    } else {

        $username    = trim($_POST['username']   ?? '');
        $nama_asli   = trim($_POST['nama_asli']  ?? '');
        $password    = $_POST['password']        ?? '';
        $no_wa_ortu  = trim($_POST['no_wa_ortu'] ?? '');
        $token_input = trim($_POST['token']      ?? '');
        $kelas       = trim($_POST['kelas']      ?? '');
        $role        = 'siswa';

        $no_wa_bersih = preg_replace('/[^0-9]/', '', $no_wa_ortu);

        // Validasi bertingkat
        if ($registrasi_status !== 'buka') {
            $pesan = "<div style='color: #ff9933; margin-bottom: 15px;'>⚠️ Pendaftaran sedang ditutup. Silakan hubungi guru pembimbing jika ingin mendaftar.</div>";
        } elseif ($nama_asli === '') {
            $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Nama lengkap wajib diisi!</div>";
        } elseif ($username === '') {
            $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Username wajib diisi!</div>";
        } elseif ($password === '') {
            $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Password wajib diisi!</div>";
        } elseif (!hash_equals((string)$token_sah, $token_input)) {
            $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Gagal! Token salah!</div>";
        } elseif (!in_array($kelas, $daftar_kelas_sah, true)) {
            $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Kelas tidak valid. Pilih dari daftar.</div>";
        } elseif (!preg_match('/^(08|62)[0-9]{8,12}$/', $no_wa_bersih)) {
            $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Format nomor WA tidak valid. Contoh: 081234567890</div>";
        } else {
            try {
                // 1. Cek nama kembar (hanya siswa AKTIF)
                $duplikat = cari_nama_kembar($pdo, normalize_nama($nama_asli));

                // 2. Cek username duplikat
                $stmt_cek = $pdo->prepare("SELECT 1 FROM users WHERE username = :username LIMIT 1");
                $stmt_cek->execute(['username' => $username]);
                $username_sudah_ada = ($stmt_cek->fetchColumn() !== false);

                if ($duplikat) {
                    $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>
                        ⚠️ Nama <strong>" . htmlspecialchars($nama_asli) . "</strong>
                        sudah terdaftar di kelas <strong>" . htmlspecialchars($duplikat['kelas']) . "</strong>.<br>
                        <small style='color:#ffcc00;'>Kalau kamu siswa baru dengan nama persis sama
                        (mis. saudara kembar) atau merasa belum pernah daftar,
                        lapor ke guru untuk pendaftaran manual.</small>
                    </div>";
                } elseif ($username_sudah_ada) {
                    $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Username sudah terdaftar!</div>";
                } else {
                    $password_aman = password_hash($password, PASSWORD_DEFAULT);

                    $sql_insert = "INSERT INTO users (username, nama_asli, password, no_wa_ortu, kelas, role, created_at)
                                   VALUES (:username, :nama_asli, :password, :no_wa_ortu, :kelas, :role, NOW())";

                    $stmt_insert = $pdo->prepare($sql_insert);
                    $eksekusi = $stmt_insert->execute([
                        'username'   => $username,
                        'nama_asli'  => $nama_asli,
                        'password'   => $password_aman,
                        'no_wa_ortu' => $no_wa_bersih,
                        'kelas'      => $kelas,
                        'role'       => $role,
                    ]);

                    if ($eksekusi) {
                        $pesan = "<div style='color: #00ff66; margin-bottom: 15px;'>Akun siswa sukses dibuat. Silahkan kembali ke halaman Login!</div>";
                    } else {
                        $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Gagal menyimpan data. Coba lagi.</div>";
                    }
                }
            } catch (PDOException $e) {
                // 23000 = pelanggaran UNIQUE (dua orang daftar username sama bersamaan)
                if ($e->getCode() === '23000') {
                    $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Username sudah terdaftar!</div>";
                } else {
                    error_log('DB Error [signup]: ' . $e->getMessage());
                    $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Terjadi kesalahan pada sistem. Silakan hubungi administrator.</div>";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up Member Baru</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #121212; color: #fff; display: flex; min-height: 100vh; align-items: center; justify-content: center; margin: 0; padding: 20px 0; box-sizing: border-box; }
        .card { background: #1e1e1e; padding: 30px; border-radius: 8px; width: 100%; max-width: 360px; border: 1px solid #333; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }
        h2 { margin-top: 0; color: #00cc66; text-align: center; }
        label { font-size: 14px; color: #ccc; }
        input, select { width: 100%; padding: 10px; margin: 8px 0 20px 0; border-radius: 4px; border: 1px solid #555; background: #222; color: #fff; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #00cc66; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 16px; }
        button:hover { background: #009955; }
        .back-link { text-align: center; margin-top: 15px; font-size: 14px; }
        .back-link a { color: #aaa; text-decoration: none; }
        .back-link a:hover { color: #fff; }
        .hint { font-size: 12px; color: #888; margin-top: -16px; margin-bottom: 16px; display: block; }
    </style>
</head>
<body>

<div class="card">
    <h2>Registrasi Siswa</h2>

    <!-- Panggonan nampilno notifikasi -->
    <?= $pesan; ?>

    <form action="" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <label>Nama Lengkap:</label>
        <input type="text" name="nama_asli" placeholder="Sesuai nama di rapor/absensi..." required autocomplete="off">
        <span class="hint">Tulis nama lengkap. Kalau nama sama persis dengan siswa lain, hubungi guru.</span>

        <label>Username Baru:</label>
        <input type="text" name="username" placeholder="Masukkan username..." required autocomplete="off">
        <span class="hint">Boleh beda dari nama asli, dipakai untuk login saja.</span>

        <label>Password:</label>
        <input type="password" name="password" placeholder="******" required>

        <label for="kelas">Kelas:</label>
        <select name="kelas" id="kelas" required>
            <option value="">-- Pilih Kelas --</option>
            <option value="X TKJ 1">X TKJ 1</option>
            <option value="X TKJ 2">X TKJ 2</option>
            <option value="X TKJ 3">X TKJ 3</option>
            <option value="X TKJ 4">X TKJ 4</option>
            <option value="XI SIJA">XI SIJA</option>
            <option value="XII SIJA">XII SIJA</option>
            <option value="XIII SIJA">XIII SIJA</option>
        </select>

        <label>No. WhatsApp Orang Tua/Wali:</label>
        <input type="text" name="no_wa_ortu" placeholder="Contoh: 081234567890" required>
        <span class="hint">Untuk notifikasi progress belajar ke orang tua.</span>

        <label style="color: #ffcc00; font-weight: bold;">Token Akses Pendaftaran:</label>
        <input type="text" name="token" placeholder="Ketik kode dari papan tulis lab..." required autocomplete="off">

        <button type="submit" name="register">GASS DAFTAR AKUN</button>
    </form>

    <div class="back-link">
        <a href="login.php">← Kembali ke halaman login</a>
    </div>
</div>

</body>
</html>