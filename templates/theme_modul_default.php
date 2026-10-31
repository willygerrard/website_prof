<?php
/**
 * templates/theme_modul_default.php — Tema minimalis bergaya slide.
 *
 * Dipakai oleh aimmWrapWithTheme() (ai_helper.php).
 * Variabel masuk: $title (string), $html_content (fragmen HTML SUDAH disanitasi).
 * Karena pembungkusan dilakukan saat tampil, modul lama otomatis ikut tema ini.
 */

$titleEsc = htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8');
$body     = (string)$html_content;

// H1 dari Markdown sudah ditampilkan di hero -> buang agar tidak dobel
$body = preg_replace('#<h1[^>]*>.*?</h1>#is', '', $body, 1);

// Pecah per <h2> menjadi "slide"; teks sebelum h2 pertama jadi pengantar
$parts  = preg_split('/(?=<h2[\s>])/i', trim($body), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$slides = '';
foreach ($parts as $part) {
    $isSection = (bool)preg_match('/^<h2[\s>]/i', $part);
    $slides .= '<section class="slide' . ($isSection ? ' numbered' : ' lead') . '">' . $part . '</section>' . "\n";
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $titleEsc ?></title>
<style>
:root{
  --bg:#f4f5f7; --card:#ffffff; --ink:#1f2933; --muted:#6b7785;
  --accent:#2563eb; --accent-soft:#e8efff; --line:#e5e8ec;
  --code-bg:#111827; --code-ink:#e5e7eb;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
  margin:0; background:var(--bg); color:var(--ink);
  font:16px/1.75 system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
  counter-reset:slide;
}
.hero{
  background:linear-gradient(135deg,#1e3a8a,#2563eb);
  color:#fff; padding:56px 20px 64px; text-align:center;
}
.hero small{display:block;letter-spacing:.14em;text-transform:uppercase;opacity:.75;font-size:.78rem;margin-bottom:12px}
.hero h1{margin:0 auto;max-width:880px;font-size:clamp(1.6rem,4vw,2.6rem);line-height:1.25;font-weight:700}
.wrap{max-width:900px;margin:-32px auto 48px;padding:0 16px}
.slide{
  background:var(--card); border:1px solid var(--line); border-radius:16px;
  padding:32px clamp(20px,4vw,48px); margin-bottom:24px;
  box-shadow:0 1px 2px rgba(16,24,40,.04),0 8px 24px rgba(16,24,40,.05);
}
.slide.numbered{counter-increment:slide}
.slide h2{
  margin:0 0 20px; padding-bottom:14px; border-bottom:2px solid var(--accent-soft);
  font-size:1.45rem; line-height:1.3; color:var(--accent);
}
.slide.numbered h2::before{
  content:counter(slide); display:inline-flex; align-items:center; justify-content:center;
  width:34px; height:34px; margin-right:12px; border-radius:50%;
  background:var(--accent); color:#fff; font-size:.95rem; vertical-align:middle;
}
.slide h3{margin:28px 0 10px;font-size:1.15rem}
.slide h4{margin:22px 0 8px;font-size:1rem;color:var(--muted)}
.slide p{margin:0 0 14px}
.slide ul,.slide ol{margin:0 0 16px;padding-left:1.4rem}
.slide li{margin-bottom:8px}
.slide li::marker{color:var(--accent);font-weight:600}
.slide a{color:var(--accent)}
.slide blockquote{margin:16px 0;padding:12px 18px;border-left:4px solid var(--accent);background:var(--accent-soft);border-radius:0 10px 10px 0;color:#334155}
.slide hr{border:0;border-top:1px solid var(--line);margin:24px 0}
.slide code{
  font:0.88em/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
  background:var(--accent-soft); color:#1e40af; padding:2px 6px; border-radius:6px;
}
.slide pre{
  margin:16px 0; padding:18px 20px; background:var(--code-bg); color:var(--code-ink);
  border-radius:12px; overflow-x:auto; -webkit-overflow-scrolling:touch;
}
.slide pre code{background:none;color:inherit;padding:0;font-size:.88rem;white-space:pre}
.slide table{width:100%;border-collapse:collapse;margin:16px 0;font-size:.95rem;display:block;overflow-x:auto}
.slide th,.slide td{border:1px solid var(--line);padding:9px 12px;text-align:left}
.slide th{background:var(--accent-soft);font-weight:600}
.slide tr:nth-child(even) td{background:#fafbfc}
.slide img{max-width:100%;height:auto;border-radius:10px}
.slide.lead{background:transparent;border:0;box-shadow:none;padding:8px 8px 0;color:var(--muted)}
footer{text-align:center;color:var(--muted);font-size:.8rem;padding:0 16px 36px}
@media print{
  body{background:#fff}.hero{background:#fff;color:#000;padding:16px}
  .wrap{margin:0}.slide{box-shadow:none;break-inside:avoid;border-color:#ccc}
}
</style>
</head>
<body>
<header class="hero">
  <small>Pusat Pembelajaran SIJA</small>
  <h1><?= $titleEsc ?></h1>
</header>
<main class="wrap">
<?= $slides ?>
</main>
<footer>SMKN 11 Malang · Departemen SIJA</footer>
</body>
</html>
