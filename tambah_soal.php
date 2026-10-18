<?php
require_once 'session.php';
require_once 'csrf_helper.php';
require 'koneksi.php';
include __DIR__ . '/includes/soal_writer.php';

checkLogin();
checkRole(['admin']);

$pesan = '';
$pesan_type = '';

$materi_list = $pdo->query("SELECT DISTINCT materi FROM kuis_soal WHERE materi IS NOT NULL AND materi <> '' ORDER BY materi")->fetchAll(PDO::FETCH_COLUMN);
$modul_list = $pdo->query("SELECT id, title, category FROM modules ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);

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
                'kategori'   => $kategori, 'level' => $level, 'materi' => $materi,
                'pertanyaan' => $pertanyaan, 'pilihan_a' => $pilihan_a, 'pilihan_b' => $pilihan_b,
                'pilihan_c'  => $pilihan_c, 'pilihan_d' => $pilihan_d, 'jawaban'   => $jawaban,
            ]);
            $pesan = '✅ Soal pilihan ganda berhasil ditambahkan!';
            $pesan_type = 'success';
        } else {
            $pesan = '⚠️ Semua field wajib diisi!';
            $pesan_type = 'danger';
        }
    } else {
        $alternatif_raw = $_POST['jawaban_alternatif'] ?? '';
        $alternatif_list = array_values(array_filter(array_map('trim', explode("\n", $alternatif_raw))));

        if ($kategori && $pertanyaan && $level && !empty($alternatif_list)) {
            try {
                simpanSoalIsian($pdo, [
                    'kategori' => $kategori, 'level' => $level, 'materi' => $materi,
                    'pertanyaan' => $pertanyaan, 'alternatif' => $alternatif_list,
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
        #quizRawText { flex-grow: 1; min-height: 300px; resize: none; width: 100%; padding: 10px; text-align: left; white-space: pre-wrap; display: block; }
        #previewContainer { flex-grow: 1; min-height: 300px; overflow-y: auto; }
        #editSection { display: flex; flex-direction: column; width: 100%; text-align: left; }
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
                            <option value="System Administration">System Administration</option>
                            <option value="Cloud Computing">Cloud Computing</option>
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
                        <input type="text" id="sharedMateriBaru" class="form-control mt-2" placeholder="Ketik nama materi baru" style="display:none;" oninput="syncSharedMetadata()">
                    </div>
                </div>
            </div>
        </div>

        <!-- AI QUIZ MAKER -->
        <div class="card shadow-sm border-0 mb-4" style="height:auto;">
            <div class="card-header bg-info text-dark"><i class="bi bi-stars"></i> AI Quiz Maker</div>
            <div class="card-body">
                <form id="aiGenForm" onsubmit="return false;">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Permintaan</label>
                            <textarea class="form-control" name="prompt" id="aiqPrompt" rows="2"
                                placeholder="Contoh: buatkan 20 soal pilihan ganda dari modul subnetting VLSM"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Modul rujukan (opsional)</label>
                            <select class="form-select" name="modul_id" id="aiqModul" onchange="aiqPilihModul(this)">
                                <option value="">-- Tanpa modul --</option>
                                <?php foreach ($modul_list as $mm): ?>
                                    <option value="<?= (int)$mm['id'] ?>" data-category="<?= htmlspecialchars($mm['category'] ?? '') ?>">
                                        <?= htmlspecialchars($mm['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Kategori, level, dan materi mengikuti panel Metadata di atas.</div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Isi materi modul <span class="text-muted">(Opsional jika memilih modul)</span></label>
                        <textarea class="form-control" name="sumber" id="aiqSumber" rows="4" maxlength="12000"
                            placeholder="Kosongkan saja jika sudah memilih modul di atas. AI akan meracik soal dari Judul & Deskripsi modul. Jika ingin materi spesifik, tempel teksnya di sini."></textarea>
                        <div class="form-text">AI akan mengekstrak teks dari modul yang dipilih. Jika modul sangat panjang, AI hanya akan fokus pada bagian yang relevan dengan prompt.</div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
                        <button type="button" id="aiqBtn" class="btn btn-info fw-bold" onclick="aiqGenerate()">
                            <i class="bi bi-stars"></i> Generate Soal
                        </button>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="verify" value="1" id="aiqVerify" checked>
                            <label class="form-check-label small" for="aiqVerify">Verifikasi otomatis (AI mengerjakan ulang tiap soal, lebih lambat)</label>
                        </div>
                        <span id="aiqStatus" class="small text-muted"></span>
                    </div>
                </form>

                <div id="aiqPreviewWrap" style="display:none;" class="mt-4">
                    <div id="aiqMeta" class="alert alert-secondary py-2 small"></div>
                    <div id="aiqList"></div>
                    <form action="save_quiz.php" method="POST" onsubmit="return aiqSiapSimpan();" class="d-flex gap-2 mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="quiz_json" id="aiqJson">
                        <input type="hidden" name="kategori" id="aiqKategori">
                        <input type="hidden" name="level" id="aiqLevel">
                        <input type="hidden" name="materi" id="aiqMateri">
                        <input type="hidden" name="jenis_soal" id="aiqJenisSoal"> <!-- Input hidden jenis soal -->
                        <button type="button" class="btn btn-outline-secondary" onclick="aiqGenerate()">Generate Ulang</button>
                        <button type="submit" class="btn btn-success flex-grow-1">Simpan Soal yang Dipilih ke Database</button>
                    </form>
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
                                    <option value="System Administration">System Administration</option>
                                    <option value="Cloud Computing">Cloud Computing</option>
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

                            <div id="bagianIsian" style="display:none;">
                                <label class="form-label">Alternatif Jawaban Benar</label>
                                <textarea class="form-control" name="jawaban_alternatif" rows="3"
                                          placeholder="Satu alternatif per baris, misal:&#10;IP address&#10;alamat IP&#10;Internet Protocol"></textarea>
                                <div class="form-text">
                                    Tulis semua variasi istilah yang dianggap benar, 1 baris = 1 alternatif.
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
            syncSharedMetadata();
            const rawText = document.getElementById('quizRawText').value.trim();
            const previewContainer = document.getElementById('previewContainer');
            if (!rawText) { alert("Silakan paste soal terlebih dahulu!"); return; }

            const questionsArray = parseQuizText(rawText);
            previewContainer.innerHTML = "";
            questionsArray.forEach((q, idx) => {
                let opts = q.options.map(o => `<li>${o} ${o === q.correct_answer ? '✓' : ''}</li>`).join('');
                previewContainer.innerHTML += `<div class="mb-3 p-2 border"><strong>Q${idx + 1}: ${q.question_text}</strong><ul>${opts}</ul></div>`;
            });

            document.getElementById('finalJsonData').value = JSON.stringify({ questions: questionsArray });
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
    <script>
        // ===== AI QUIZ MAKER =====
        let aiqSoal = [];

        function aiqEsc(t) {
            return String(t ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }

        function aiqPilihModul(sel) {
            const cat = sel.selectedOptions[0]?.dataset.category || '';
            const kat = document.getElementById('sharedKategori');
            if (cat && [...kat.options].some(o => o.value === cat)) { kat.value = cat; syncSharedMetadata(); }
        }

        async function aiqGenerate() {
            const prompt = document.getElementById('aiqPrompt').value.trim();
            const modul  = document.getElementById('aiqModul').value;
            const sumber = document.getElementById('aiqSumber').value.trim();
            
            if (modul === "" && sumber.length < 100) { 
                alert('Pilih modul dari dropdown, ATAU tempel isi materi modul dulu (minimal 100 karakter).'); 
                return; 
            }

            syncSharedMetadata();
            const fd = new FormData(document.getElementById('aiGenForm')); 
            fd.set('kategori', document.getElementById('sharedKategori').value);
            fd.set('level', document.getElementById('sharedLevel').value);
            fd.set('jenis_soal', document.getElementById('sharedJenisSoal').value); // Kirim jenis soal ke backend
            const mSel = document.getElementById('sharedMateri').value;
            fd.set('materi', mSel === '__baru__' ? document.getElementById('sharedMateriBaru').value.trim() : mSel);
            fd.set('verify', document.getElementById('aiqVerify').checked ? '1' : '0');

            const btn = document.getElementById('aiqBtn'), st = document.getElementById('aiqStatus');
            btn.disabled = true;
            st.textContent = 'Mengirim permintaan ke AI... bisa sampai 1 menit.';

            try {
                const ctrl = new AbortController();
                const tm   = setTimeout(() => ctrl.abort(), 110000);
                const res  = await fetch('ai_quiz_generate.php', { method: 'POST', body: fd, signal: ctrl.signal });
                clearTimeout(tm);
                const text = await res.text();
                let data;
                try { data = JSON.parse(text); } catch (e) { throw new Error('Server mengirim respons tidak valid (HTTP ' + res.status + ')'); }
                if (data.status !== 'success') throw new Error(data.message || 'Gagal membuat soal');

                aiqSoal = data.questions;
                aiqRender(data.meta);
                st.textContent = '';
            } catch (err) {
                const msg = err.name === 'AbortError' ? 'Permintaan terlalu lama (lebih dari 110 detik).' : err.message;
                st.innerHTML = '<span class="text-danger fw-bold">Gagal: ' + aiqEsc(msg) + '</span>';
                alert(msg);
            } finally {
                btn.disabled = false;
            }
        }

        function aiqRender(meta) {
            const wrap = document.getElementById('aiqPreviewWrap');
            const list = document.getElementById('aiqList');
            wrap.style.display = 'block';

            const perlu = aiqSoal.filter(q => q.cek === true).length;
            let info = `Diterima ${aiqSoal.length} dari ${meta.diminta} soal diminta`;
            if (meta.dibuang > 0) info += ` (${meta.dibuang} soal cacat dibuang)`;
            if (meta.tanpa_bukti > 0) info += ` (${meta.tanpa_bukti} soal dibuang karena tidak ada dasarnya di materi)`;
            info += meta.diverifikasi ? `. Terverifikasi otomatis, ${perlu} soal perlu Anda cek.` : '. Belum diverifikasi otomatis.';
            document.getElementById('aiqMeta').textContent = info;

            list.innerHTML = aiqSoal.map((q, i) => {
                let isIsian = !q.options || q.options.length === 0;
                let optsHtml = '';
                
                if (isIsian) {
                    optsHtml = `<div class="text-success fw-bold mt-2">Kunci: ${aiqEsc(q.correct_answer)}</div>
                                <div class="small text-muted">Alternatif: ${q.alternatif ? q.alternatif.join(', ') : '-'}</div>`;
                } else {
                    optsHtml = `<ul class="list-unstyled mt-2 mb-1 small">` + q.options.map((o, k) => {
                        const benar = o === q.correct_answer;
                        return `<li class="${benar ? 'fw-bold text-success' : ''}">${String.fromCharCode(65 + k)}. ${aiqEsc(o)} ${benar ? '✓' : ''}</li>`;
                    }).join('') + `</ul>`;
                }

                let badge = '';
                if (q.cek === true)  badge += '<span class="badge bg-danger me-1">AI menjawab beda - periksa kunci</span>';
                if (q.cek === false) badge += '<span class="badge bg-success me-1">Lolos verifikasi</span>';
                if (q.duplikat)      badge += '<span class="badge bg-warning text-dark me-1">Mirip soal yang sudah ada</span>';
                
                return `<div class="border rounded p-3 mb-2 ${q.cek === true ? 'border-danger' : ''}" data-i="${i}">
                    <div class="d-flex justify-content-between gap-2">
                        <strong>${i + 1}. ${aiqEsc(q.question_text)}</strong>
                        <button type="button" class="btn btn-sm btn-outline-danger flex-shrink-0" onclick="aiqHapus(${i})" title="Buang soal ini"><i class="bi bi-trash"></i></button>
                    </div>
                    ${optsHtml}
                    ${q.penjelasan ? `<div class="small text-muted mt-1">Penjelasan: ${aiqEsc(q.penjelasan)}</div>` : ''}
                    ${q.bukti ? `<div class="small text-muted fst-italic">Dasar di materi: "${aiqEsc(q.bukti)}"</div>` : ''}
                    <div class="mt-1">${badge}</div>
                </div>`;
            }).join('');
        }

        function aiqHapus(i) {
            aiqSoal.splice(i, 1);
            aiqRender({ diminta: aiqSoal.length, dibuang: 0, tanpa_bukti: 0, diverifikasi: aiqSoal.some(q => q.cek !== null) });
        }

        function aiqSiapSimpan() {
            if (aiqSoal.length === 0) { alert('Tidak ada soal untuk disimpan.'); return false; }
            const belum = aiqSoal.filter(q => q.cek === true).length;
            if (belum > 0 && !confirm(belum + ' soal ditandai "AI menjawab beda". Tetap simpan semuanya?')) return false;

            syncSharedMetadata();
            const mSel = document.getElementById('sharedMateri').value;
            
            // Set hidden inputs untuk save_quiz.php
            document.getElementById('aiqKategori').value = document.getElementById('sharedKategori').value;
            document.getElementById('aiqLevel').value    = document.getElementById('sharedLevel').value;
            document.getElementById('aiqMateri').value   = mSel === '__baru__' ? document.getElementById('sharedMateriBaru').value.trim() : mSel;
            document.getElementById('aiqJenisSoal').value = document.getElementById('sharedJenisSoal').value; // Kirim jenis soal
            
            // Rakit payload JSON
            document.getElementById('aiqJson').value = JSON.stringify({
                questions: aiqSoal.map(q => {
                    if (!q.options || q.options.length === 0) {
                        // Format Isian
                        return {
                            question_text: q.question_text,
                            correct_answer: q.correct_answer,
                            alternatif: q.alternatif,
                            materi: ''
                        };
                    } else {
                        // Format Pilgan
                        return {
                            question_text: q.question_text, 
                            options: q.options,
                            correct_answer: q.correct_answer, 
                            materi: ''
                        };
                    }
                })
            });
            return true;
        }
    </script>
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>