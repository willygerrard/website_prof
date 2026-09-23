<?php
include 'koneksi.php';
include 'csrf_helper.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['is_login']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak.");
}

$pesan = '';
$pesan_type = '';

// Variabel untuk head.php
$page_title = "Management Tugas - Pusat Pembelajaran SIJA";
$body_class = "bg-light";

// Ambil daftar kelas aktif
$kelas_options = $pdo->query("SELECT DISTINCT kelas FROM users WHERE kelas IS NOT NULL AND kelas <> '' AND status = 'aktif' ORDER BY kelas")->fetchAll(PDO::FETCH_COLUMN);

// Helper Fungsi Hapus Rekursif Folder HDD
function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    return rmdir($dir);
}

// ==========================================
// 1. ACTION: CREATE (DEPLOY TUGAS BARU)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    csrf_require_valid_post();
    $judul        = trim($_POST['judul'] ?? '');
    $deskripsi    = trim($_POST['deskripsi'] ?? '');
    $deadline     = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
    $link_lkpd    = trim($_POST['link_lkpd'] ?? '') ?: null;
    $kelas_target = $_POST['kelas'] ?? [];

    if ($judul && !empty($kelas_target)) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO tugas (judul, deskripsi, deadline, link_lkpd, folder_path) VALUES (?, ?, ?, ?, '')");
            $stmt->execute([$judul, $deskripsi, $deadline, $link_lkpd]);
            $tugas_id = $pdo->lastInsertId();

            $slug_judul  = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($judul));
            $folder_name = $slug_judul . '_' . $tugas_id;
            $target_dir  = "/var/www/html/uploads/" . $folder_name;

            if (!file_exists($target_dir) && !mkdir($target_dir, 0775, true)) {
                throw new Exception("Gagal membuat folder di HDD ({$target_dir}). Cek permission direktori.");
            }

            $stmtUpdate = $pdo->prepare("UPDATE tugas SET folder_path = ? WHERE id = ?");
            $stmtUpdate->execute([$folder_name, $tugas_id]);

            $stmtKelas = $pdo->prepare("INSERT INTO tugas_kelas (tugas_id, kelas) VALUES (?, ?)");
            foreach ($kelas_target as $k) {
                $stmtKelas->execute([$tugas_id, $k]);
            }

            $pdo->commit();
            $pesan = "✅ Tugas berhasil di-deploy dengan ID #$tugas_id!";
            $pesan_type = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $pesan = "❌ Gagal deploy tugas: " . $e->getMessage();
            $pesan_type = "danger";
        }
    } else {
        $pesan = "⚠️ Mohon isi judul dan minimal pilih 1 kelas target!";
        $pesan_type = "warning";
    }
}

// ==========================================
// 2. ACTION: UPDATE (EDIT TUGAS)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    csrf_require_valid_post();
    $tugas_id     = (int)$_POST['tugas_id'];
    $judul        = trim($_POST['judul'] ?? '');
    $deskripsi    = trim($_POST['deskripsi'] ?? '');
    $deadline     = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
    $link_lkpd    = trim($_POST['link_lkpd'] ?? '') ?: null;
    $kelas_target = $_POST['kelas'] ?? [];

    if ($tugas_id && $judul && !empty($kelas_target)) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE tugas SET judul = ?, deskripsi = ?, deadline = ?, link_lkpd = ? WHERE id = ?");
            $stmt->execute([$judul, $deskripsi, $deadline, $link_lkpd, $tugas_id]);

            $pdo->prepare("DELETE FROM tugas_kelas WHERE tugas_id = ?")->execute([$tugas_id]);
            $stmtKelas = $pdo->prepare("INSERT INTO tugas_kelas (tugas_id, kelas) VALUES (?, ?)");
            foreach ($kelas_target as $k) {
                $stmtKelas->execute([$tugas_id, $k]);
            }

            $pdo->commit();
            $pesan = "✅ Data tugas berhasil diperbarui!";
            $pesan_type = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $pesan = "❌ Gagal mengedit tugas: " . $e->getMessage();
            $pesan_type = "danger";
        }
    }
}

