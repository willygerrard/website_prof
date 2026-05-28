<?php
$host     = 'db'; // Nama service di docker-compose Bapak
$db       = 'db_website_pribadi'; // Sesuaikan dengan nama DB kemarin
$user     = 'willy'; // Atau user yang Bapak buat
$password = 'RahasiaPro2026!'; // Sandi MariaDB kemarin

try {
    $koneksi = new PDO("mysql:host=$host;dbname=$db", $user, $password);
    $koneksi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Status: Koneksi ke MariaDB Docker SUKSES, Pak! 🎉";
} catch (PDOException $e) {
    echo "Status: Koneksi GAGAL ❌ Karena: " . $e->getMessage();
}
?>