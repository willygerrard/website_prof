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

    // --- PROSES AMBIL DATA FILE GAMBAR ---
    // Default nama file di DB kalau user tidak upload gambar
    $nama_file_db = 'img/default_icon.png'; 

    if (isset($_FILES['image_path']) && $_FILES['image_path']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['image_path']['tmp_name'];
        $file_name = $_FILES['image_path']['name'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $ekstensi_boleh = ['png', 'jpg', 'jpeg'];

        if (in_array($file_ext, $ekstensi_boleh)) {
            // Generate nama file acak yang unik
            $nama_file_baru = 'icon_' . time() . '_' . uniqid() . '.' . $file_ext;
            
            // Pindahkan file fisik ke folder img/ server Linux
            if (move_uploaded_file($file_tmp, 'img/' . $nama_file_baru)) {
                // Trik Hulu: Kita gandeng teks 'img/' ke variabel database
                $nama_file_db = 'img/' . $nama_file_baru;
            }
        }
    }

    // 3. Eksekusi INSERT ke database MariaDB via PDO Prepared Statements
    try {
        $sql = "INSERT INTO modules (title, category, description, image_path, file_path) 
                VALUES (:nama_modul, :kategori, :desc, :image_path, :link_drive)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nama_modul' => $nama_modul,
            ':kategori'   => $kategori,
            ':desc'       => $desc,
            ':image_path' => $nama_file_db, // <-- Masuk ke DB: img/icon_xxx.png
            ':link_drive' => $link_drive
        ]);

        echo "<script>alert('Mantap Pak Komandan! Modul baru berhasil dicatat!'); window.location.href = 'management_modul.php';</script>";
        exit();

    } catch (PDOException $e) {
        die("Gagal menyimpan ke MariaDB, Pak! Karena: " . $e->getMessage());
    }

} else {
    header("Location: index.php");
    exit();
}
?>