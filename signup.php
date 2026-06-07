<?php
require 'koneksi.php'; 

$pesan = "";

// KUNCI RAHASIA: Setel token sak karepmu, misal diganti saben mlebu kelas
$token_sah = "Sija2026"; 

if (isset($_POST['register'])) {
    
    $username    = trim($_POST['username']);
    $password    = $_POST['password'];
    $token_input = trim($_POST['token']); // Njupuk inputan token soko arek-arek
    $role        = 'siswa'; 

    // VALIDASI 1: Cek opo token sing dilebokno arek cocok karo kunci rahasia
    if ($token_input !== $token_sah) {
        $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Gagal! Token salah!</div>";
    } else {
        try {
            // VALIDASI 2: Cek username opo wis ono sing duwe
            $stmt_cek = $pdo->prepare("SELECT username FROM users WHERE username = :username");
            $stmt_cek->execute(['username' => $username]);
            
            if ($stmt_cek->rowCount() > 0) {
                $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Username sudah terdaftar!</div>";
            } else {
                // VALIDASI 3: Enkripsi password
                $password_aman = password_hash($password, PASSWORD_DEFAULT);

                // VALIDASI 4: Eksekusi SQL Insert
                $sql_insert = "INSERT INTO users (username, password, role, created_at) 
                               VALUES (:username, :password, :role, NOW())";
                
                $stmt_insert = $pdo->prepare($sql_insert);
                $eksekusi = $stmt_insert->execute([
                    'username' => $username,
                    'password' => $password_aman,
                    'role'     => $role
                ]);

                if ($eksekusi) {
                    $pesan = "<div style='color: #00ff66; margin-bottom: 15px;'>Akun siswa sukses dibuat. Silahkan kembali ke halaman Login!</div>";
                }
            }
        } catch (PDOException $e) {
            $pesan = "<div style='color: #ff3333; margin-bottom: 15px;'>Gagal Error: " . $e->getMessage() . "</div>";
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