<?php
include 'koneksi.php';
include 'csrf_helper.php';
session_start();

// 1. Validasi Keamanan: Hanya Admin yang boleh masuk
if (!isset($_SESSION['is_login']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$pesan = '';

// 2. Buat folder 'games_data' otomatis jika belum ada di server
$upload_dir = 'games_data/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// 3. PROSES TAMBAH & UPLOAD GAME BARU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_game'])) {
   csrf_require_valid_post();
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category    = trim($_POST['category']);
    $image_path  = trim($_POST['image_path']); // URL cover gambar opsional

    // Cek apakah ada file HTML yang diupload
    if (isset($_FILES['game_file']) && $_FILES['game_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['game_file']['tmp_name'];
        $file_name = $_FILES['game_file']['name'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Validasi ekstensi harus .html atau .htm
        if (in_array($file_ext, ['html', 'htm'])) {
            // Bersihkan nama file agar tidak ada spasi/karakter aneh (opsional, ditambahkan timestamp biar unik)
            $clean_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', strtolower($file_name));
            $target_file = $upload_dir . $clean_name;

            // Pindahkan file dari temporary ke folder 'games_data/'
            if (move_uploaded_file($file_tmp, $target_file)) {
                // Simpan data ke database
                $sql = "INSERT INTO games (title, description, category, image_path, file_path) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$title, $description, $category, $image_path, $target_file]);
                
                $pesan = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                            ✅ <strong>Berhasil!</strong> Game HTML berhasil diupload dan dipublikasikan.
                            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                          </div>";
            } else {
                $pesan = "<div class='alert alert-danger'>❌ Gagal memindahkan file ke folder server! Cek izin folder (permissions).</div>";
            }
        } else {
            $pesan = "<div class='alert alert-warning'>⚠️ Format file ditolak! Harap upload file berekstensi <strong>.html</strong> saja.</div>";
        }
    } else {
        $pesan = "<div class='alert alert-danger'>❌ Wajib memilih file HTML game!</div>";
    }
}

// 4. PROSES HAPUS GAME & BERSIHKAN FILE DARI SERVER
if (isset($_GET['hapus'])) {
    csrf_require_valid_get();
    $id = (int)$_GET['hapus'];
    
    // Cari tahu dulu letak file HTML-nya di server
    $stmt_find = $pdo->prepare("SELECT file_path FROM games WHERE id = ?");
    $stmt_find->execute([$id]);
    $game_data = $stmt_find->fetch(PDO::FETCH_ASSOC);

    if ($game_data) {
        // Hapus file fisik .html di folder 'games_data/' jika ada
        if (file_exists($game_data['file_path']) && !is_dir($game_data['file_path'])) {
            unlink($game_data['file_path']);
        }

        // Hapus data dari database
        $stmt_del = $pdo->prepare("DELETE FROM games WHERE id = ?");
        $stmt_del->execute([$id]);
    }

    header("Location: manage_game.php?status=deleted");
    exit();
}

// 5. Ambil semua daftar game dari database
$stmt = $pdo->query("SELECT * FROM games ORDER BY id DESC");
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Manage Game Edukasi - Admin Panel</title>
    <!-- Favicon-->
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet" />
</head>
<body class="bg-light">
    <!-- Navbar simpel khusus Admin -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">⚙️ Admin Panel - SIJA</a>
            <div class="d-flex">
                <a href="index.php" class="btn btn-outline-light btn-sm d-flex align-items-center gap-2">
                    <i class="bi bi-house-door"></i> Kembali ke LMS
                </a>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold mb-0">🎮 Manajemen Game Edukasi HTML</h3>
                <p class="text-muted small mb-0">Upload file game HTML tunggal (.html) langsung ke server LMS.</p>
            </div>
        </div>

        <?= $pesan ?>
        <?php if (isset($_GET['status']) && $_GET['status'] == 'deleted'): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                🗑️ Data game beserta file HTML di server berhasil dihapus bersih!
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>
        <?php endif; ?>

        <!-- Form Upload Game Baru -->
        <div class="card shadow-sm border-0 rounded-3 mb-5">
            <div class="card-header bg-primary text-white fw-bold py-3">
                <i class="bi bi-cloud-arrow-up-fill me-2"></i> Upload Game HTML Baru
            </div>
            <div class="card-body p-4">
                <!-- PENTING: enctype="multipart/form-data" wajib ada untuk upload file -->
                <form action="" method="POST" enctype="multipart/form-data">
                    <?= csrf_field(); ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Judul Game <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="Contoh: Sorting Proses Bisnis TKJ" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kategori Materi</label>
                            <input type="text" name="category" class="form-control" value="Teknologi & Jaringan">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi / Instruksi Singkat</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Jelaskan cara main atau tujuan game edukasi ini..."></textarea>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Pilih File Game (.html) <span class="text-danger">*</span></label>
                            <input type="file" name="game_file" class="form-control" accept=".html,.htm" required>
                            <div class="form-text small">Upload file HTML tunggal yang sudah berisi script game (Maksimal sesuai limit server).</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">URL Cover Gambar (Opsional)</label>
                            <input type="url" name="image_path" class="form-control" placeholder="https://... (Kosongkan jika ingin pakai gambar default)">
                            <div class="form-text small">Bisa pakai link gambar dari Unsplash atau biarkan kosong.</div>
                        </div>
                    </div>

                    <button type="submit" name="tambah_game" class="btn btn-primary px-4 fw-bold shadow-sm">
                        <i class="bi bi-save me-1"></i> Simpan & Publikasikan Game
                    </button>
                </form>
            </div>
        </div>

        <!-- Tabel Daftar Game Tersimpan -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                <span>Daftar Game Tersimpan (<?= count($games) ?>)</span>
                <span class="badge bg-secondary">Folder: /games_data/</span>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-bordered mb-0 align-middle">
                    <thead class="table-light text-center">
                        <tr>
                            <th width="5%">No</th>
                            <th width="22%">Judul Game</th>
                            <th width="28%">Deskripsi</th>
                            <th width="15%">Kategori</th>
                            <th width="18%">File Path (Server)</th>
                            <th width="12%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($games)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-folder-x display-6 d-block mb-2 text-secondary"></i>
                                    Belum ada game edukasi yang diupload.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach($games as $g): ?>
                            <tr>
                                <td class="text-center fw-semibold"><?= $no++ ?></td>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($g['title']) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($g['description']) ?></td>
                                <td class="text-center"><span class="badge bg-info text-dark"><?= htmlspecialchars($g['category']) ?></span></td>
                                <td class="small font-monospace text-muted">
                                    <i class="bi bi-file-earmark-code me-1"></i><?= htmlspecialchars($g['file_path']) ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <!-- Tombol Test Mainkan Game -->
                                        <a href="<?= htmlspecialchars($g['file_path']) ?>" target="_blank" class="btn btn-sm btn-success" title="Tes Mainkan">
                                            <i class="bi bi-play-fill"></i> Tes
                                        </a>
                                        <!-- Tombol Hapus -->
                                      <a href="<?= 'manage_game.php?hapus=' . (int)$g['id'] . '&token=' . csrf_token() ?>" 
                                        class="btn btn-sm btn-danger" 
                                        onclick="return confirm('Yakin ingin menghapus game ini?');" 
                                        title="Hapus Game">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>