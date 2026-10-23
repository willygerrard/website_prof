<?php
require_once 'session.php';
require_once 'csrf_helper.php';
require 'koneksi.php';

checkLogin();
checkRole(['admin']);

const AIMM_PATH       = '/var/www/data_private/modul_ai/pending';
const AIMM_PUBLIC_DIR = '/var/www/html/modul';   // docroot container = <root>/modul (nginx: disable_symlinks off)
const AIMM_PUBLIC_URL = 'modul';                 // prefix URL publik

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_require_valid_post();
    
    $moduleId = (int)($_POST['module_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($moduleId > 0 && in_array($action, ['approve', 'reject'])) {
        try {
            if ($action === 'approve') {
                // Get module info first
                $stmt = $pdo->prepare("SELECT ai_file, title FROM modules WHERE id = ? AND status = 'pending'");
                $stmt->execute([$moduleId]);
                $module = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$module || empty($module['ai_file'])) {
                    $errorMsg = 'File modul AI tidak ditemukan.';
                } else {
                    $aiFile     = $module['ai_file'];
                    $sourcePath = rtrim(AIMM_PATH, '/') . '/' . $aiFile;
                    $destPath   = rtrim(AIMM_PUBLIC_DIR, '/') . '/' . $aiFile;

                    if (!is_file($sourcePath)) {
                        $errorMsg = 'File modul AI tidak ditemukan di penyimpanan.';
                    } elseif (!is_dir(AIMM_PUBLIC_DIR)
                              && !mkdir(AIMM_PUBLIC_DIR, 0775, true)
                              && !is_dir(AIMM_PUBLIC_DIR)) {
                        error_log('[management_pending] gagal mkdir ' . AIMM_PUBLIC_DIR);
                        $errorMsg = 'Folder publik modul tidak dapat disiapkan.';
                    } else {
                        // Buat symlink dari storage privat ke docroot/modul.
                        // Timpa symlink lama bila modul di-approve ulang.
                        if (is_link($destPath) || file_exists($destPath)) {
                            @unlink($destPath);
                        }

                        if (!@symlink($sourcePath, $destPath)) {
                            error_log('[management_pending] gagal symlink ' . $sourcePath . ' -> ' . $destPath);
                            $errorMsg = 'Gagal membuat tautan publik modul.';
                        } else {
                            try {
                                $publicPath = AIMM_PUBLIC_URL . '/' . $aiFile;
                                $stmt = $pdo->prepare("UPDATE modules SET status = 'approved', file_path = ? WHERE id = ?");
                                $stmt->execute([$publicPath, $moduleId]);

                                $successMsg = "Modul <strong>" . htmlspecialchars($module['title']) . "</strong> berhasil di-approve dan dipublikasikan!";
                            } catch (PDOException $e) {
                                @unlink($destPath); // jangan sisakan symlink yatim
                                throw $e;
                            }
                        }
                    }
                }
            } else {
                // Reject: delete the pending file and the database record
                $stmt = $pdo->prepare("SELECT ai_file, title FROM modules WHERE id = ? AND status = 'pending'");
                $stmt->execute([$moduleId]);
                $module = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($module && !empty($module['ai_file'])) {
                    $sourcePath = rtrim(AIMM_PATH, '/') . '/' . $module['ai_file'];
                    if (file_exists($sourcePath)) {
                        @unlink($sourcePath);
                    }
                    $destPath = rtrim(AIMM_PUBLIC_DIR, '/') . '/' . $module['ai_file'];
                    if (is_link($destPath)) {
                        @unlink($destPath);
                    }
                }
                
                $stmt = $pdo->prepare("DELETE FROM modules WHERE id = ? AND status = 'pending'");
                $stmt->execute([$moduleId]);
                
                $successMsg = "Modul <strong>" . htmlspecialchars($module['title'] ?? '') . "</strong> ditolak dan dihapus.";
            }
        } catch (PDOException $e) {
            error_log('[management_pending] ' . $action . ' error: ' . $e->getMessage());
            $errorMsg = 'Terjadi kesalahan database. Silakan coba lagi.';
        }
    }
}

