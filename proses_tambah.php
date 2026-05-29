<?php
// 1. Amankan session dan include koneksi database
session_start();
include 'koneksi.php'; // $pdo otomatis aktif

// Cek autentikasi login admin
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

// 2. Cek kiriman form via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Ambil data teks dan link dari form input
    $nama_modul = trim($_POST['nama_modul'] ?? '');
    $kategori   = trim($_POST['kategori'] ?? '');
    $link_drive = trim($_POST['link_drive'] ?? ''); // Menangkap inputan URL link Google Drive
    $desc       = trim($_POST['desc'] ?? '');

    // Validasi: Pastikan tidak ada data penting yang kosong
    if (empty($nama_modul) || empty($kategori) || empty($link_drive) || empty($desc)) {
        echo "<script>
                alert('Aduh Pak, semua kolom (termasuk Link Drive) wajib diisi!');
                window.history.back();
              </script>";
        exit();
    }

    // Validasi format URL (memastikan admin beneran input link, bukan teks asal)
    if (!filter_var($link_drive, FILTER_VALIDATE_URL)) {
        echo "<script>
                alert('Pak, inputan harus berupa URL Link yang valid! (Contoh: https://drive.google.com/...)');
                window.history.back();
              </script>";
        exit();
    }

    // 3. Eksekusi INSERT ke database MariaDB via PDO Prepared Statements
    try {
        // Sesuaikan nama kolom database Bapak (misal nama kolomnya: 'link_modul' atau 'file')
        $sql = "INSERT INTO modules (title, category, description, file_path) VALUES (:nama_modul, :kategori, :desc, :link_drive)";
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            ':nama_modul' => $nama_modul,
            ':kategori'   => $kategori,
            ':desc'       => $desc,
            ':link_drive' => $link_drive // Yang disimpan murni string text link URL-nya
        ]);

        echo "<script>
                alert('Mantap Pak Komandan! Link Modul Drive berhasil dicatat!');
                window.location.href = 'manage_modul.php';
              </script>";
        exit();

    } catch (PDOException $e) {
        die("Gagal menyimpan link ke MariaDB, Pak! Karena: " . $e->getMessage());
    }

} else {
    header("Location: index.php");
    exit();
}
?>