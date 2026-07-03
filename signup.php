<?php
require 'koneksi.php'; 

$pesan = "";

// KUNCI RAHASIA: Setel token sak karepmu, misal diganti saben mlebu kelas
$token_sah = "Sija2026"; 

if (isset($_POST['register'])) {
    
    $username    = trim($_POST['username']);
    $password    = $_POST['password'];
    $no_wa_ortu  = trim($_POST['no_wa_ortu']);
    $token_input = trim($_POST['token']);
    $kelas       = trim($_POST['kelas']);
    $role        = 'siswa'; 

    // Normalisasi nomor WA (hapus spasi, strip, dst)
    $no_wa_bersih = preg_replace('/[^0-9]/', '', $no_wa_ortu);

    if ($token_input !== $token_sah) {
        $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Gagal! Token salah!</div>";
    } elseif (!preg_match('/^(08|62)[0-9]{8,12}$/', $no_wa_bersih)) {
        $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Format nomor WA tidak valid. Contoh: 081234567890</div>";
    } else {
        try {
            $stmt_cek = $pdo->prepare("SELECT username FROM users WHERE username = :username");
            $stmt_cek->execute(['username' => $username]);
            
            if ($stmt_cek->rowCount() > 0) {
                $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Username sudah terdaftar!</div>";
            } else {
                $password_aman = password_hash($password, PASSWORD_DEFAULT);

                $sql_insert = "INSERT INTO users (username, password, no_wa_ortu, kelas, role, created_at) 
                               VALUES (:username, :password, :no_wa_ortu, :kelas, :role, NOW())";
                
                $stmt_insert = $pdo->prepare($sql_insert);
                $eksekusi = $stmt_insert->execute([
                    'username'    => $username,
                    'password'    => $password_aman,
                    'no_wa_ortu'  => $no_wa_bersih,
                    'kelas'       => $kelas,
                    'role'        => $role
                ]);

                if ($eksekusi) {
                    $pesan = "<div style='color: #00ff66; margin-bottom: 15px;'>Akun siswa sukses dibuat. Silahkan kembali ke halaman Login!</div>";
                }
            }
        } catch (PDOException $e) {
            $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Gagal Error: " . htmlspecialchars($e->getMessage()) . "</div>";
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
        body { font-family: 'Segoe UI', sans-serif; background: #121212; color: #fff; display: flex; height: 100vh; align-items: center; justify-content: center; margin: 0; }
        .card { background: #1e1e1e; padding: 30px; border-radius: 8px; width: 100%; max-width: 360px; border: 1px solid #333; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }
        h2 { margin-top: 0; color: #00cc66; text-align: center; }
        label { font-size: 14px; color: #ccc; }
        input { width: 100%; padding: 10px; margin: 8px 0 20px 0; border-radius: 4px; border: 1px solid #555; background: #222; color: #fff; box-sizing: border-box; }
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
        <label>Username Baru:</label>
        <input type="text" name="username" placeholder="Masukkan username..." required autocomplete="off">

        <label>Password:</label>
        <input type="password" name="password" placeholder="******" required>

        <label for="kelas">Kelas:</label>
        <select name="kelas" id="kelas" required>
            <option value="">-- Pilih Kelas --</option>
            <!-- Kelas X -->
            <option value="X TKJ 1">X TKJ 1</option>
            <option value="X TKJ 2">X TKJ 2</option>
            <option value="X SIJA">X SIJA</option>
            
            <!-- Kelas XI -->
            <option value="XI TKJ 1">XI TKJ 1</option>
            <option value="XI TKJ 2">XI TKJ 2</option>
            <option value="XI SIJA">XI SIJA</option>
            
            <!-- Kelas XII -->
            <option value="XII TKJ 1">XII TKJ 1</option>
            <option value="XII SIJA">XII SIJA</option>
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