// ==========================================
// 3. ACTION: DELETE (HAPUS TUGAS + HDD CLEANUP)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    csrf_require_valid_post();
    $tugas_id = (int)$_POST['tugas_id'];

    $stmt = $pdo->prepare("SELECT folder_path FROM tugas WHERE id = ?");
    $stmt->execute([$tugas_id]);
    $folder_path = $stmt->fetchColumn();

    if ($folder_path) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM pengumpulan_tugas WHERE tugas_id = ?")->execute([$tugas_id]);
            $pdo->prepare("DELETE FROM tugas_kelas WHERE tugas_id = ?")->execute([$tugas_id]);

            $stmtDel = $pdo->prepare("DELETE FROM tugas WHERE id = ?");
            $stmtDel->execute([$tugas_id]);

            $target_dir = "/var/www/html/uploads/" . $folder_path;
            $folderDeleted = deleteDirectory($target_dir);

            $pdo->commit();
            if ($folderDeleted) {
                $pesan = "🗑️ Tugas dan berkas HDD berhasil dibersihkan!";
                $pesan_type = "warning";
            } else {
                $pesan = "⚠️ Data tugas terhapus dari database, tapi sebagian/semua file di HDD ({$target_dir}) gagal dihapus. Cek permission folder secara manual.";
                $pesan_type = "warning";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $pesan = "❌ Gagal menghapus tugas: " . $e->getMessage();
            $pesan_type = "danger";
        }
    }
}

