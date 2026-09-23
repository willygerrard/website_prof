<?php
// rekap_nilai_tugas.php - Input & Rekap Nilai Tugas (khusus admin/guru)
include 'koneksi.php';
session_start();

// ============================================================
// AUTH: hanya admin
// ============================================================
if (!isset($_SESSION['is_login']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak.");
}

// ============================================================
// AJAX HANDLER: simpan nilai
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'simpan_nilai') {
    header('Content-Type: application/json');

    $submission_id = (int)($_POST['submission_id'] ?? 0);
    $nilai_raw     = trim($_POST['nilai'] ?? '');
    $catatan       = trim($_POST['catatan'] ?? '');

    if ($submission_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID submission tidak valid.']);
        exit;
    }

    // Nilai kosong = reset
    if ($nilai_raw === '') {
        $stmt = $pdo->prepare("
            UPDATE pengumpulan_tugas 
            SET nilai = NULL, catatan_guru = NULL, dinilai_at = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$submission_id]);
        echo json_encode(['status' => 'success', 'message' => 'Nilai dikosongkan.', 'nilai' => null]);
        exit;
    }

    $nilai = (float)$nilai_raw;
    if ($nilai < 0 || $nilai > 100) {
        echo json_encode(['status' => 'error', 'message' => 'Nilai harus antara 0-100.']);
        exit;
    }

    $stmtCek = $pdo->prepare("SELECT id FROM pengumpulan_tugas WHERE id = ?");
    $stmtCek->execute([$submission_id]);
    if (!$stmtCek->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Submission tidak ditemukan.']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE pengumpulan_tugas 
        SET nilai = ?, catatan_guru = ?, dinilai_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$nilai, $catatan !== '' ? $catatan : null, $submission_id]);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Nilai tersimpan.',
        'nilai'   => $nilai
    ]);
    exit;
}

// ============================================================
// AMBIL DATA TUGAS
// ============================================================
$tugas_id = (int)($_GET['id'] ?? 0);
if ($tugas_id <= 0) {
    die("ID tugas tidak valid. <a href='management_tugas.php'>Kembali</a>");
}

