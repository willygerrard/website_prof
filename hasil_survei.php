<?php
/*
  Rekap Hasil Survei LMS — khusus guru/admin.
  Taruh di folder yang sama dengan survei.php dan koneksi.php.
*/
session_start();
require_once 'koneksi.php'; // SESUAIKAN: harus menyediakan koneksi PDO bernama $pdo

// === AKSES: SESUAIKAN dengan session LMS-mu ===
$ROLE_BOLEH = ['guru', 'admin'];
if (!in_array($_SESSION['role'] ?? '', $ROLE_BOLEH, true)) {
    http_response_code(403);
    exit('Akses ditolak.');
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Filter tingkat (opsional): ?tingkat=X
$tingkatList = $pdo->query("SELECT DISTINCT tingkat FROM survei_lms WHERE tingkat IS NOT NULL ORDER BY tingkat")->fetchAll(PDO::FETCH_COLUMN);
$tingkat = $_GET['tingkat'] ?? '';
if ($tingkat !== '' && in_array($tingkat, $tingkatList, true)) {
    $st = $pdo->prepare('SELECT id, tingkat, jawaban, created_at FROM survei_lms WHERE tingkat = ? ORDER BY id DESC');
    $st->execute([$tingkat]);
} else {
    $tingkat = '';
    $st = $pdo->query('SELECT id, tingkat, jawaban, created_at FROM survei_lms ORDER BY id DESC');
}
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as &$r) { $r['d'] = json_decode($r['jawaban'], true) ?: []; }
unset($r);

$single = [
    'perangkat' => 'Perangkat yang paling sering dipakai',
    'lupa_pw'   => 'Seberapa sering lupa password',
    'login'     => 'Cara login yang dipilih',
    'timer'     => 'Timer 100 detik per kartu',
    'remedial'  => 'Remedial 4 kali (KKM 75)',
    'notif'     => 'Kanal pemberitahuan tercepat dibaca',
];
$multi = [
    'materi'   => 'Materi yang paling membantu',
    'kendala'  => 'Kendala saat kuis',
    'motivasi' => 'Hal yang memotivasi',
];
$nilai = [
    'nilai_kecepatan' => 'Kecepatan',
    'nilai_navigasi'  => 'Kemudahan mencari menu',
    'nilai_hp'        => 'Tampilan di HP',
];
$terbuka = [
    'frustrasi' => 'Hal yang paling membuat kesal',
    'fitur'     => 'Fitur yang diinginkan',
];

// Ekspor CSV
if (isset($_GET['csv'])) {
    $safe = fn($v) => (is_string($v) && preg_match('/^[=+\-@\t\r]/', $v)) ? "'" . $v : $v;
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="survei_lms_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $cols = array_merge(array_keys($single), array_keys($multi), array_keys($nilai), array_keys($terbuka));
    fputcsv($out, array_merge(['waktu', 'tingkat'], $cols));
    foreach (array_reverse($rows) as $r) {
        $line = [$r['created_at'], $r['tingkat']];
        foreach ($cols as $c) {
            $v = $r['d'][$c] ?? '';
            $line[] = $safe(is_array($v) ? implode('; ', $v) : $v);
        }
        fputcsv($out, $line);
    }
    exit;
}

$n = count($rows);

function hitung($rows, $key, $isMulti) {
    $c = [];
    foreach ($rows as $r) {
        $v = $r['d'][$key] ?? ($isMulti ? [] : '');
        foreach ((array)$v as $x) { if ($x !== '') $c[$x] = ($c[$x] ?? 0) + 1; }
    }
    arsort($c);
    return $c;
}

