<?php
// 1. Koneksi ke database MariaDB via PDO (Sesuaikan dengan file koneksi Bapak)
include 'koneksi.php'; 

$id = $_GET['id'] ?? '';

if (!empty($id)) {
    try {
        // 2. Ambil nama file gambar dari database dulu sebelum datanya dihapus
        $sql_select = "SELECT image_path FROM modules WHERE id = :id";
        $stmt_select = $pdo->prepare($sql_select);
        $stmt_select->execute([':id' => $id]);
        $modul = $stmt_select->fetch(PDO::FETCH_ASSOC);

        if ($modul) {
            $file_gambar = $modul['image_path']; // Isinya sudah otomatis: img/icon_xxxx.png

            // 3. Eksekusi Penghapusan File Fisik di Server Linux
            // Karena di DB sudah ada teks 'img/', kita tidak perlu gandeng teks 'img/' lagi di fungsi unlink!
            if (!empty($file_gambar) && $file_gambar !== 'img/default_icon.png' && file_exists($file_gambar)) {
                unlink($file_gambar); // Langsung menghapus file fisik di dalam folder img/
            }

            // 4. Hapus Data Modul dari Database MariaDB
            $sql_delete = "DELETE FROM modules WHERE id = :id";
            $stmt_delete = $pdo->prepare($sql_delete);
            $stmt_delete->execute([':id' => $id]);

            echo "<script>
                    alert('Modul dan file ikonnya berhasil dihapus, Pak!');
                    window.location.href = 'management_modul.php';
                  </script>";
            exit();
        } else {
            echo "<script>alert('Data modul tidak ditemukan, Pak!'); window.location.href = 'management_modul.php';</script>";
        }

    } catch (PDOException $e) {
        die("Waduh, Query Hapus Gagal karena: " . $e->getMessage());
    }
} else {
    header("Location: management_modul.php");
    exit();
}
?>