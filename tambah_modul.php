<?php
session_start();
include 'csrf_helper.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Module - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Style Jumbotron/Hero Banner penunjang gambar background server rack */
        .hero-banner {
            background: linear-gradient(rgba(15, 23, 42, 0.85), rgba(15, 23, 42, 0.85)), url('path_gambar_server_bapak.jpg') no-repeat center center;
            background-size: cover;
            color: white;
            padding: 80px 0;
            text-align: center;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/admin_header.php'; ?>
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0 fw-bold">🚀 Tambah Modul Baru</h5>
                </div>
                
                <div class="card-body p-4">
                    <form action="proses_tambah.php" method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        
                        <div class="mb-3">
                            <label for="nama_modul" class="form-label fw-semibold">Nama / Judul Modul</label>
                            <input type="text" class="form-control" id="nama_modul" name="nama_modul" placeholder="Contoh: Konfigurasi OSPF RouterOS" required>
                        </div>
                        
                        <div class="mb-3">
                        <label for="jenis" class="form-label fw-semibold">Jenis Materi:</label>
                            <select class="form-select" id="jenis" name="jenis" required >
                                <option value="modul">Modul Pembelajaran (PDF)</option>
                                <option value="media">Media Pembelajaran (PPT/PPTX)</option>
                                <option value="video">Video Tutorial (Link YouTube)</option>
                            </select>
                            </div>

                        <div class="mb-3">
                            <label for="kategori" class="form-label fw-semibold">Kategori Pembelajaran</label>
                            <select class="form-select" id="kategori" name="kategori" required>
                                <option value="" selected disabled>-- Pilih Kategori --</option>
                                <option value="Network">Network</option>
                                <option value="IoT">Internet of Things (IoT)</option>
                                <option value="Cloud Computing">Cloud Computing</option>
                                <option value="DevOps">DevOps</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="kelas_target" class="form-label fw-semibold">Target Tingkat Kelas</label>
                            <select class="form-select" id="kelas_target" name="kelas_target" required>
                                <option value="semua" selected>Semua Tingkat</option>
                                <option value="X">Kelas X</option>
                                <option value="XI">Kelas XI</option>
                                <option value="XII">Kelas XII</option>
                            </select>
                            <div class="form-text text-muted small">Pilih "Semua Tingkat" kalau modul ini boleh diakses semua kelas.</div>
                        </div>

                        <div class="mb-3">
                            <label for="desc" class="form-label fw-semibold">Deskripsi Modul</label>
                            <input type="text" class="form-control" id="desc" name="desc" placeholder="Contoh: Modul pembelajaran konfigurasi OSPF dengan mikrotik" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="image_path" class="form-label fw-semibold text-secondary">Icon Modul Pembelajaran (.png / .jpg)</label>
                            <input type="file" class="form-control p-2" id="image_path" name="image_path" accept="image/png, image/jpeg, image/jpg">
                            <div class="form-text text-muted small">💡 Rekomendasi: Gunakan logo transparan berformat PNG agar menyatu dengan card menu.</div>
                        </div>

                        <div class="mb-3">
                            <label for="link_drive" class="form-label fw-semibold">Link Dokumen (RPP / LKPD / Modul)</label>
                            <input type="url" class="form-control" id="link_drive" name="link_drive" placeholder="https://drive.google.com/..." required>
                        </div>
                        
                        <hr class="my-4 text-muted">
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary fw-bold py-2">💾 Simpan Modul</button>
                            <button type="reset" class="btn btn-outline-danger btn-sm">Reset Form</button>
                        </div>
                        
                    </form>
                </div>
            </div> </div>
    <?php include 'includes/footer.php'; ?>
        <!-- Bootstrap core JS-->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>