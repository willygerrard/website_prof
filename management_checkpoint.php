<?php
session_start();
require 'koneksi.php';
include 'csrf_helper.php';

// Guard: admin saja
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header('Location: login.php');
    exit();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 404 Not Found');
    exit();
}

// Guard path
if (strpos($_SERVER['REQUEST_URI'], 'pintu-rahasia-modul') === false) {
    header('HTTP/1.1 404 Not Found');
    exit();
}

$pesan = '';
$pesan_type = 'success';

// CRUD
$mode = $_GET['mode'] ?? 'create';
$edit_id = (int)($_GET['id'] ?? 0);

if (!isset($_SESSION['checkpoint_checkpoint_modul_cache'])) {
    $_SESSION['checkpoint_checkpoint_modul_cache'] = [];
}

// Ambil modul
$modules = $pdo->query("SELECT id, title FROM modules ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Pastikan checkpoint table ada (kalau belum dibuat, kasih error jelas)
try {
    $pdo->query("SELECT 1 FROM checkpoint_modul LIMIT 1");
} catch (Throwable $e) {
    die('Tabel checkpoint_modul belum ada. Buat dulu tabel checkpoint (SQL) sebelum memakai management_checkpoint.php.');
}

// Delete
if (isset($_GET['delete']) && $_GET['delete']) {
    csrf_require_valid_get();
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM checkpoint_modul WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: management_checkpoint.php?deleted=1');
    exit();
}

// Edit mode load
$editRow = null;
if ($mode === 'edit' && $edit_id) {
    $stmt = $pdo->prepare('SELECT * FROM checkpoint_modul WHERE id = ?');
    $stmt->execute([$edit_id]);
    $editRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editRow) {
        header('Location: management_checkpoint.php');
        exit();
    }
}

