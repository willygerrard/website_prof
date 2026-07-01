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

    <!-- 1. NAVBAR (Sama persis seperti index.php Bapak) -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container px-4 px-lg-5">
                <a class="navbar-brand" href="index.php">Modul Pembelajaran SIJA</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    
                </ul>
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

        <!-- Header-->
 <header class="py-5" style="
    background: linear-gradient(to bottom, rgba(15, 23, 42, 0.85), rgba(30, 41, 59, 0.9)), 
                url('https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=800'); 
    background-size: cover; 
    background-position: center;">
    
    <div class="container px-4 px-lg-5 my-5">
        <div class="text-center text-white">
            <h1 class="display-4 fw-bolder"> 
                Pusat Pembelajaran SIJA
            </h1>
            <p class="lead fw-normal text-white-50 mb-0">
               Selamat datang di portal lab kendali materi mandiri</p>
            </div>
    </div>
</header>
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0 fw-bold">🚀 Tambah Modul Baru</h5>
                </div>
                
                <div class="card-body p-4">
                    <form action="proses_tambah.php" method="POST" enctype="multipart/form-data">
                        
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
    <!-- Footer-->
        <footer class="py-5 bg-dark">
            <div class="container"><p class="m-0 text-center text-white">Copyright &copy; SIJA Website 2026</p></div>
        </footer>
        <!-- Bootstrap core JS-->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>