// Query pending modules
$stmt = $pdo->prepare("SELECT * FROM modules WHERE status = 'pending' ORDER BY created_at DESC");
$stmt->execute();
$pendingModules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query approved modules (for reference)
$stmt = $pdo->prepare("SELECT * FROM modules WHERE status = 'approved' ORDER BY created_at DESC LIMIT 20");
$stmt->execute();
$approvedModules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$delete_token = csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Modul Pending - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .pending-card {
            border-left: 4px solid #ffc107;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .pending-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        .approved-card {
            border-left: 4px solid #198754;
        }
        .module-preview-frame {
            width: 100%;
            height: 500px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #fff;
        }
        .badge-status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }
        .badge-status-approved {
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #198754;
        }
        .action-btn-group .btn {
            min-width: 100px;
        }
        @media (max-width: 576px) {
            .action-btn-group .btn {
                min-width: auto;
                flex: 1;
            }
        }
    </style>
</head>
<body class="bg-light">

<?php 
$hero_title = 'Kelola Modul Pending';
$hero_subtitle = 'Review dan approve modul yang dibuat oleh AI sebelum dipublikasikan ke siswa';
include __DIR__ . '/includes/admin_header.php'; 
?>

<div class="container py-4" style="max-width: 1200px;">

    <!-- Alert Messages -->
    <?php if (isset($successMsg)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= $successMsg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $errorMsg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="pendingTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                <i class="bi bi-hourglass-split me-1"></i> Pending Review
                <span class="badge bg-warning text-dark ms-2"><?= count($pendingModules) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab">
                <i class="bi bi-check-circle-fill me-1"></i> Approved (Recent)
                <span class="badge bg-success ms-2"><?= count($approvedModules) ?></span>
            </button>
        </li>
        <li class="nav-item ms-auto" role="presentation">
            <a href="ai_module_maker.php" class="btn btn-primary btn-sm">
                <i class="bi bi-stars me-1"></i> Buat Modul Baru (AI)
            </a>
        </li>
    </ul>

    <div class="tab-content" id="pendingTabsContent">
        <!-- PENDING TAB -->
        <div class="tab-pane fade show active" id="pending" role="tabpanel">
            <?php if (empty($pendingModules)): ?>
                <div class="text-center py-5">
                    <div class="display-1 text-muted">📭</div>
                    <h4 class="text-muted mt-3">Tidak ada modul pending</h4>
                    <p class="text-muted">Semua modul AI sudah di-review. Luar biasa! 🎉</p>
                    <a href="ai_module_maker.php" class="btn btn-primary mt-2">
                        <i class="bi bi-stars me-1"></i> Buat Modul Baru
                    </a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($pendingModules as $modul): ?>
                    <div class="col-12 col-lg-6 col-xl-4">
                        <div class="card pending-card shadow-sm h-100">
                            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                                <span class="badge badge-status-pending fw-semibold px-3 py-2">
                                    <i class="bi bi-clock-history me-1"></i> Menunggu Review
                                </span>
                                <small class="text-muted"><?= date('d M Y, H:i', strtotime($modul['created_at'])) ?></small>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title fw-bold text-dark"><?= htmlspecialchars($modul['title']) ?></h5>
                                <p class="card-text text-muted small"><?= htmlspecialchars($modul['description'] ?? 'Tidak ada deskripsi') ?></p>
                                
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <span class="badge bg-info text-dark px-2.5 py-1.5">
                                        <?= htmlspecialchars($modul['category']) ?>
                                    </span>
                                    <span class="badge bg-secondary px-2.5 py-1.5">
                                        <?= htmlspecialchars($modul['jenis_resource']) ?>
                                    </span>
                                    <span class="badge bg-primary px-2.5 py-1.5">
                                        Kelas: <?= htmlspecialchars($modul['kelas_target']) ?>
                                    </span>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <?php if (!empty($modul['ai_file'])): ?>
                                    <button type="button" class="btn btn-outline-primary btn-sm flex-fill preview-btn"
                                            data-module-title="<?= htmlspecialchars($modul['title'], ENT_QUOTES) ?>"
                                            data-ai-file="<?= htmlspecialchars($modul['ai_file'], ENT_QUOTES) ?>">
                                        <i class="bi bi-eye me-1"></i> Preview
                                    </button>
                                    <?php else: ?>
                                    <span class="text-muted small">File modul tidak tersedia</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-0 action-btn-group">
                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Yakin ingin MENOLAK dan MENGHAPUS modul ini? Tindakan ini tidak bisa dibatalkan.')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($delete_token) ?>">
                                    <input type="hidden" name="module_id" value="<?= (int)$modul['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-outline-danger btn-sm flex-fill">
                                        <i class="bi bi-x-lg me-1"></i> Tolak
                                    </button>
                                </form>
                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Yakin ingin MENGAPPROVE modul ini? Modul akan dipublikasikan ke siswa.')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($delete_token) ?>">
                                    <input type="hidden" name="module_id" value="<?= (int)$modul['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-success btn-sm flex-fill fw-bold">
                                        <i class="bi bi-check-lg me-1"></i> Approve
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- APPROVED TAB -->
        <div class="tab-pane fade" id="approved" role="tabpanel">
            <?php if (empty($approvedModules)): ?>
                <div class="text-center py-5">
                    <div class="display-1 text-muted">✅</div>
                    <h4 class="text-muted mt-3">Belum ada modul approved</h4>
                    <p class="text-muted">Modul yang di-approve akan muncul di sini.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 5%">No</th>
                                <th style="width: 40%">Nama Modul</th>
                                <th style="width: 15%">Kategori</th>
                                <th style="width: 10%">Tipe</th>
                                <th style="width: 10%">Kelas</th>
                                <th style="width: 15%">Tanggal</th>
                                <th style="width: 5%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($approvedModules as $modul): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td class="fw-semibold text-dark"><?= htmlspecialchars($modul['title']) ?></td>
                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($modul['category']) ?></span></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($modul['jenis_resource']) ?></span></td>
                                <td><span class="badge bg-primary"><?= htmlspecialchars($modul['kelas_target']) ?></span></td>
                                <td class="text-muted small"><?= date('d M Y', strtotime($modul['created_at'])) ?></td>
                                <td class="text-center">
                                    <a href="<?= htmlspecialchars($modul['file_path']) ?>" target="_blank" class="btn btn-outline-primary btn-sm" title="Lihat Modul">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Back Link -->
    <div class="mt-4 text-center">
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Beranda
        </a>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-lg-down">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="previewModalLabel"><i class="bi bi-eye me-2"></i> Preview Modul</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe class="module-preview-frame" id="previewFrame" src="about:blank" sandbox title="Preview Modul"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
    const previewFrame = document.getElementById('previewFrame');
    
    document.querySelectorAll('.preview-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const moduleId = this.dataset.moduleId;
            const moduleTitle = this.dataset.moduleTitle;
            const aiFile = this.dataset.aiFile;
            
            document.getElementById('previewModalLabel').innerHTML = '<i class="bi bi-eye me-2"></i> Preview: ' + moduleTitle;
            
            // Load preview from the pending file
            const previewUrl = 'ai_module_preview.php?file=' + encodeURIComponent(aiFile) + '&ts=' + Date.now();
            previewFrame.src = previewUrl;
            
            previewModal.show();
        });
    });
    
    // Reset iframe when modal closes
    document.getElementById('previewModal').addEventListener('hidden.bs.modal', function() {
        previewFrame.src = 'about:blank';
    });
});
</script>
</body>
</html>