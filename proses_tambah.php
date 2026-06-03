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
    $jenis      = trim($_POST['jenis'] ?? '');

    // Validasi: Pastikan tidak ada data penting yang kosong
    if (empty($nama_modul) || empty($kategori) || empty($jenis) || empty($link_drive) || empty($desc)) {
        echo "<script>
                alert('Aduh Pak, semua kolom (termasuk Link Drive) wajib diisi!');
                window.history.back();
              </script>";
        exit();
    }

    // Validasi format URL (memastikan admin beneran input link, bukan teks asal)
    if (!filter_var($link_drive, FILTER_VALIDATE_URL)) {
        echo "<script>
                alert('Pak, inputan harus berupa URL Link yang valid! (Contoh: https://drive.google.com/...');
                window.history.back();
              </script>";
        exit();
    }

    // ==================== WAJIB GANTI DADI SATU WADAH IKI ====================

if ($jenis == 'video') {
    // 1. Nek milih Video, langsung dikunci ing kene (aman teko gangguan upload)
    $nama_file_db = 'img/default_video.png'; 

} elseif ($jenis == 'media') {
    // 2. Nek milih Media PPT, langsung dikunci ing kene
    $nama_file_db = 'img/default_media.png'; 

} else {
    // 3. JALUR UTAMA MODUL (PDF): Default awal diset dadi debian.webp yen gak upload gambar
    $nama_file_db = 'img/debian.webp'; 

    // Proses upload file custom mung mlaku KANDHI KHUSUS ing njero kene wae!
    if (isset($_FILES['image_path']) && $_FILES['image_path']['error'] === UPLOAD_ERR_OK) {
        $file_tmp       = $_FILES['image_path']['tmp_name'];
        $file_name      = $_FILES['image_path']['name'];
        $file_ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $ekstensi_boleh = ['png', 'jpg', 'jpeg'];

        if (in_array($file_ext, $ekstensi_boleh)) {
            $nama_file_baru = 'icon_' . time() . '_' . uniqid() . '.' . $file_ext;

            if (move_uploaded_file($file_tmp, 'img/' . $nama_file_baru)) {
                // TIMPA dadi nama acak murni khusus kanggo modul sing sukses upload!
                $nama_file_db = 'img/' . $nama_file_baru;
            }
        }
    }
} // <--- TUTUP WADAH UTAMA

// =========================================================================

    // 3. Eksekusi INSERT ke database MariaDB via PDO Prepared Statements
    try {
        $sql = "INSERT INTO modules (title, category, description, image_path, file_path, jenis_resource) 
                VALUES (:nama_modul, :kategori, :desc, :image_path, :link_drive, :jenis)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nama_modul' => $nama_modul,
            ':kategori'   => $kategori,
            ':desc'       => $desc,
            ':image_path' => $nama_file_db, // <-- Masuk ke DB: img/icon_xxx.png
            ':link_drive' => $link_drive,
            ':jenis'      => $jenis
        ]);

        echo "<script>alert('Mantap Pak Komandan! Modul baru berhasil dicatat!'); window.location.href = 'gerbang-rahasia-sija';</script>";
        exit();

    } catch (PDOException $e) {
        die("Gagal menyimpan ke MariaDB, Pak! Karena: " . $e->getMessage());
    }

} else {
    header("Location: index.php");
    exit();
}
?>