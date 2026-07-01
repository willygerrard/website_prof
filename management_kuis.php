<?php
include 'koneksi.php';
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

if (strpos($_SERVER['REQUEST_URI'], 'pintu-rahasia-sija') === false) {
    header("HTTP/1.1 404 Not Found");
    exit();
}

// Handle hapus soal
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $stmt = $pdo->prepare("DELETE FROM kuis_soal WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: /pintu-rahasia-sija");
    exit();
}

// Filter kategori & level
$kategori_filter = $_GET['kategori'] ?? '';
$level_filter     = $_GET['level'] ?? '';

$sql = "SELECT * FROM kuis_soal WHERE 1=1";
$params = [];

if ($kategori_filter) {
    $sql .= " AND kategori = ?";
    $params[] = $kategori_filter;
}
if ($level_filter) {
    $sql .= " AND level = ?";
    $params[] = $level_filter;
}
$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$query = $stmt;

$level_badge = [
    'pemula'   => ['🟢 Pemula', 'success'],
    'menengah' => ['🟡 Menengah', 'warning'],
    'mahir'    => ['🔴 Mahir', 'danger'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Kuis - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand" href="index.php">Modul Pembelajaran SIJA</a>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4"></ul>
                <div class="d-flex align-items-center gap-3">
                    <?php if (isset($_SESSION['username'])) : ?>
                        <span class="text-secondary fw-medium d-none d-md-inline small">
                            👋 Hai, <strong class="text-dark"><?= htmlspecialchars($_SESSION['username']); ?></strong>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- HEADER -->
    <header class="py-5" style="
        background: linear-gradient(to bottom, rgba(15, 23, 42, 0.85), rgba(30, 41, 59, 0.9)),
                    url('https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=800');
        background-size: cover;
        background-position: center;">
        <div class="container px-4 px-lg-5 my-5">
            <div class="text-center text-white">
                <h1 class="display-4 fw-bolder">Pusat Pembelajaran SIJA</h1>
                <p class="lead fw-normal text-white-50 mb-0">Selamat datang di portal lab kendali materi mandiri</p>
            </div>
        </div>
    </header>

    <!-- KONTEN -->
    <div class="container mt-5 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold m-0 text-dark">📝 Daftar Soal Kuis</h4>
            <div class="btn-group gap-2">
                <a href="index.php" class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Kembali ke Beranda">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
                <a href="deploy_kuis.php" class="btn btn-primary rounded-circle ..." title="Deploy Kuis">
                    <i class="bi bi-rocket-takeoff"></i>
                </a>
                <a href="tambah_soal.php" class="btn btn-success rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Tambah Soal Baru">
                    <i class="bi bi-plus-lg"></i>
                </a>
                <a href="rekap_nilai.php" class="btn btn-success rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Lihat Rekap Nilai">
                    <i class="bi bi-bar-chart"></i>
                </a>
                <button type="submit" form="bulkDeleteForm" class="btn btn-success rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Hapus Masal Soal Terpilih">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>

        <!-- Filter Kategori -->
        <div class="mb-2 d-flex gap-2 flex-wrap align-items-center">
            <span class="text-muted small fw-semibold me-1">Kategori:</span>
            <a href="?level=<?= urlencode($level_filter) ?>" class="btn btn-sm <?= !$kategori_filter ? 'btn-dark' : 'btn-outline-dark' ?>">Semua</a>
            <?php
            $kategori_list = $pdo->query("SELECT DISTINCT kategori FROM kuis_soal ORDER BY kategori");
            while ($kat = $kategori_list->fetch(PDO::FETCH_ASSOC)) :
                $active = $kategori_filter === $kat['kategori'] ? 'btn-info text-white' : 'btn-outline-info';
            ?>
            <a href="?kategori=<?= urlencode($kat['kategori']) ?>&level=<?= urlencode($level_filter) ?>" class="btn btn-sm <?= $active ?>">
                <?= htmlspecialchars($kat['kategori']) ?>
            </a>
            <?php endwhile; ?>
        </div>

        <!-- Filter Level -->
        <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
            <span class="text-muted small fw-semibold me-1">Level:</span>
            <a href="?kategori=<?= urlencode($kategori_filter) ?>" class="btn btn-sm <?= !$level_filter ? 'btn-dark' : 'btn-outline-dark' ?>">Semua</a>
            <?php foreach ($level_badge as $key => $val):
                $active = $level_filter === $key ? 'btn-' . $val[1] . ' text-white' : 'btn-outline-' . $val[1];
            ?>
            <a href="?kategori=<?= urlencode($kategori_filter) ?>&level=<?= $key ?>" class="btn btn-sm <?= $active ?>">
                <?= $val[0] ?>
            </a>
            <?php endforeach; ?>
        </div>

        <form id="bulkDeleteForm" action="bulkdelete.php" method="POST" onsubmit="return confirmBulkDelete(this)">
        <div class="table-responsive bg-white p-4 rounded-3 shadow-sm border">
            <table class="table table-hover align-middle m-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 4%"><input type="checkbox" class="form-check-input" id="checkAll" title="Pilih semua"></th>
                        <th style="width: 4%">No</th>
                        <th style="width: 33%">Pertanyaan</th>
                        <th style="width: 15%">Kategori</th>
                        <th style="width: 13%">Level</th>
                        <th style="width: 16%">Jawaban</th>
                        <th style="width: 15%" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; while ($row = $query->fetch(PDO::FETCH_ASSOC)) :
                        $lvl = $level_badge[$row['level']] ?? ['-', 'secondary'];
                    ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input soal-check" name="ids[]" value="<?= (int)$row['id']; ?>">
                        </td>
                        <td><?= $no++; ?></td>
                        <td class="fw-semibold text-secondary"><?= htmlspecialchars($row['pertanyaan'] ?? ''); ?></td>
                        <td><span class="badge bg-info text-dark px-2 py-1"><?= htmlspecialchars($row['kategori'] ?? ''); ?></span></td>
                        <td><span class="badge bg-<?= $lvl[1] ?>"><?= $lvl[0] ?></span></td>
                        <td>
                            <span class="badge bg-success">
                                <?php
                                $jwb = strtoupper($row['jawaban']);
                                echo $jwb . '. ' . htmlspecialchars($row['pilihan_' . strtolower($row['jawaban'])] ?? '');
                                ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="edit-soal-sija?id=<?= $row['id']; ?>" class="btn btn-warning fw-bold text-dark px-2">E</a>
                                <a href="?hapus=<?= $row['id']; ?>" class="btn btn-danger fw-bold px-2" onclick="return confirm('Yakin hapus soal ini?')">－</a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($no === 1): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Belum ada soal untuk filter ini.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </form>
    </div>

    <!-- FOOTER -->
    <footer class="py-5 bg-dark">
        <div class="container"><p class="m-0 text-center text-white">Copyright &copy; SIJA Website 2026</p></div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Centang / hilangkan centang semua checkbox soal
        document.getElementById('checkAll').addEventListener('change', function () {
            document.querySelectorAll('.soal-check').forEach(function (cb) {
                cb.checked = this.checked;
            }.bind(this));
        });

        // Jika salah satu checkbox baris diubah, sinkronkan status "Pilih semua"
        document.querySelectorAll('.soal-check').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var all = document.querySelectorAll('.soal-check');
                var checked = document.querySelectorAll('.soal-check:checked');
                document.getElementById('checkAll').checked = all.length === checked.length;
            });
        });

        // Validasi sebelum submit bulk delete
        function confirmBulkDelete(form) {
            var checked = form.querySelectorAll('.soal-check:checked');
            if (checked.length === 0) {
                alert('Pilih minimal 1 soal terlebih dahulu untuk dihapus.');
                return false;
            }
            return confirm('Yakin ingin menghapus ' + checked.length + ' soal terpilih?');
        }
    </script>
</body>
</html>