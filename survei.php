<?php
/*
  Survei Pengguna LMS — Pusat Pembelajaran SIJA
  Satu file: form + penyimpanan. Jawaban anonim (hanya menyimpan tingkat jika ada di session).

  Tabel (jalankan sekali):
  CREATE TABLE survei_lms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tingkat VARCHAR(10) NULL,
    jawaban JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  );
*/
session_start();
require_once 'koneksi.php'; // SESUAIKAN: harus menyediakan koneksi PDO bernama $pdo

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

function e($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function opts($name, $list, $multi = false) {
    $type = $multi ? 'checkbox' : 'radio';
    $n = $multi ? $name . '[]' : $name;
    foreach ($list as $o) {
        echo '<label class="opt"><input type="' . $type . '" name="' . e($n) . '" value="' . e($o) . '"'
           . (!$multi ? ' required' : '') . '><span>' . e($o) . '</span></label>';
    }
}

function rating($name, $label) {
    echo '<div class="rate"><span class="rl">' . e($label) . '</span><div class="rs">';
    for ($i = 1; $i <= 5; $i++) {
        echo '<label><input type="radio" name="' . e($name) . '" value="' . $i . '" required><span>' . $i . '</span></label>';
    }
    echo '</div></div>';
}

$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Token tidak valid. Muat ulang halaman lalu coba lagi.');
    }
    $d = [];
    foreach (['perangkat', 'lupa_pw', 'login', 'timer', 'remedial', 'notif'] as $k) {
        $d[$k] = mb_substr(trim($_POST[$k] ?? ''), 0, 100);
    }
    foreach (['materi', 'kendala', 'motivasi'] as $k) {
        $d[$k] = array_map(fn($v) => mb_substr((string)$v, 0, 100), array_slice((array)($_POST[$k] ?? []), 0, 10));
    }
    foreach (['nilai_kecepatan', 'nilai_navigasi', 'nilai_hp'] as $k) {
        $v = (int)($_POST[$k] ?? 0);
        $d[$k] = ($v >= 1 && $v <= 5) ? $v : null;
    }
    foreach (['frustrasi', 'fitur'] as $k) {
        $d[$k] = mb_substr(trim($_POST[$k] ?? ''), 0, 500);
    }
    $st = $pdo->prepare('INSERT INTO survei_lms (tingkat, jawaban) VALUES (?, ?)');
    $st->execute([$_SESSION['tingkat'] ?? null, json_encode($d, JSON_UNESCAPED_UNICODE)]);
    $done = true;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Survei LMS — Pusat Pembelajaran SIJA</title>
