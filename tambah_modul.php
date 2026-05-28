<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Tambah Modul</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            
            <a href="index.php" class="btn btn-sm btn-secondary mb-3">← Kembali ke Dashboard</a>
            
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0 fw-bold">🚀 Tambah Modul Baru</h5>
                </div>
                
                <div class="card-body p-4">
                    <form action="proses_tambah.php" method="POST">
                        
                        <div class="mb-3">
                            <label for="nama_modul" class="form-label fw-semibold">Nama / Judul Modul</label>
                            <input type="text" class="form-control" id="nama_modul" name="nama_modul" placeholder="Contoh: Konfigurasi OSPF RouterOS" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="kategori" class="form-label fw-semibold">Kategori Pembelajaran</label>
                            <select class="form-select" id="kategori" name="kategori" required>
                                <option value="" selected disabled>-- Pilih Kategori --</option>
                                <option value="Network">Network</option>
                                <option value="IoT">Internet of Things (IoT)</option>
                                <option value="Cloud Computing">Cloud Computing</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="link_dokumen" class="form-label fw-semibold">Link Dokumen (RPP / LKPD / Modul)</label>
                            <input type="url" class="form-control" id="link_dokumen" name="link_dokumen" placeholder="https://drive.google.com/..." required>
                        </div>
                        
                        <hr class="my-4 text-muted">
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary fw-bold py-2">💾 Simpan Modul</button>
                            <button type="reset" class="btn btn-outline-danger btn-sm">Reset Form</button>
                        </div>
                        
                    </form>
                </div>
            </div> </div>
    </div>
</div>

</body>
</html>