// Save (create/update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid_post();

    $modul_id = (int)($_POST['modul_id'] ?? 0);
    $pertanyaan = trim($_POST['pertanyaan'] ?? '');
    $opsi_a = trim($_POST['opsi_a'] ?? '');
    $opsi_b = trim($_POST['opsi_b'] ?? '');
    $jawaban_benar = ($_POST['jawaban_benar'] ?? 'a') === 'b' ? 'b' : 'a';

    if (!$modul_id || !$pertanyaan || !$opsi_a || !$opsi_b) {
        $pesan = 'Semua field (modul, pertanyaan, opsi A, opsi B) wajib diisi.';
        $pesan_type = 'danger';
    } else {
        if ($mode === 'edit' && $edit_id) {
            $stmt = $pdo->prepare('UPDATE checkpoint_modul SET modul_id=?, pertanyaan=?, opsi_a=?, opsi_b=?, jawaban_benar=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([$modul_id, $pertanyaan, $opsi_a, $opsi_b, $jawaban_benar, $edit_id]);
            $pesan = 'Checkpoint berhasil diperbarui.';
        } else {
            // Upsert berdasarkan modul_id (1 checkpoint per modul)
            $stmt = $pdo->prepare('SELECT id FROM checkpoint_modul WHERE modul_id = ? LIMIT 1');
            $stmt->execute([$modul_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $stmt2 = $pdo->prepare('UPDATE checkpoint_modul SET pertanyaan=?, opsi_a=?, opsi_b=?, jawaban_benar=?, updated_at=NOW() WHERE modul_id=?');
                $stmt2->execute([$pertanyaan, $opsi_a, $opsi_b, $jawaban_benar, $modul_id]);
                $pesan = 'Checkpoint modul sudah ada, datanya diupdate.';
            } else {
                $stmt3 = $pdo->prepare('INSERT INTO checkpoint_modul (modul_id, pertanyaan, opsi_a, opsi_b, jawaban_benar, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
                $stmt3->execute([$modul_id, $pertanyaan, $opsi_a, $opsi_b, $jawaban_benar]);
                $pesan = 'Checkpoint berhasil disimpan.';
            }
        }
        $pesan_type = 'success';
    }
}

// List checkpoint
$listStmt = $pdo->query("SELECT c.id, c.modul_id, m.title, c.pertanyaan, c.opsi_a, c.opsi_b, c.jawaban_benar, c.updated_at FROM checkpoint_modul c LEFT JOIN modules m ON m.id=c.modul_id ORDER BY c.id DESC");
$checkpoints = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// Render form values
$form = [
    'modul_id' => $editRow['modul_id'] ?? '',
    'pertanyaan' => $editRow['pertanyaan'] ?? '',
    'opsi_a' => $editRow['opsi_a'] ?? '',
    'opsi_b' => $editRow['opsi_b'] ?? '',
    'jawaban_benar' => $editRow['jawaban_benar'] ?? 'a',
];

$deleteToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Checkpoint Modul - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container px-4 px-lg-5">
        <a class="navbar-brand" href="index.php">Modul Pembelajaran SIJA</a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <?php if (isset($_SESSION['username'])) : ?>
                <span class="text-secondary fw-medium d-none d-md-inline small">👋 Hai, <strong class="text-dark"><?= htmlspecialchars($_SESSION['username']); ?></strong></span>
            <?php endif; ?>
        </div>
    </div>
</nav>


<header class="py-5" style="
    background: linear-gradient(to bottom, rgba(15, 23, 42, 0.85), rgba(30, 41, 59, 0.9)),
                url('https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=800');
    background-size: cover;
    background-position: center;">
    <div class="container px-4 px-lg-5 my-5">
        <div class="text-center text-white">
            <h1 class="display-4 fw-bolder">Manage Checkpoint Modul</h1>
            <p class="lead fw-normal text-white-50 mb-0">Kelola 1 pertanyaan checkpoint (opsi A/B) per modul</p>
        </div>
    </div>
</header>

<div class="container my-4" style="max-width: 1100px;">

    <?php if ($pesan): ?>
        <div class="alert alert-<?= htmlspecialchars($pesan_type) ?>"><?= htmlspecialchars($pesan) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0 fw-bold"><?= ($mode === 'edit') ? 'Edit Checkpoint' : 'Tambah/Update Checkpoint' ?></h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Modul</label>
                            <select class="form-select" name="modul_id" required>
                                <option value="" selected disabled>-- Pilih Modul --</option>
                                <?php foreach ($modules as $m): ?>
                                    <option value="<?= (int)$m['id'] ?>" <?= ((string)($form['modul_id'] ?? '') === (string)$m['id']) ? 'selected' : '' ?> ><?= htmlspecialchars($m['title']) ?></option>

                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Pertanyaan Checkpoint</label>
                            <textarea class="form-control" name="pertanyaan" rows="3" required><?= htmlspecialchars($form['pertanyaan']) ?></textarea>
                        </div>

                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Opsi Atas (A)</label>
                                <textarea class="form-control" name="opsi_a" rows="2" required><?= htmlspecialchars($form['opsi_a']) ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Opsi Bawah (B)</label>
                                <textarea class="form-control" name="opsi_b" rows="2" required><?= htmlspecialchars($form['opsi_b']) ?></textarea>
                            </div>
                        </div>

                        <div class="mb-3 mt-2">
                            <label class="form-label fw-semibold">Jawaban Benar</label>
                            <select class="form-select" name="jawaban_benar" required>
                                <option value="a" <?= $form['jawaban_benar'] === 'a' ? 'selected' : '' ?>>A (Opsi Atas)</option>
                                <option value="b" <?= $form['jawaban_benar'] === 'b' ? 'selected' : '' ?>>B (Opsi Bawah)</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success fw-bold">
                                <i class="bi bi-save"></i> Simpan
                            </button>
                            <?php if ($mode === 'edit' && $edit_id): ?>
                                <a href="management_checkpoint.php" class="btn btn-outline-secondary">Batal Edit</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm border-0 rounded-3">

                <div class="card-header bg-dark text-white py-3">
                    <h5 class="mb-0 fw-bold">Daftar Checkpoint</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle m-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 8%">#</th>
                                    <th style="width: 35%">Modul</th>
                                    <th>Pertanyaan</th>
                                    <th style="width: 12%">Benar</th>
                                    <th style="width: 15%" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($checkpoints)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada checkpoint.</td></tr>
                                <?php else: ?>
                                    <?php $no=1; foreach ($checkpoints as $c): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td class="fw-semibold"><?= htmlspecialchars($c['title'] ?? ('Modul #' . $c['modul_id'])) ?></td>
                                            <td class="text-secondary"><?= htmlspecialchars(mb_strimwidth($c['pertanyaan'] ?? '', 0, 90, '...')) ?></td>
                                            <td>
                                                <span class="badge bg-<?= ($c['jawaban_benar'] === 'a') ? 'primary' : 'secondary' ?>">
                                                    <?= strtoupper($c['jawaban_benar']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <a class="btn btn-warning" href="management_checkpoint.php?mode=edit&id=<?= (int)$c['id'] ?>">E</a>
                                                    <a class="btn btn-danger" href="management_checkpoint.php?delete=<?= (int)$c['id'] ?>&csrf_token=<?= htmlspecialchars($deleteToken) ?>" onclick="return confirm('Hapus checkpoint modul ini?')">-</a>
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
        </div>
    </div>
</div>

<footer class="py-5 bg-dark mt-5">
    <div class="container"><p class="m-0 text-center text-white">Copyright &copy; SIJA Website 2026</p></div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