function bars($counts, $n) {
    foreach ($counts as $label => $jml) {
        $p = $n ? round($jml / $n * 100) : 0;
        echo '<div class="bar"><div class="bl">' . e($label) . '</div>'
           . '<div class="bt"><div class="bf" style="width:' . $p . '%"></div></div>'
           . '<div class="bn">' . $jml . ' <small>(' . $p . '%)</small></div></div>';
    }
    if (!$counts) echo '<p class="muted">Belum ada data.</p>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Hasil Survei LMS</title>
<style>
:root{--ink:#14213d;--muted:#5b6678;--paper:#f6f8fa;--card:#fff;--line:#d3dbe5;--accent:#0b7a75;--soft:#e0f2f0}
@media (prefers-color-scheme:dark){:root{--ink:#e8edf5;--muted:#9aa6b8;--paper:#0f1722;--card:#17212f;--line:#2c3a4e;--accent:#4fc3bb;--soft:#16363a}}
*{box-sizing:border-box}
body{margin:0;background:var(--paper);color:var(--ink);font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
main{max-width:920px;margin:0 auto;padding:24px 16px 64px}
h1{font-size:1.5rem;margin:0 0 4px}
h2{font-size:1.05rem;margin:0 0 12px}
.muted{color:var(--muted)}
.top{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:20px}
.tools{display:flex;gap:8px;align-items:center}
select,.btn{font:inherit;padding:8px 12px;border:1px solid var(--line);border-radius:8px;background:var(--card);color:var(--ink);text-decoration:none}
.btn{background:var(--accent);border-color:var(--accent);color:#fff;font-weight:600}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:14px}
@media (max-width:480px){.grid{grid-template-columns:1fr}}
.card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:16px 18px}
.bar{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr) auto;gap:10px;align-items:center;padding:4px 0;font-size:.92rem}
.bt{height:12px;background:var(--soft);border-radius:6px;overflow:hidden}
.bf{height:100%;background:var(--accent)}
.bn{white-space:nowrap;text-align:right}
.bn small{color:var(--muted)}
.avg{display:flex;justify-content:space-between;align-items:baseline;padding:6px 0;border-top:1px solid var(--line)}
.avg:first-of-type{border-top:0}
.avg b{font-size:1.4rem}
.full{grid-column:1/-1}
.cmt{max-height:380px;overflow:auto}
.cmt p{margin:0;padding:8px 0;border-top:1px solid var(--line);overflow-wrap:anywhere}
.cmt p:first-child{border-top:0}
.cmt time{display:block;color:var(--muted);font-size:.78rem}
</style>
</head>
<body>
<main>
  <div class="top">
    <div>
      <h1>Hasil Survei LMS</h1>
      <div class="muted"><?= $n ?> responden<?= $tingkat !== '' ? ' (tingkat ' . e($tingkat) . ')' : '' ?></div>
    </div>
    <form class="tools" method="get">
      <select name="tingkat" onchange="this.form.submit()" aria-label="Filter tingkat">
        <option value="">Semua tingkat</option>
        <?php foreach ($tingkatList as $t): ?>
          <option value="<?= e($t) ?>"<?= $t === $tingkat ? ' selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
      <a class="btn" href="?csv=1<?= $tingkat !== '' ? '&tingkat=' . urlencode($tingkat) : '' ?>">Unduh CSV</a>
    </form>
  </div>

<?php if ($n === 0): ?>
  <div class="card"><p class="muted">Belum ada jawaban yang masuk.</p></div>
<?php else: ?>
  <div class="grid">
    <div class="card full">
      <h2>Rata-rata nilai LMS (skala 1-5)</h2>
      <?php foreach ($nilai as $k => $lbl):
          $vals = array_filter(array_map(fn($r) => $r['d'][$k] ?? null, $rows), fn($v) => $v !== null);
          $avg = $vals ? round(array_sum($vals) / count($vals), 2) : '-'; ?>
        <div class="avg"><span><?= e($lbl) ?></span><b><?= e($avg) ?></b></div>
      <?php endforeach; ?>
    </div>

    <?php foreach ($single as $k => $lbl): ?>
      <div class="card"><h2><?= e($lbl) ?></h2><?php bars(hitung($rows, $k, false), $n); ?></div>
    <?php endforeach; ?>

    <?php foreach ($multi as $k => $lbl): ?>
      <div class="card"><h2><?= e($lbl) ?></h2><?php bars(hitung($rows, $k, true), $n); ?></div>
    <?php endforeach; ?>

    <?php foreach ($terbuka as $k => $lbl): ?>
      <div class="card full"><h2><?= e($lbl) ?></h2>
        <div class="cmt">
        <?php $ada = false; foreach ($rows as $r):
            $t = trim($r['d'][$k] ?? '');
            if ($t === '') continue; $ada = true; ?>
          <p><time><?= e($r['created_at']) ?></time><?= nl2br(e($t)) ?></p>
        <?php endforeach; if (!$ada) echo '<p class="muted">Belum ada jawaban.</p>'; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</main>
</body>
</html>