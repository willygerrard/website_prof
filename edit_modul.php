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
    
    $nama_file_gambar = $data['image_path']; // Default pake foto lama dulu
    
    
    // Cek apakah admin mengupload ikon baru untuk mengganti ikon lama
    if (isset($_FILES['image_path']) && $_FILES['image_path']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['image_path']['tmp_name'];
        $file_name = $_FILES['image_path']['name'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $ekstensi_boleh = ['png', 'jpg', 'jpeg'];

        if (in_array($file_ext, $ekstensi_boleh)) {
            // Hapus ikon lama dari server (asal bukan ikon bawaan)
            if ($data['image_path'] !== 'default_icon.png' && file_exists('img/' . $data['image_path'])) {
                unlink('img/' . $data['image_path']);
            }
            // Generate nama ikon baru
            $nama_file_gambar = 'icon_' . time() . '_' . uniqid() . '.' . $file_ext;
            move_uploaded_file($file_tmp, 'img/' . $nama_file_gambar);
        }
    }

    // Eksekusi UPDATE Query ke MariaDB via PDO
    try {
        $sql = "UPDATE modules 
                SET title = :title, category = :category, `description` = :deskripsi, file_path = :file_path, image_path = :image_path 
                WHERE id = :id";
        
        $stmt_update = $pdo->prepare($sql);
        $stmt_update->execute([
            ':title' => $title,
            ':category'   => $category,
            ':deskripsi'  => $description,
            ':file_path' => $file_path,
            ':image_path'   => $nama_file_gambar,
            ':id'         => $id
        ]);

        echo "<script>
                alert('Perubahan modul sukses disimpan!');
                window.location.href = 'management_modul.php';
              </script>";
        exit();
    } catch (PDOException $e) {
        die("Gagal update data Pak! Karena: " . $e->getMessage());
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
                <span class="text-secondary fw-medium small">👋 Hai, <strong class="text-dark">willy</strong></span>
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
                    </select>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label fw-semibold text-secondary">Deskripsi Modul</label>
                    <input type="text" class="form-control p-2.5" id="description" name="description" value="<?= htmlspecialchars($data['description'] ?? ''); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold text-secondary">Ikon Modul Saat Ini</label>
                    <div class="mb-2">
                      <img src="assets/img/<?= !empty($data['image_path']) ? htmlspecialchars($data['image_path']) : 'default_icon.png'; ?>" alt="Icon" class="img-thumbnail" style="width: 60px; height: 60px;">
                    </div>
                    <label for="image_path" class="form-label small text-muted">Ganti Ikon (Kosongkan jika tidak ingin diubah)</label>
                    <input type="file" class="form-control p-2" id="image_path" name="image_path" accept="image/png, image/jpeg, image/jpg">
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