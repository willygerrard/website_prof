<?php
include 'koneksi.php';
include 'csrf_helper.php';
session_start();

if (!isset($_SESSION['is_login'])) {
    header("Location: login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$tugas_id = (int)($_GET['id'] ?? 0);

// Ambil kelas siswa
$stmtUser = $pdo->prepare("SELECT username, kelas FROM users WHERE id = ?");
$stmtUser->execute([$user_id]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

// Cek Tugas & Hak Akses Kelas
$stmt = $pdo->prepare("
    SELECT t.* 
    FROM tugas t
    JOIN tugas_kelas tk ON t.id = tk.tugas_id
    WHERE t.id = ? AND tk.kelas = ?
");
$stmt->execute([$tugas_id, $user['kelas']]);
$tugas = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tugas) {
    die("Tugas tidak ditemukan atau tidak ditujukan untuk kelas kamu.");
}

/* ============================================================
   FUNGSI KOMPRES GAMBAR OTOMATIS (TANPA WARNING)
   Menurunkan resolusi & kualitas JPEG hingga file <= $maxSize
   ============================================================ */
function compressImageToMaxSize(string $sourcePath, string $targetPath, int $maxSize = 1048576): bool
{
    $info = @getimagesize($sourcePath);
    if (!$info) return false;

    [$width, $height, $type] = $info;

    switch ($type) {
        case IMAGETYPE_JPEG:
            $src = @imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $src = @imagecreatefrompng($sourcePath);
            break;
        default:
            return false;
    }
    if (!$src) return false;

    // Kombinasi skala & kualitas (dari terbaik ke paling kecil)
    $scales    = [1, 0.85, 0.7, 0.6, 0.5, 0.4, 0.3, 0.25];
    $qualities = [85, 75, 65, 55, 45, 35, 30];

    foreach ($scales as $scale) {
        $newW = max(1, (int)($width * $scale));
        $newH = max(1, (int)($height * $scale));

        $dst   = imagecreatetruecolor($newW, $newH);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);

        foreach ($qualities as $q) {
            ob_start();
            imagejpeg($dst, null, $q);
            $data = ob_get_clean();

            if (strlen($data) <= $maxSize) {
                file_put_contents($targetPath, $data);
                imagedestroy($dst);
                imagedestroy($src);
                return true;
            }
        }
        imagedestroy($dst);
    }

    imagedestroy($src);
    return false;
}

/* ============================================================
   SIMPAN KE DATABASE (dipakai untuk gambar & PDF)
   ============================================================ */
function simpanPengumpulan(PDO $pdo, int $tugas_id, int $user_id, string $web_path): void
{
    $stmtSave = $pdo->prepare("
        INSERT INTO pengumpulan_tugas (tugas_id, user_id, file_path, uploaded_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            file_path    = VALUES(file_path), 
            uploaded_at  = NOW(),
            nilai        = NULL,
            catatan_guru = NULL,
            dinilai_at   = NULL
    ");
    $stmtSave->execute([$tugas_id, $user_id, $web_path]);
}

$pesan = '';
$max_size = 5 * 1024 * 1024; // 5 MB

// Process Upload File
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_tugas'])) {
    csrf_require_valid_post();

    $file = $_FILES['file_tugas'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];

        if (!in_array($ext, $allowed)) {
            $pesan = "⚠️ Format file tidak didukung! Harus berupa JPG, PNG, atau PDF.";
        } else {
            // Path fisik ke HDD via Docker volume mount
            $target_dir = "/var/www/html/uploads/" . $tugas['folder_path'] . "/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0775, true);

            $isImage = in_array($ext, ['jpg', 'jpeg', 'png']);

            if ($isImage) {
                /* --- GAMBAR: selalu dikompres otomatis jadi JPEG ≤ 5MB --- */
                $filename    = strtolower($user['username']) . '_' . time() . '.jpg';
                $target_path = $target_dir . $filename;
                $web_path    = "uploads/" . $tugas['folder_path'] . "/" . $filename;

                if (compressImageToMaxSize($file['tmp_name'], $target_path, $max_size)) {
                    simpanPengumpulan($pdo, $tugas_id, $user_id, $web_path);
                    $pesan = "✅ Berhasil mengunggah tugas!";
                } else {
                    $pesan = "❌ Gagal memproses gambar.";
                }
            } else {
                /* --- PDF: tidak bisa dikompres tanpa library, jadi tetap dicek --- */
                if ($file['size'] > $max_size) {
                    $pesan = "⚠️ Ukuran PDF terlalu besar! Maksimal 5 MB.";
                } else {
                    $filename    = strtolower($user['username']) . '_' . time() . '.pdf';
                    $target_path = $target_dir . $filename;
                    $web_path    = "uploads/" . $tugas['folder_path'] . "/" . $filename;

                    if (move_uploaded_file($file['tmp_name'], $target_path)) {
                        simpanPengumpulan($pdo, $tugas_id, $user_id, $web_path);
                        $pesan = "✅ Berhasil mengunggah tugas!";
                    } else {
                        $pesan = "❌ Gagal memindahkan file ke storage server.";
                    }
                }
            }
        }
    } else {
        $pesan = "⚠️ Terjadi kesalahan saat mengunggah file. Kode Error: " . $file['error'];
    }
}

// Cek apakah siswa sudah pernah upload
$stmtCek = $pdo->prepare("SELECT * FROM pengumpulan_tugas WHERE tugas_id = ? AND user_id = ?");
$stmtCek->execute([$tugas_id, $user_id]);
$bukti = $stmtCek->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title><?= htmlspecialchars($tugas['judul']) ?> - Upload Tugas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width: 600px;">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <a href="daftar_tugas_siswa.php" class="btn btn-outline-secondary btn-sm fw-bold shadow-sm">
                    ← Kembali ke Daftar Tugas
                </a>
            </div>
            <h4><?= htmlspecialchars($tugas['judul']) ?></h4>
            <p class="text-muted"><?= nl2br(htmlspecialchars($tugas['deskripsi'])) ?></p>

            <?php if (!empty($tugas['link_lkpd'])): ?>
                <a href="<?= htmlspecialchars($tugas['link_lkpd']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm mb-3">
                    📄 Baca LKPD Dulu
                </a>
            <?php endif; ?>

            <?php if($pesan): ?><div class="alert alert-info"><?= $pesan ?></div><?php endif; ?>

            <?php if (!empty($tugas['deadline'])): ?>
                <?php
                    $deadlineTs = strtotime($tugas['deadline']);
                    $sudahLewat = time() > $deadlineTs;
                ?>
                <p class="mb-2">
                    <span class="badge <?= $sudahLewat ? 'bg-danger' : 'bg-secondary' ?>">
                        Batas waktu: <?= date('d M Y, H:i', $deadlineTs) ?>
                    </span>
                </p>
            <?php endif; ?>

            <!-- Form Upload -->
            <form method="POST" enctype="multipart/form-data" class="mb-4">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label fw-bold">Pilih Foto / PDF Tugas Buku</label>
                    <input type="file" name="file_tugas" class="form-control" accept="image/*,.pdf" required>
                </div>
                <button type="submit" class="btn btn-success w-100 fw-bold">Upload Berkas</button>
            </form>

            <?php if ($bukti && $bukti['nilai'] !== null): ?>
                <div class="alert alert-warning py-2 small mb-3">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Perhatian!</strong> Kamu sudah mendapat nilai <strong><?= htmlspecialchars($bukti['nilai']) ?></strong> untuk tugas ini.
                    Jika mengunggah ulang, <strong>nilai akan direset</strong> dan guru harus menilai ulang.
                </div>
            <?php endif; ?>

            <!-- Preview Bukti Upload -->
            <?php if ($bukti): ?>
                <hr>
                <h6 class="fw-bold text-primary">Pratinjau Bukti Terunggah:</h6>
                <?php
                    $uploadTs = strtotime($bukti['uploaded_at']);
                    $terlambat = !empty($tugas['deadline']) && $uploadTs > strtotime($tugas['deadline']);
                ?>
                <p class="small text-muted mb-2">
                    Dikumpulkan pada: <?= date('d M Y, H:i', $uploadTs) ?>
                    <?php if ($terlambat): ?>
                        <span class="badge bg-danger ms-1">Terlambat</span>
                    <?php elseif (!empty($tugas['deadline'])): ?>
                        <span class="badge bg-success ms-1">Tepat Waktu</span>
                    <?php endif; ?>
                </p>

                <?php
                $ext_bukti = strtolower(pathinfo($bukti['file_path'], PATHINFO_EXTENSION));
                $download_url = 'download_tugas.php?id=' . (int)$bukti['id'];
                if (in_array($ext_bukti, ['jpg', 'jpeg', 'png'])):
                ?>
                    <img src="<?= htmlspecialchars($download_url) ?>" class="img-fluid rounded border shadow-sm" style="max-height: 350px;">
                <?php else: ?>
                    <a href="<?= htmlspecialchars($download_url) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                        📄 Lihat File PDF Terunggah
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>