<style>
:root{--ink:#14213d;--muted:#5b6678;--paper:#f6f8fa;--card:#fff;--line:#d3dbe5;--accent:#0b7a75;--accent-soft:#e0f2f0}
@media (prefers-color-scheme:dark){:root{--ink:#e8edf5;--muted:#9aa6b8;--paper:#0f1722;--card:#17212f;--line:#2c3a4e;--accent:#4fc3bb;--accent-soft:#16363a}}
*{box-sizing:border-box}
body{margin:0;background:var(--paper);color:var(--ink);font:16px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
main{max-width:640px;margin:0 auto;padding:28px 16px 64px}
h1{font-size:1.6rem;line-height:1.25;margin:0 0 6px}
.lead{color:var(--muted);margin:0 0 24px}
fieldset{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:16px 18px 12px;margin:0 0 16px}
legend{font-weight:650;padding:0 6px;max-width:100%}
.hint{color:var(--muted);font-size:.875rem;margin:-4px 0 8px}
.opt{display:flex;gap:10px;align-items:flex-start;padding:7px 8px;border-radius:6px;cursor:pointer}
.opt:hover{background:var(--accent-soft)}
.opt input{margin-top:5px;accent-color:var(--accent)}
.rate{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;padding:8px 0;border-top:1px solid var(--line)}
.rate:first-of-type{border-top:0}
.rs{display:flex;gap:6px}
.rs label{cursor:pointer}
.rs input{position:absolute;opacity:0}
.rs span{display:grid;place-items:center;width:40px;height:40px;border:1px solid var(--line);border-radius:8px;font-weight:600}
.rs input:checked+span{background:var(--accent);border-color:var(--accent);color:#fff}
.rs input:focus-visible+span,.opt input:focus-visible,textarea:focus-visible,button:focus-visible{outline:3px solid var(--accent);outline-offset:2px}
.scale{display:flex;justify-content:space-between;color:var(--muted);font-size:.8rem;margin:0 0 4px}
textarea{width:100%;min-height:90px;padding:10px;border:1px solid var(--line);border-radius:8px;background:var(--paper);color:var(--ink);font:inherit;resize:vertical}
button{background:var(--accent);color:#fff;border:0;border-radius:8px;padding:12px 22px;font:inherit;font-weight:650;cursor:pointer}
button:hover{filter:brightness(1.1)}
.done{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:28px}
</style>
</head>
<body>
<main>
<?php if ($done): ?>
  <div class="done">
    <h1>Jawabanmu sudah tersimpan</h1>
    <p class="lead">Terima kasih. Masukanmu dipakai untuk menentukan fitur LMS yang dikembangkan berikutnya.</p>
    <a href="/">Kembali ke LMS</a>
  </div>
<?php else: ?>
  <h1>Bantu kami memperbaiki LMS</h1>
  <p class="lead">12 pertanyaan, sekitar 3 menit. Jawabanmu anonim dan tidak memengaruhi nilai. Jawab sejujurnya.</p>

  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

    <fieldset><legend>1. Kamu paling sering membuka LMS lewat apa?</legend>
      <?php opts('perangkat', ['HP', 'Laptop / PC', 'Sama sering keduanya']); ?>
    </fieldset>

    <fieldset><legend>2. Seberapa sering kamu lupa password LMS?</legend>
      <?php opts('lupa_pw', ['Tidak pernah', 'Kadang-kadang', 'Sering']); ?>
    </fieldset>

    <fieldset><legend>3. Cara login mana yang paling kamu pilih?</legend>
      <?php opts('login', ['Username dan password (seperti sekarang)', 'Akun Google', 'Fingerprint / face unlock', 'Tidak masalah, yang penting bisa masuk']); ?>
    </fieldset>

    <fieldset><legend>4. Materi seperti apa yang paling membantumu belajar?</legend>
      <p class="hint">Boleh pilih lebih dari satu.</p>
      <?php opts('materi', ['Video', 'PDF / dokumen', 'Teks langsung di LMS', 'Latihan praktik / lab'], true); ?>
    </fieldset>

    <fieldset><legend>5. Timer 100 detik pada tiap kartu materi menurutmu:</legend>
      <?php opts('timer', ['Membantu aku fokus', 'Terlalu lama', 'Terlalu singkat', 'Tidak terasa bedanya']); ?>
    </fieldset>

    <fieldset><legend>6. Kendala apa yang pernah kamu alami saat mengerjakan kuis?</legend>
      <p class="hint">Boleh pilih lebih dari satu.</p>
      <?php opts('kendala', ['Koneksi putus', 'Peringatan pindah tab terasa tidak adil', 'Batas waktu kurang jelas', 'Materi (Drive/YouTube) lambat dibuka', 'Tidak ada kendala'], true); ?>
    </fieldset>

    <fieldset><legend>7. Remedial sampai 4 kali dengan KKM 75, menurutmu:</legend>
      <?php opts('remedial', ['Memotivasi aku memperbaiki nilai', 'Cukup adil, biasa saja', 'Membuatku tertekan', 'Terlalu mudah diulang-ulang']); ?>
    </fieldset>

    <fieldset><legend>8. Apa yang paling memotivasimu belajar di LMS?</legend>
      <p class="hint">Boleh pilih lebih dari satu.</p>
      <?php opts('motivasi', ['Papan peringkat kelas', 'Badge / poin', 'Grafik progres belajarku', 'Tidak perlu fitur seperti itu'], true); ?>
    </fieldset>

    <fieldset><legend>9. Pemberitahuan (tugas, kuis, nilai) paling cepat kamu baca lewat:</legend>
      <?php opts('notif', ['WhatsApp', 'Email', 'Notifikasi di dalam LMS']); ?>
    </fieldset>

    <fieldset><legend>10. Beri nilai LMS saat ini</legend>
      <div class="scale"><span>1 = buruk</span><span>5 = sangat baik</span></div>
      <?php rating('nilai_kecepatan', 'Kecepatan'); rating('nilai_navigasi', 'Kemudahan mencari menu'); rating('nilai_hp', 'Tampilan di HP'); ?>
    </fieldset>

    <fieldset><legend>11. Apa satu hal yang paling membuatmu kesal di LMS?</legend>
      <textarea name="frustrasi" maxlength="500" placeholder="Tulis singkat saja"></textarea>
    </fieldset>

    <fieldset><legend>12. Fitur apa yang paling kamu ingin ada di LMS?</legend>
      <textarea name="fitur" maxlength="500" placeholder="Tulis singkat saja"></textarea>
    </fieldset>

    <button type="submit">Kirim jawaban</button>
  </form>
<?php endif; ?>
</main>
</body>
</html>