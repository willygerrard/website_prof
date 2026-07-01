<?php
session_start();
include 'koneksi.php';
// 1. Proteksi Halaman
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

// 2. Tarik Data Lama Berdasarkan ID dari URL
$id = $_GET['id'] ?? '';
if (empty($id)) {
    header("Location: management_modul.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM modules WHERE id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) {
    die("Aduh Pak, data modul dengan ID tersebut tidak ditemukan!");
}

// 3. Proses Update Data saat Form di-Submit (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category   = trim($_POST['category'] ?? '');
    $description       = trim($_POST['description'] ?? '');
    $file_path = trim($_POST['file_path'] ?? '');
    
    // 1. Ambil data lama dari database terlebih dahulu (Asumsi Bapak sudah melakukan SELECT dan disimpan di variabel $data)
    // Defaultnya, pakai nama file gambar lama yang ada di DB saat ini (isinya sudah mengandung 'img/...')
    $nama_file_db = $data['image_path'] ?? 'img/default_icon.png';
    
    
 // 2. Cek apakah user mengupload file gambar baru lewat input file
if (isset($_FILES['image_path']) && !empty($_FILES['image_path']['name'])) {
    $file_tmp  = $_FILES['image_path']['tmp_name'];
    $file_name = $_FILES['image_path']['name'];
    $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $ekstensi_boleh = ['png', 'jpg', 'jpeg'];

    if (in_array($file_ext, $ekstensi_boleh)) {
        
        // --- REM DARURAT HAPUS FILE LAMA ---
        // Karena di DB string-nya sudah rapi mengandung 'img/nama_file.png', 
        // KITA LANGSUNG cek file_exists($nama_file_db) TANPA perlu menggandeng 'img/' lagi!
        if (strpos($nama_file_db, 'default') === false && strpos($nama_file_db, 'debian') === false) {
    
            if (!empty($nama_file_db) && file_exists($nama_file_db)) {
                unlink($nama_file_db); // Aman satus persen, file master gak bakal ilang!
            }
        }
        // Generate nama ikon baru agar unik
        $nama_file_baru = 'icon_' . time() . '_' . uniqid() . '.' . $file_ext;
        
        // Pindahkan file fisik baru ke folder img/
        if (move_uploaded_file($file_tmp, 'img/' . $nama_file_baru)) {
            // Trik Hulu: Isinya kita perbarui dengan format lengkap teks 'img/'
            $nama_file_db = 'img/' . $nama_file_baru;
        }
    } else {
        echo "<script>alert('Format file salah Pak! Harus png, jpg, atau jpeg.');</script>";
    }
}

// 3. EKSEKUSI UPDATE QUERY KE MARIADB VIA PDO
try {
    // Pastikan kolom image_path ikut di-SET di dalam query UPDATE Bapak
    $sql = "UPDATE modules 
            SET title = :title, 
                category = :category, 
                `description` = :description, 
                image_path = :image_path, 
                file_path = :file_path 
            WHERE id = :id";

    $stmt_update = $pdo->prepare($sql);
    $stmt_update->execute([
        ':title'       => $title,       // Ambil dari trim($_POST['title']) Bapak
        ':category'    => $category,    // Ambil dari trim($_POST['category']) Bapak
        ':description' => $description, // Ambil dari trim($_POST['description']) Bapak
        ':image_path'  => $nama_file_db, // <-- Masuk DB: Tetap aman 'img/icon_xxx.png' baik ganti maupun tidak
        ':file_path'   => $file_path,   // Ambil dari trim($_POST['file_path']) Bapak
        ':id'          => $id           // Ambil dari $_GET['id'] atau $_POST['id'] Bapak
    ]);

    echo "<script>
            alert('Data modul resmi diperbarui!');
            window.location.href = 'index.php';
          </script>";
    exit();

    } catch (PDOException $e) {
    die("Gagal Update ke MariaDB karena: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Modul - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .hero-banner {
            background: linear-gradient(rgba(15, 23, 42, 0.85), rgba(15, 23, 42, 0.85)), url('assets/img/server-bg.jpg') no-repeat center center;
            background-size: cover;
            color: white;
            padding: 60px 0;
            text-align: center;
        }
    </style>
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top py-3">
        <div class="container">
            <a class="navbar-brand fw-bold text-dark" href="index.php">Modul Pembelajaran SIJA</a>
            <div class="ms-auto d-flex align-items-center gap-3">
                 <?php if (isset($_SESSION['username'])) : ?>
        <span class="text-secondary fw-medium d-none d-md-inline small">
            👋 Hai, <strong class="text-dark"><?= htmlspecialchars($_SESSION['username']); ?></strong>
        </span>
    <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="hero-banner shadow-sm">
        <div class="container">
            <h1 class="display-5 fw-bold">Pusat Pembelajaran SIJA</h1>
            <p class="lead text-white-50 fs-6 m-0">Selamat datang di portal lab kendali materi mandiri.</p>
        </div>
    </div>

    <div class="container mt-5 mb-5" style="max-width: 650px;">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h4 class="fw-bold m-0 text-dark">📝 Edit Modul Praktik</h4>
            <a href="management_modul.php" class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="bi bi-arrow-counterclockwise"></i>
            </a>
        </div>

        <div class="card border-0 shadow-sm rounded-3 p-4 bg-white">
            <form action="" method="POST" enctype="multipart/form-data">
                
                <div class="mb-3">
                    <label for="title" class="form-label fw-semibold text-secondary">Nama Modul Praktik</label>
                    <input type="text" class="form-control p-2.5" id="title" name="title" value="<?= htmlspecialchars($data['title']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="category" class="form-label fw-semibold text-secondary">category Pembelajaran</label>
                    <select class="form-select p-2.5" id="category" name="category" required>
                        <option value="Network" <?= $data['category'] === 'Network' ? 'selected' : ''; ?>>Network</option>
                        <option value="IoT" <?= $data['category'] === 'IoT' ? 'selected' : ''; ?>>Internet of Things (IoT)</option>
                        <option value="Cloud Computing" <?= $data['category'] === 'Cloud Computing' ? 'selected' : ''; ?>>Cloud Computing</option>
                        <option value="DevOps" <?= $data['category'] === 'DevOps' ? 'selected' : ''; ?>>DevOps</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label fw-semibold text-secondary">Deskripsi Modul</label>
                    <input type="text" class="form-control p-2.5" id="description" name="description" value="<?= htmlspecialchars($data['description'] ?? ''); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Icon Saat Ini:</label>
                    <div class="mb-2">
                        <img src="<?php echo !empty($data['image_path']) ? htmlspecialchars($data['image_path']) : 'img/default_icon.png'; ?>" 
                            alt="Current Icon" 
                            style="max-height: 120px; object-fit: contain; border: 1px solid #ddd; padding: 5px; background-color: #f8f9fa;">
                    </div>
                    <input type="file" name="image_path" class="form-control">
                    <small class="text-muted">Biarkan kosong jika tidak ingin mengubah icon, Pak.</small>
                </div>

                <div class="mb-4">
                    <label for="file_path" class="form-label fw-semibold text-secondary">Link Google Drive Modul</label>
                    <input type="url" class="form-control p-2.5" id="file_path" name="file_path" value="<?= htmlspecialchars($data['file_path']); ?>" required>
                </div>

                <button type="submit" class="btn btn-warning w-100 py-2.5 fw-bold text-dark rounded-2 shadow-sm">
                    Simpan Perubahan Modul
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>