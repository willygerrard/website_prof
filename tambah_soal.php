<?php
include 'koneksi.php';
include __DIR__ . '/includes/soal_writer.php';
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

    $jenis_soal = ($_POST['jenis_soal'] ?? '') === 'isian' ? 'isian' : 'pilgan';
    $kategori   = trim($_POST['kategori'] ?? '');
    $pertanyaan = trim($_POST['pertanyaan'] ?? '');
    $level      = trim($_POST['level'] ?? '');

    $materi_baru  = trim($_POST['materi_baru'] ?? '');
    $materi_pilih = trim($_POST['materi_pilih'] ?? '');
    $materi       = $materi_baru !== '' ? $materi_baru : ($materi_pilih !== '' ? $materi_pilih : null);

    if ($jenis_soal === 'pilgan') {
        $pilihan_a  = trim($_POST['pilihan_a'] ?? '');
        $pilihan_b  = trim($_POST['pilihan_b'] ?? '');
        $pilihan_c  = trim($_POST['pilihan_c'] ?? '');
        $pilihan_d  = trim($_POST['pilihan_d'] ?? '');
        $jawaban    = trim($_POST['jawaban'] ?? '');

        if ($kategori && $pertanyaan && $pilihan_a && $pilihan_b && $pilihan_c && $pilihan_d && $jawaban && $level) {
            simpanSoalPilgan($pdo, [
                'kategori'   => $kategori,
                'level'      => $level,
                'materi'     => $materi,
                'pertanyaan' => $pertanyaan,
                'pilihan_a'  => $pilihan_a,
                'pilihan_b'  => $pilihan_b,
                'pilihan_c'  => $pilihan_c,
                'pilihan_d'  => $pilihan_d,
                'jawaban'    => $jawaban,
            ]);
            $pesan = '✅ Soal pilihan ganda berhasil ditambahkan!';
            $pesan_type = 'success';
        } else {
            $pesan = '⚠️ Semua field wajib diisi!';
            $pesan_type = 'danger';
        }
    } else {
        // Isian pendek: 1 baris = 1 alternatif jawaban yang diterima
        $alternatif_raw = $_POST['jawaban_alternatif'] ?? '';
        $alternatif_list = array_values(array_filter(array_map('trim', explode("\n", $alternatif_raw))));

        if ($kategori && $pertanyaan && $level && !empty($alternatif_list)) {
            try {
                simpanSoalIsian($pdo, [
                    'kategori'   => $kategori,
                    'level'      => $level,
                    'materi'     => $materi,
                    'pertanyaan' => $pertanyaan,
                    'alternatif' => $alternatif_list,
                ]);
                $pesan = '✅ Soal isian berhasil ditambahkan dengan ' . count($alternatif_list) . ' alternatif jawaban!';
                $pesan_type = 'success';
            } catch (Exception $e) {
                $pesan = '❌ Gagal menyimpan soal isian.';
                $pesan_type = 'danger';
            }
        } else {
            $pesan = '⚠️ Pertanyaan dan minimal 1 alternatif jawaban wajib diisi!';
            $pesan_type = 'danger';
        }
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
        .card { border-radius: 15px; height: 100%; display: flex; flex-direction: column; }
        .card-body { flex: 1; display: flex; flex-direction: column; }
        #quizRawText { 
            flex-grow: 1; 
            min-height: 300px; 
            resize: none; 
            width: 100%;
            padding: 10px;
            text-align: left;
            white-space: pre-wrap;
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
    <?php include 'includes/admin_header.php'; ?>
    
    <div class="container mt-4">
        <div class="mb-3">
            <a href="pintu-rahasia-sija" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </div>
        <?php if ($pesan): ?>
            <div class="alert alert-<?= $pesan_type ?>"><?= $pesan ?></div>
        <?php endif; ?>

        <!-- METADATA PANEL ATAS -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-dark text-white">Metadata Soal</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Jenis Soal</label>
                        <select class="form-select" id="sharedJenisSoal" onchange="syncSharedMetadata(); toggleJenisSoalUI()">
                            <option value="pilgan">Pilihan Ganda</option>
                            <option value="isian">Isian Pendek</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Kategori</label>
                        <select class="form-select" id="sharedKategori" onchange="syncSharedMetadata()">
                            <option value="Network">Network</option>
                            <option value="IoT">IoT</option>
                            <option value="Cloud Computing">Cloud</option>
                            <option value="DevOps">DevOps</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Level</label>
                        <select class="form-select" id="sharedLevel" onchange="syncSharedMetadata()">
                            <option value="pemula">Pemula</option>
                            <option value="menengah">Menengah</option>
                            <option value="mahir">Mahir</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Materi</label>
                        <select class="form-select" id="sharedMateri" onchange="syncSharedMetadata(); toggleMateriBaru(this, 'sharedMateriBaru')">
                            <option value="">-- Belum Ditandai --</option>
                            <?php foreach ($materi_list as $m): ?>
                                <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                            <?php endforeach; ?>
                            <option value="__baru__">+ Tambah materi baru...</option>
                        </select>
                        <input type="text" id="sharedMateriBaru" class="form-control mt-2"
                               placeholder="Ketik nama materi baru" style="display:none;" oninput="syncSharedMetadata()">
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- TAMBAH SOAL MANUAL -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-primary text-white">Tambah Soal Manual</div>
                    <div class="card-body">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="jenis_soal" id="manualJenisSoal" value="pilgan">
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
                                       placeholder="Ketik nama materi baru" style="display:none;" oninput="syncSharedMetadata()">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Pertanyaan</label>
                                <textarea class="form-control" name="pertanyaan" rows="2" required></textarea>
                            </div>

                            <!-- Bagian khusus Pilihan Ganda -->
                            <div id="bagianPilgan">
                                <div class="row g-2">
                                    <?php foreach(['a','b','c','d'] as $h): ?>
                                        <div class="col-6"><input type="text" class="form-control" name="pilihan_<?= $h ?>" placeholder="Pilihan <?= strtoupper($h) ?>"></div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-2">
                                    <label class="form-label">Jawaban Benar</label>
                                    <select class="form-select" name="jawaban">
                                        <option value="a">A</option><option value="b">B</option>
                                        <option value="c">C</option><option value="d">D</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Bagian khusus Isian Pendek -->
                            <div id="bagianIsian" style="display:none;">
                                <label class="form-label">Alternatif Jawaban Benar</label>
                                <textarea class="form-control" name="jawaban_alternatif" rows="3"
                                          placeholder="Satu alternatif per baris, misal:&#10;IP address&#10;alamat IP&#10;Internet Protocol"></textarea>
                                <div class="form-text">
                                    Tulis semua variasi istilah yang dianggap benar, 1 baris = 1 alternatif.
                                    Sistem otomatis abaikan besar-kecil huruf, spasi berlebih, dan tanda baca saat mencocokkan.
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mt-3">Simpan Soal</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- AI QUIZ IMPORT (TEXT / JSON) -->
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
Answer: B" style="min-height: 250px; resize: vertical;"></textarea>           
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

    <script src="js/quiz_parser.js"></script>
    <script>
        function toggleJenisSoalUI() {
            const jenis = document.getElementById('sharedJenisSoal')?.value || 'pilgan';
            const bagianPilgan = document.getElementById('bagianPilgan');
            const bagianIsian = document.getElementById('bagianIsian');
            const pilihanInputs = document.querySelectorAll('#bagianPilgan input, #bagianPilgan select');

            if (jenis === 'isian') {
                bagianPilgan.style.display = 'none';
                bagianIsian.style.display = 'block';
                pilihanInputs.forEach(el => el.required = false);
            } else {
                bagianPilgan.style.display = 'block';
                bagianIsian.style.display = 'none';
                pilihanInputs.forEach(el => { if (el.tagName === 'INPUT') el.required = true; });
            }
        }

        function syncSharedMetadata() {
            const sharedJenisSoal = document.getElementById('sharedJenisSoal')?.value || 'pilgan';
            const sharedKategori = document.getElementById('sharedKategori')?.value || '';
            const sharedLevel = document.getElementById('sharedLevel')?.value || '';
            const sharedMateriSelect = document.getElementById('sharedMateri');
            const sharedMateri = sharedMateriSelect?.value || '';
            const sharedMateriBaru = document.getElementById('sharedMateriBaru')?.value.trim() || '';

            // 1. Sinkronisasi ke Form Manual (Sebelah Kiri)
            const manualJenisSoal = document.getElementById('manualJenisSoal');
            const manualKategori = document.getElementById('manualKategori');
            const manualLevel = document.getElementById('manualLevel');
            const manualMateriPilih = document.getElementById('materi_pilih_manual');
            const manualMateriBaru = document.getElementById('materi_baru_manual');

            if (manualJenisSoal) manualJenisSoal.value = sharedJenisSoal;
            if (manualKategori) manualKategori.value = sharedKategori;
            if (manualLevel) manualLevel.value = sharedLevel;
            
            if (manualMateriPilih) {
                manualMateriPilih.value = sharedMateri;
                toggleMateriBaru(manualMateriPilih, 'materi_baru_manual');
            }
            if (manualMateriBaru) {
                manualMateriBaru.value = sharedMateriBaru;
            }

            // 2. Sinkronisasi ke Form AI Import (Sebelah Kanan)
            const finalSharedMateri = sharedMateri === '__baru__' ? sharedMateriBaru : sharedMateri;
            
            const inputKategori = document.getElementById('inputKategori');
            const inputLevel = document.getElementById('inputLevel');
            const inputMateri = document.getElementById('inputMateri');

            if (inputKategori) inputKategori.value = sharedKategori;
            if (inputLevel) inputLevel.value = sharedLevel;
            if (inputMateri) inputMateri.value = finalSharedMateri;
        }

        function toggleMateriBaru(select, inputId) {
            const inputBaru = document.getElementById(inputId);
            if (!inputBaru) return;

            if (select.value === '__baru__') {
                inputBaru.style.display = 'block';
            } else {
                inputBaru.style.display = 'none';
                inputBaru.value = '';
            }
        }

        function generatePreviewFromText() {
            // Pastikan nilai terbaru tersinkronisasi sebelum parsing
            syncSharedMetadata();

            const rawText = document.getElementById('quizRawText').value.trim();
            const previewContainer = document.getElementById('previewContainer');
            
            if (!rawText) {
                alert("Silakan paste soal terlebih dahulu!");
                return;
            }

            // Parsing teks menjadi array JSON (fungsi parseQuizText dari js/quiz_parser.js)
            const questionsArray = parseQuizText(rawText);

            // Render Preview HTML
            previewContainer.innerHTML = "";
            questionsArray.forEach((q, idx) => {
                let opts = q.options.map(o => `<li>${o} ${o === q.correct_answer ? '✓' : ''}</li>`).join('');
                previewContainer.innerHTML += `<div class="mb-3 p-2 border"><strong>Q${idx + 1}: ${q.question_text}</strong><ul>${opts}</ul></div>`;
            });

            // Set ke hidden input
            document.getElementById('finalJsonData').value = JSON.stringify({ questions: questionsArray });

            // Sembunyikan editor, tampilkan preview
            document.getElementById('editSection').style.display = 'none';
            document.getElementById('previewSection').style.display = 'block';
        }

        function backToEdit() {
            document.getElementById('editSection').style.display = 'block';
            document.getElementById('previewSection').style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', function () {
            syncSharedMetadata();
            toggleJenisSoalUI();
        });
    </script>
    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>