$stmtTugas = $pdo->prepare("
    SELECT t.*, GROUP_CONCAT(DISTINCT tk.kelas SEPARATOR ', ') AS kelas_target 
    FROM tugas t
    LEFT JOIN tugas_kelas tk ON t.id = tk.tugas_id
    WHERE t.id = ?
    GROUP BY t.id
");
$stmtTugas->execute([$tugas_id]);
$tugas = $stmtTugas->fetch(PDO::FETCH_ASSOC);

if (!$tugas) {
    die("Tugas tidak ditemukan. <a href='management_tugas.php'>Kembali</a>");
}

// ============================================================
// AMBIL DAFTAR SISWA + STATUS + NILAI
// ============================================================
$list_kelas   = array_map('trim', explode(',', $tugas['kelas_target']));
$placeholders = implode(',', array_fill(0, count($list_kelas), '?'));

$querySiswa = "
    SELECT u.id AS user_id, u.username, u.nama_asli, u.kelas,
           pt.id AS pengumpulan_id, pt.file_path, pt.uploaded_at,
           pt.nilai, pt.catatan_guru, pt.dinilai_at
    FROM users u
    LEFT JOIN pengumpulan_tugas pt ON u.id = pt.user_id AND pt.tugas_id = ?
    WHERE u.kelas IN ($placeholders)
      AND (u.role IS NULL OR u.role != 'admin')
      AND u.status = 'aktif'
    ORDER BY u.kelas ASC, u.username ASC
";

$params = array_merge([$tugas_id], $list_kelas);
$stmtSiswa = $pdo->prepare($querySiswa);
$stmtSiswa->execute($params);
$rekap = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// HITUNG STATISTIK
// ============================================================
$total_siswa    = count($rekap);
$total_upload   = 0;
$total_dinilai  = 0;
$sum_nilai      = 0;
$total_terlambat = 0;

// Deadline check dulu (biar bisa dipakai di loop)
$deadline_ts  = !empty($tugas['deadline']) ? strtotime($tugas['deadline']) : null;
$is_lewat     = $deadline_ts && time() > $deadline_ts;

foreach ($rekap as $r) {
    if (!empty($r['file_path'])) {
        $total_upload++;
        // Hitung terlambat
        if ($deadline_ts && strtotime($r['uploaded_at']) > $deadline_ts) {
            $total_terlambat++;
        }
    }
    if ($r['nilai'] !== null) {
        $total_dinilai++;
        $sum_nilai += (float)$r['nilai'];
    }
}

$total_belum_upload = $total_siswa - $total_upload;
$total_belum_nilai  = $total_upload - $total_dinilai;
$rata_nilai         = $total_dinilai > 0 ? round($sum_nilai / $total_dinilai, 2) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Nilai: <?= htmlspecialchars($tugas['judul']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .nilai-input { max-width: 80px; text-align: center; font-weight: 600; }
        .catatan-input { font-size: 12px; }
        .feedback-msg { font-size: 11px; min-height: 14px; }
        .row-unggul { background: #f0fdf4 !important; }
        .row-kosong { background: #fff5f5 !important; }
        tr.row-unggul:hover { background: #dcfce7 !important; }
        tr.row-kosong:hover { background: #fee2e2 !important; }
        .stat-card { border-left: 4px solid; }
        .stat-upload  { border-color: #0d6efd; }
        .stat-nilai   { border-color: #198754; }
        .stat-belum   { border-color: #dc3545; }
        .stat-rata    { border-color: #fd7e14; }
        .stat-lambat  { border-color: #dc3545; }
        .catatan-cell { max-width: 200px; }
        .ketepatan-cell { font-size: 11px; }
    </style>
</head>
<body>
<div class="container-fluid py-4 px-4">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold m-0">
                <i class="bi bi-clipboard-check text-success"></i>
                Rekap Nilai: <?= htmlspecialchars($tugas['judul']) ?>
            </h4>
            <div class="mt-1">
                <span class="badge bg-primary">
                    <i class="bi bi-people"></i> Kelas: <?= htmlspecialchars($tugas['kelas_target']) ?>
                </span>
                <?php if ($deadline_ts): ?>
                    <span class="badge bg-<?= $is_lewat ? 'danger' : 'warning text-dark' ?>">
                        <i class="bi bi-clock"></i> Deadline: <?= date('d M Y, H:i', $deadline_ts) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="management_tugas.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- STATISTIK -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3 col-lg">
            <div class="card shadow-sm border-0 stat-card stat-upload h-100">
                <div class="card-body py-2 px-3">
                    <small class="text-muted d-block">Total Siswa</small>
                    <h4 class="fw-bold m-0"><?= $total_siswa ?></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg">
            <div class="card shadow-sm border-0 stat-card stat-nilai h-100">
                <div class="card-body py-2 px-3">
                    <small class="text-muted d-block">Sudah Upload</small>
                    <h4 class="fw-bold m-0 text-success"><?= $total_upload ?> <small class="text-muted fs-6">/ <?= $total_siswa ?></small></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg">
            <div class="card shadow-sm border-0 stat-card stat-belum h-100">
                <div class="card-body py-2 px-3">
                    <small class="text-muted d-block">Sudah Dinilai</small>
                    <h4 class="fw-bold m-0 text-primary"><?= $total_dinilai ?> <small class="text-muted fs-6">/ <?= $total_upload ?></small></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg">
            <div class="card shadow-sm border-0 stat-card stat-rata h-100">
                <div class="card-body py-2 px-3">
                    <small class="text-muted d-block">Rata-rata Nilai</small>
                    <h4 class="fw-bold m-0 text-warning"><?= $rata_nilai ?: '-' ?></h4>
                </div>
            </div>
        </div>
        <?php if ($deadline_ts): ?>
        <div class="col-6 col-md-3 col-lg">
            <div class="card shadow-sm border-0 stat-card stat-lambat h-100">
                <div class="card-body py-2 px-3">
                    <small class="text-muted d-block">Terlambat</small>
                    <h4 class="fw-bold m-0 text-danger"><?= $total_terlambat ?> <small class="text-muted fs-6">/ <?= $total_upload ?></small></h4>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- INFO PROGRESS -->
    <?php if ($total_belum_upload > 0 || $total_belum_nilai > 0): ?>
        <div class="alert alert-warning py-2 mb-3 small">
            <i class="bi bi-info-circle"></i>
            <?php if ($total_belum_upload > 0): ?>
                <strong><?= $total_belum_upload ?></strong> siswa belum upload.
            <?php endif; ?>
            <?php if ($total_belum_nilai > 0): ?>
                <strong><?= $total_belum_nilai ?></strong> file belum dinilai.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-success py-2 mb-3 small">
            <i class="bi bi-check-circle-fill"></i> Semua siswa sudah upload & sudah dinilai. 🎉
        </div>
    <?php endif; ?>

    <!-- TABEL -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>Nama / NIS</th>
                            <th style="width: 80px;">Kelas</th>
                            <th style="width: 110px;">Status</th>
                            <th style="width: 130px;">Waktu Upload</th>
                            <th style="width: 100px;" class="text-center">Ketepatan</th>
                            <th style="width: 100px;" class="text-center">Bukti</th>
                            <th style="width: 110px;" class="text-center">Nilai</th>
                            <th class="catatan-cell">Catatan Guru</th>
                            <th style="width: 70px;" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($rekap as $row): 
                            $sudah_upload = !empty($row['file_path']);
                            $sudah_nilai  = $row['nilai'] !== null;
                            
                            // Status ketepatan
                            $status_waktu = null;
                            if ($sudah_upload && $deadline_ts) {
                                $uploadTs     = strtotime($row['uploaded_at']);
                                $status_waktu = ($uploadTs > $deadline_ts) ? 'terlambat' : 'tepat';
                            }
                            
                            $row_class = '';
                            if (!$sudah_upload)   $row_class = 'row-kosong';
                            elseif ($sudah_nilai) $row_class = 'row-unggul';
                        ?>
                        <tr class="<?= $row_class ?>" data-row="<?= (int)$row['pengumpulan_id'] ?>">
                            <td><?= $no++ ?></td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($row['nama_asli'] ?: $row['username']) ?></div>
                                <?php if (!empty($row['nama_asli'])): ?>
                                    <small class="text-muted"><?= htmlspecialchars($row['username']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($row['kelas']) ?></span></td>
                            <td>
                                <?php if ($sudah_upload): ?>
                                    <span class="badge bg-success">✅ Upload</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">❌ Belum</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted">
                                <?= $row['uploaded_at'] ? date('d M Y, H:i', strtotime($row['uploaded_at'])) : '-' ?>
                            </td>
                            <td class="text-center ketepatan-cell">
                                <?php if ($status_waktu === 'terlambat'): ?>
                                    <span class="badge bg-danger" title="Lewat deadline">
                                        <i class="bi bi-exclamation-triangle"></i> Terlambat
                                    </span>
                                <?php elseif ($status_waktu === 'tepat'): ?>
                                    <span class="badge bg-success" title="Tepat waktu">
                                        <i class="bi bi-check-circle"></i> Tepat
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($sudah_upload): 
                                    $ext = strtolower(pathinfo($row['file_path'], PATHINFO_EXTENSION));
                                    $url = 'download_tugas.php?id=' . (int)$row['pengumpulan_id'];
                                ?>
                                    <?php if (in_array($ext, ['jpg','jpeg','png'])): ?>
                                        <a href="<?= htmlspecialchars($url) ?>" target="_blank" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-image"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= htmlspecialchars($url) ?>" target="_blank" 
                                           class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($sudah_upload): ?>
                                    <input type="number" 
                                           class="form-control form-control-sm nilai-input mx-auto"
                                           value="<?= $row['nilai'] !== null ? htmlspecialchars($row['nilai']) : '' ?>"
                                           min="0" max="100" step="0.01"
                                           placeholder="-"
                                           data-submission-id="<?= (int)$row['pengumpulan_id'] ?>">
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($sudah_upload): ?>
                                    <input type="text"
                                           class="form-control form-control-sm catatan-input"
                                           value="<?= htmlspecialchars($row['catatan_guru'] ?? '') ?>"
                                           placeholder="Catatan (opsional)..."
                                           maxlength="255"
                                           data-submission-id="<?= (int)$row['pengumpulan_id'] ?>">
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($sudah_upload): ?>
                                    <button class="btn btn-success btn-sm btn-simpan" 
                                            data-submission-id="<?= (int)$row['pengumpulan_id'] ?>"
                                            title="Simpan">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <div class="feedback-msg text-success" 
                                         data-for="<?= (int)$row['pengumpulan_id'] ?>"></div>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (empty($rekap)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                Tidak ada siswa aktif di kelas target.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <p class="text-muted small mt-3 mb-0">
        <i class="bi bi-info-circle"></i>
        Isi nilai → klik tombol <i class="bi bi-check-lg"></i> atau tekan <kbd>Enter</kbd>. 
        Kosongkan nilai untuk menghapus. 
        <span class="text-success">Hijau</span> = sudah dinilai · 
        <span class="text-danger">Merah</span> = belum upload.
    </p>
</div>

<script>
// ============================================================
// SIMPAN NILAI PER BARIS
// ============================================================
async function simpanNilai(submissionId) {
    const inputNilai   = document.querySelector(`.nilai-input[data-submission-id="${submissionId}"]`);
    const inputCatatan = document.querySelector(`.catatan-input[data-submission-id="${submissionId}"]`);
    const btn          = document.querySelector(`.btn-simpan[data-submission-id="${submissionId}"]`);
    const feedback     = document.querySelector(`.feedback-msg[data-for="${submissionId}"]`);
    const row          = document.querySelector(`tr[data-row="${submissionId}"]`);

    if (!inputNilai || !btn) return;

    const nilai   = inputNilai.value.trim();
    const catatan = inputCatatan ? inputCatatan.value.trim() : '';

    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    feedback.textContent = '';

    try {
        const fd = new FormData();
        fd.append('action', 'simpan_nilai');
        fd.append('submission_id', submissionId);
        fd.append('nilai', nilai);
        fd.append('catatan', catatan);

        const res  = await fetch('rekap_nilai_tugas.php?id=<?= $tugas_id ?>', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.status === 'success') {
            feedback.textContent = '✓ ' + data.message;
            feedback.className = 'feedback-msg text-success';

            if (row) {
                if (data.nilai === null) {
                    row.classList.remove('row-unggul');
                } else {
                    row.classList.add('row-unggul');
                }
            }

            setTimeout(() => { feedback.textContent = ''; }, 2500);
        } else {
            feedback.textContent = '✗ ' + data.message;
            feedback.className = 'feedback-msg text-danger';
        }
    } catch (err) {
        feedback.textContent = '✗ Error koneksi';
        feedback.className = 'feedback-msg text-danger';
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}

// Tombol simpan
document.querySelectorAll('.btn-simpan').forEach(btn => {
    btn.addEventListener('click', function () {
        simpanNilai(this.dataset.submissionId);
    });
});

// Enter di input nilai atau catatan → simpan
document.querySelectorAll('.nilai-input, .catatan-input').forEach(input => {
    input.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            simpanNilai(this.dataset.submissionId);
        }
    });
});

// Auto-select saat focus input nilai
document.querySelectorAll('.nilai-input').forEach(input => {
    input.addEventListener('focus', function () {
        this.select();
    });
});

// Navigasi keyboard: panah atas/bawah pindah baris
document.querySelectorAll('.nilai-input').forEach(input => {
    input.addEventListener('keydown', function (e) {
        if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;

        const allInputs = Array.from(document.querySelectorAll('.nilai-input:not([disabled])'));
        const idx = allInputs.indexOf(input);
        const next = e.key === 'ArrowDown' ? allInputs[idx + 1] : allInputs[idx - 1];
        if (next) {
            e.preventDefault();
            next.focus();
        }
    });
});
</script>
</body>
</html>