// ==========================================
// READ: DAFTAR TUGAS
// ==========================================
$daftar_tugas = $pdo->query("
    SELECT t.*, 
           GROUP_CONCAT(DISTINCT tk.kelas ORDER BY tk.kelas SEPARATOR ', ') AS kelas_target,
           COUNT(DISTINCT pt.id) AS total_pengumpul
    FROM tugas t
    LEFT JOIN tugas_kelas tk ON t.id = tk.tugas_id
    LEFT JOIN pengumpulan_tugas pt ON t.id = pt.tugas_id
    GROUP BY t.id
    ORDER BY t.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Include Head HTML & Navbar/Header Admin
include 'includes/head.php';
?>
<!-- CSS Bootstrap & Custom Styles -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="css/styles.css">

<?php
include 'includes/admin_header.php';
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold m-0">📚 Management Tugas Siswa</h3>
        <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="bi bi-plus-circle"></i> Deploy Tugas Baru
        </button>
    </div>

    <?php if ($pesan): ?>
        <div class="alert alert-<?= $pesan_type ?> alert-dismissible fade show"><?= $pesan ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Judul Tugas</th>
                            <th>Kelas Target</th>
                            <th>Deadline</th>
                            <th>Pengumpul</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($daftar_tugas)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada tugas yang di-deploy.</td></tr>
                        <?php else: foreach ($daftar_tugas as $t): ?>
                            <tr>
                                <td class="fw-bold">
                                    <?= htmlspecialchars($t['judul']) ?>
                                    <?php if (!empty($t['link_lkpd'])): ?>
                                        <a href="<?= htmlspecialchars($t['link_lkpd']) ?>" target="_blank" class="badge bg-light text-dark border text-decoration-none">📄 LKPD</a>
                                    <?php endif; ?>
                                    <br>
                                    <small class="text-muted fw-normal"><code>/uploads/<?= htmlspecialchars($t['folder_path']) ?></code></small>
                                </td>
                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($t['kelas_target'] ?? '-') ?></span></td>
                                <td>
                                    <?php if($t['deadline']): ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> <?= date('d M Y, H:i', strtotime($t['deadline'])) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Tanpa Deadline</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="rekap_nilai_tugas.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-success fw-bold">
                                        <i class="bi bi-people"></i> <?= $t['total_pengumpul'] ?> Siswa
                                    </a>
                                </td>
                                <td class="text-center">
                                     <a href="export_tugas.php?tugas_id=<?= $t['id'] ?>" 
                                        class="btn btn-sm btn-outline-success me-1" 
                                        title="Export Nilai ke Excel">
                                            <i class="bi bi-file-earmark-excel"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-warning me-1" onclick='openEditModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus tugas ini? Seluruh berkas di HDD akan TERHAPUS PERMANEN!')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="tugas_id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header"><h5 class="modal-title fw-bold">Deploy Tugas Baru</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label fw-bold">Judul Tugas</label><input type="text" name="judul" class="form-control" placeholder="Contoh: Praktek Subnetting 1" required></div>
                <div class="mb-3"><label class="form-label fw-bold">Deskripsi / Petunjuk</label><textarea name="deskripsi" class="form-control" rows="3"></textarea></div>
                <div class="mb-3"><label class="form-label fw-bold">Batas Waktu (Deadline)</label><input type="datetime-local" name="deadline" class="form-control"></div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Link LKPD (opsional)</label>
                    <input type="url" name="link_lkpd" class="form-control" placeholder="https://drive.google.com/... (link ke file LKPD spesifik tugas ini)">
                    <div class="form-text">Kosongkan kalau LKPD-nya cukup di folder Drive kelas umum.</div>
                </div>
                <div class="mb-3"><label class="form-label fw-bold">Kelas Target</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($kelas_options as $k): ?>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="kelas[]" value="<?= $k ?>" id="add_<?= $k ?>"><label class="form-check-label" for="add_<?= $k ?>"><?= $k ?></label></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary fw-bold">Deploy Sekarang</button></div>
        </form>
    </div>
</div>

<!-- MODAL EDIT -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="tugas_id" id="edit_tugas_id">
            <div class="modal-header"><h5 class="modal-title fw-bold">Edit Tugas</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label fw-bold">Judul Tugas</label><input type="text" name="judul" id="edit_judul" class="form-control" required></div>
                <div class="mb-3"><label class="form-label fw-bold">Deskripsi / Petunjuk</label><textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="3"></textarea></div>
                <div class="mb-3"><label class="form-label fw-bold">Batas Waktu (Deadline)</label><input type="datetime-local" name="deadline" id="edit_deadline" class="form-control"></div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Link LKPD (opsional)</label>
                    <input type="url" name="link_lkpd" id="edit_link_lkpd" class="form-control" placeholder="https://drive.google.com/...">
                </div>
                <div class="mb-3"><label class="form-label fw-bold">Kelas Target</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($kelas_options as $k): ?>
                            <div class="form-check"><input class="form-check-input check-edit-kelas" type="checkbox" name="kelas[]" value="<?= $k ?>" id="edit_k_<?= $k ?>"><label class="form-check-label" for="edit_k_<?= $k ?>"><?= $k ?></label></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-warning fw-bold">Simpan Perubahan</button></div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openEditModal(data) {
    document.getElementById('edit_tugas_id').value = data.id;
    document.getElementById('edit_judul').value = data.judul;
    document.getElementById('edit_deskripsi').value = data.deskripsi || '';
    document.getElementById('edit_link_lkpd').value = data.link_lkpd || '';
    
    if (data.deadline) {
        let d = new Date(data.deadline);
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        document.getElementById('edit_deadline').value = d.toISOString().slice(0, 16);
    } else {
        document.getElementById('edit_deadline').value = '';
    }

    document.querySelectorAll('.check-edit-kelas').forEach(cb => cb.checked = false);

    if (data.kelas_target) {
        const listKelas = data.kelas_target.split(', ');
        listKelas.forEach(k => {
            const cb = document.getElementById('edit_k_' + k);
            if (cb) cb.checked = true;
        });
    }

    new bootstrap.Modal(document.getElementById('modalEdit')).show();
}
</script>

<?php include 'includes/footer.php'; ?>