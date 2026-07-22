<?php
include 'koneksi.php';
session_start();
include 'csrf_helper.php';

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$pesan = '';
$pesan_type = '';

// Daftar materi yang sudah ada, untuk dropdown + opsi tambah baru
$materi_list = $pdo->query("SELECT DISTINCT materi FROM kuis_soal WHERE materi IS NOT NULL AND materi <> '' ORDER BY materi")
                    ->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kategori']) && !isset($_POST['quiz_json'])) {
    csrf_require_valid_post();

    $kategori   = trim($_POST['kategori'] ?? '');
    $pertanyaan = trim($_POST['pertanyaan'] ?? '');
    $pilihan_a  = trim($_POST['pilihan_a'] ?? '');
    $pilihan_b  = trim($_POST['pilihan_b'] ?? '');
    $pilihan_c  = trim($_POST['pilihan_c'] ?? '');
    $pilihan_d  = trim($_POST['pilihan_d'] ?? '');
    $jawaban    = trim($_POST['jawaban'] ?? '');
    $level      = trim($_POST['level'] ?? '');

    $materi_baru  = trim($_POST['materi_baru'] ?? '');
    $materi_pilih = trim($_POST['materi_pilih'] ?? '');
    $materi       = $materi_baru !== '' ? $materi_baru : ($materi_pilih !== '' ? $materi_pilih : null);

    if ($kategori && $pertanyaan && $pilihan_a && $pilihan_b && $pilihan_c && $pilihan_d && $jawaban && $level) {
        $stmt = $pdo->prepare("INSERT INTO kuis_soal (kategori, level, materi, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, jawaban) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$kategori, $level, $materi, $pertanyaan, $pilihan_a, $pilihan_b, $pilihan_c, $pilihan_d, $jawaban]);
        $pesan = '✅ Soal berhasil ditambahkan!';
        $pesan_type = 'success';
    } else {
        $pesan = '⚠️ Semua field wajib diisi!';
        $pesan_type = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Soal Kuis - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Pastikan card memenuhi ruang grid */
    .card { border-radius: 15px; height: 100%; display: flex; flex-direction: column; }
    
    /* Gunakan flex-grow agar konten mengisi sisa ruang */
    .card-body { flex: 1; display: flex; flex-direction: column; }
    
    /* Textarea dan preview container disamakan tingginya */
    #quizRawText { flex-grow: 1; min-height: 300px; resize: none; 
    width: 100%;
    padding: 10px;
    text-align: left; /* Memaksa rata kiri */
    white-space: pre-wrap; /* Menjaga format baris tapi menghilangkan spasi berlebih */
    display: block;
    
    }
    #previewContainer { flex-grow: 1; min-height: 300px; overflow-y: auto; }
    
    #editSection {
    display: flex;
    flex-direction: column;
    width: 100%;
    text-align: left;
    }

    .card-header { font-weight: 600; }
    </style>
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
    
    <div class="container mt-4">
        <div class="mb-3">
        <a href="pintu-rahasia-sija" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        </div>
        <?php if ($pesan): ?>
            <div class="alert alert-<?= $pesan_type ?>"><?= $pesan ?></div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-dark text-white">Metadata Soal</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Kategori</label>
                        <select class="form-select" id="sharedKategori" onchange="syncSharedMetadata()">
                            <option value="Network">Network</option>
                            <option value="IoT">IoT</option>
                            <option value="Cloud Computing">Cloud</option>
                            <option value="DevOps">DevOps</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Level</label>
                        <select class="form-select" id="sharedLevel" onchange="syncSharedMetadata()">
                            <option value="pemula">Pemula</option>
                            <option value="menengah">Menengah</option>
                            <option value="mahir">Mahir</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Materi</label>
                        <select class="form-select" id="sharedMateri" onchange="syncSharedMetadata(); toggleMateriBaru(this, 'sharedMateriBaru')">
                            <option value="">-- Belum Ditandai --</option>
                            <?php foreach ($materi_list as $m): ?>
                                <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                            <?php endforeach; ?>
                            <option value="__baru__">+ Tambah materi baru...</option>
                        </select>
                        <input type="text" id="sharedMateriBaru" class="form-control mt-2"
                               placeholder="Ketik nama materi baru" style="display:none;">
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-primary text-white">Tambah Soal Manual</div>
                    <div class="card-body">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <div class="mb-2">
                                <label class="form-label">Kategori</label>
                                <select class="form-select" name="kategori" id="manualKategori" required>
                                    <option value="Network">Network</option>
                                    <option value="IoT">IoT</option>
                                    <option value="Cloud Computing">Cloud</option>
                                    <option value="DevOps">DevOps</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Level</label>
                                <select class="form-select" name="level" id="manualLevel" required>
                                    <option value="pemula">Pemula</option>
                                    <option value="menengah">Menengah</option>
                                    <option value="mahir">Mahir</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Materi</label>
                                <select class="form-select" name="materi_pilih" id="materi_pilih_manual" onchange="toggleMateriBaru(this, 'materi_baru_manual')">
                                    <option value="">-- Belum Ditandai --</option>
                                    <?php foreach ($materi_list as $m): ?>
                                    <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                                    <?php endforeach; ?>
                                    <option value="__baru__">+ Tambah materi baru...</option>
                                </select>
                                <input type="text" name="materi_baru" id="materi_baru_manual" class="form-control mt-2"
                                       placeholder="Ketik nama materi baru" style="display:none;">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Pertanyaan</label>
                                <textarea class="form-control" name="pertanyaan" rows="2" required></textarea>
                            </div>
                            <div class="row g-2">
                                <?php foreach(['a','b','c','d'] as $h): ?>
                                    <div class="col-6"><input type="text" class="form-control" name="pilihan_<?= $h ?>" placeholder="Pilihan <?= strtoupper($h) ?>" required></div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-2">
                                <label class="form-label">Jawaban Benar</label>
                                <select class="form-select" name="jawaban" required>
                                    <option value="a">A</option><option value="b">B</option>
                                    <option value="c">C</option><option value="d">D</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 mt-3">Simpan Soal</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-success text-white">AI Quiz Import</div>
                    <div class="card-body d-flex flex-column">

                        <div class="alert alert-info py-2 px-3 small">
                        <i class="bi bi-info-circle-fill"></i> <strong>Cara Pakai:</strong><br>
                        Pilih kategori, level, dan materi di panel atas terlebih dahulu, lalu paste format: Pertanyaan [enter] Pilihan A, B, C, D [enter] Answer: [Jawaban]. 
                        Pastikan ada 1 baris kosong antar soal.
                        </div>

                        <div id="editSection" class="d-flex flex-column flex-grow-1">
                        <textarea id="quizRawText" class="form-control mb-3" 
                        placeholder="Paste soal di sini...
                        1. What is the main database system used in our LMS?
                        A) MongoDb
                        B) MariaDB
                        C) PostgreSQL
                        D) SQLite
                        Answer: B"

                        style="min-height: 250px; resize: vertical;"></textarea>           
                            <button class="btn btn-success w-100" onclick="generatePreviewFromText()">Generate Preview</button>
                        </div>
                        <div id="previewSection" style="display:none;">
                            <div id="previewContainer" class="border p-2 mb-2 bg-light"></div>
                            <form action="save_quiz.php" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" id="finalJsonData" name="quiz_json">
                                <input type="hidden" id="inputKategori" name="kategori">
                                <input type="hidden" id="inputLevel" name="level">
                                <input type="hidden" id="inputMateri" name="materi">
                                <button type="button" class="btn btn-secondary" onclick="backToEdit()">Edit Kembali</button>
                                <button type="submit" class="btn btn-success">Simpan ke Database</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function syncSharedMetadata() {
            const sharedKategori = document.getElementById('sharedKategori')?.value || '';
            const sharedLevel = document.getElementById('sharedLevel')?.value || '';
            const sharedMateriSelect = document.getElementById('sharedMateri');
            const sharedMateri = sharedMateriSelect?.value || '';
            const sharedMateriBaru = document.getElementById('sharedMateriBaru')?.value.trim() || '';
            const finalSharedMateri = sharedMateri === '__baru__' ? sharedMateriBaru : sharedMateri;

            const manualKategori = document.getElementById('manualKategori');
            const manualLevel = document.getElementById('manualLevel');
            const manualMateri = document.getElementById('materi_pilih_manual');

            if (manualKategori) manualKategori.value = sharedKategori;
            if (manualLevel) manualLevel.value = sharedLevel;
            if (manualMateri) manualMateri.value = sharedMateri;

            const inputKategori = document.getElementById('inputKategori');
            const inputLevel = document.getElementById('inputLevel');
            const inputMateri = document.getElementById('inputMateri');

            if (inputKategori) inputKategori.value = sharedKategori;
            if (inputLevel) inputLevel.value = sharedLevel;
            if (inputMateri) inputMateri.value = finalSharedMateri;
        }

        function toggleMateriBaru(select, inputId) {
            const inputBaru = document.getElementById(inputId);
            if (select.value === '__baru__') {
                inputBaru.style.display = 'block';
            } else {
                inputBaru.style.display = 'none';
                inputBaru.value = '';
            }
        }

        function generatePreviewFromText() {
            // 1. Ambil nilai metadata dari panel atas
            const kat = document.getElementById('sharedKategori')?.value || document.querySelector('select[name="kategori"]').value;
            const lvl = document.getElementById('sharedLevel')?.value || document.querySelector('select[name="level"]').value;
            const sharedMateriSelect = document.getElementById('sharedMateri');
            const sharedMateriBaru = document.getElementById('sharedMateriBaru')?.value.trim() || '';
            const materiPilih = sharedMateriSelect?.value || document.getElementById('materi_pilih_manual').value;
            const materiBaru = sharedMateriBaru || document.getElementById('materi_baru_manual').value.trim();
            const materiFinal = materiPilih === '__baru__' ? materiBaru : materiPilih;

            // 2. Masukkan ke hidden input di form kanan
            document.getElementById('inputKategori').value = kat;
            document.getElementById('inputLevel').value = lvl;
            document.getElementById('inputMateri').value = materiFinal;

            
            const rawText = document.getElementById('quizRawText').value.trim();
            const previewContainer = document.getElementById('previewContainer');
            
            if (!rawText) {
                alert("Silakan paste soal terlebih dahulu!");
                return;
            }

            // Logic Parsing (sama persis dengan quizparser.php)
            const questionBlocks = rawText.split(/\n\s*\n/);
            const questionsArray = [];

            for (let block of questionBlocks) {
                block = block.trim();
                if (!block) continue;
                const lines = block.split('\n').map(line => line.trim()).filter(line => line.length > 0);
                
                let questionText = "";
                let options = [];
                let correctAnswer = "";

                lines.forEach(line => {
                    if (line.match(/^(ans|answer|correct|key)\s*:\s*/i)) {
                        correctAnswer = line.replace(/^(ans|answer|correct|key)\s*:\s*/i, '').trim();
                    } else if (line.match(/^[A-E][\)|\]\.]\s*/i)) {
                        options.push(line.replace(/^[A-E][\)|\]\.]\s*/i, '').trim());
                    } else if (line.match(/^\d+[\.\)]\s*/)) {
                        questionText = line.replace(/^\d+[\.\)]\s*/, '').trim();
                    } else {
                        if (!questionText) questionText = line;
                    }
                });

            // Match shorthand (A/B/C) to text
            if (correctAnswer.length === 1 && ['A','B','C','D'].includes(correctAnswer.toUpperCase())) {
                const idx = correctAnswer.toUpperCase().charCodeAt(0) - 65;
                if (options[idx]) correctAnswer = options[idx];
            }

            if (questionText && options.length > 0) {
                questionsArray.push({ question_text: questionText, options: options, correct_answer: correctAnswer });
            }
        }

        // Tampilkan ke Preview
        previewContainer.innerHTML = "";
        questionsArray.forEach((q, idx) => {
            let opts = q.options.map(o => `<li>${o} ${o === q.correct_answer ? '✓' : ''}</li>`).join('');
            previewContainer.innerHTML += `<div class="mb-3 p-2 border"><strong>Q${idx + 1}: ${q.question_text}</strong><ul>${opts}</ul></div>`;
        });

        // Set ke input hidden agar bisa dikirim ke save-quiz.php
        document.getElementById('finalJsonData').value = JSON.stringify({ questions: questionsArray });

        // Toggle UI
        document.getElementById('editSection').style.display = 'none';
        document.getElementById('previewSection').style.display = 'block';
        }
            
        function backToEdit() {
            document.getElementById('editSection').style.display = 'block';
            document.getElementById('previewSection').style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', function () {
            syncSharedMetadata();
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>