<?php
/**
 * tests/markdown_test.php — CLI test untuk pipeline Markdown modul AI.
 *
 * Alur yang diuji sama dengan produksi:
 *   aimmMarkdownToHtml()  -> Parsedown (safe mode + markup escaped)
 *   aimmSanitizeHtml()    -> allowlist DOMDocument
 *
 * Jalankan: php tests/markdown_test.php
 * Exit code 0 = semua LULUS, 1 = ada yang GAGAL.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Skrip ini hanya untuk CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../ai_helper.php';

/**
 * @param string $md      Input Markdown
 * @param array  $checks  [label => callable(string $html): bool]
 * @return array{0:int,1:int} [lulus, gagal]
 */
function runFixture(string $name, string $md, array $checks): array {
    $html = aimmSanitizeHtml(aimmMarkdownToHtml($md));

    echo "== {$name} ==\n";
    echo "HTML: " . str_replace("\n", ' ', $html) . "\n";

    $pass = 0;
    $fail = 0;
    foreach ($checks as $label => $fn) {
        $ok = (bool)$fn($html);
        echo ($ok ? '  [LULUS] ' : '  [GAGAL] ') . $label . "\n";
        $ok ? $pass++ : $fail++;
    }
    echo "\n";
    return [$pass, $fail];
}

$totalPass = 0;
$totalFail = 0;

// 1) Miring
$r = runFixture('miring', 'Ini teks *miring* ya.', [
    'ada <em>miring</em>'            => fn($h) => strpos($h, '<em>miring</em>') !== false,
    'tidak ada tanda * mentah'       => fn($h) => strpos($h, '*') === false,
]);
$totalPass += $r[0]; $totalFail += $r[1];

// 2) Tebal
$r = runFixture('tebal', 'Ini teks **tebal** ya.', [
    'ada <strong>tebal</strong>'     => fn($h) => strpos($h, '<strong>tebal</strong>') !== false,
    'tidak ada ** mentah'            => fn($h) => strpos($h, '**') === false,
]);
$totalPass += $r[0]; $totalFail += $r[1];

// 3) Daftar bernomor
$r = runFixture('daftar bernomor', "1. Langkah satu\n2. Langkah dua\n3. Langkah tiga", [
    'ada <ol>'                       => fn($h) => strpos($h, '<ol>') !== false,
    'ada 3 <li>'                     => fn($h) => preg_match_all('/<li>/i', $h) === 3,
    'isi langkah dua'                => fn($h) => strpos($h, 'Langkah dua') !== false,
]);
$totalPass += $r[0]; $totalFail += $r[1];

// 4) Daftar bullet
$r = runFixture('daftar bullet', "- Poin satu\n- Poin dua", [
    'ada <ul>'                       => fn($h) => strpos($h, '<ul>') !== false,
    'ada 2 <li>'                     => fn($h) => preg_match_all('/<li>/i', $h) === 2,
]);
$totalPass += $r[0]; $totalFail += $r[1];

// 5) Codeblock ```bash
$r = runFixture('codeblock bash', "```bash\nls -la /var/www\n```", [
    'ada <pre><code'                 => fn($h) => preg_match('/<pre>\s*<code/i', $h) === 1,
    'class language-bash'            => fn($h) => strpos($h, 'language-bash') !== false,
    'perintah utuh'                  => fn($h) => strpos($h, 'ls -la /var/www') !== false,
]);
$totalPass += $r[0]; $totalFail += $r[1];

// 6) Tabel
$mdTable = "| Perintah | Fungsi |\n|---|---|\n| ls | Daftar file |\n| cd | Pindah direktori |";
$r = runFixture('tabel', $mdTable, [
    'ada <table>'                    => fn($h) => strpos($h, '<table>') !== false,
    'ada <th>'                       => fn($h) => strpos($h, '<th>') !== false,
    'ada <td>'                       => fn($h) => strpos($h, '<td>') !== false,
    'isi sel'                        => fn($h) => strpos($h, 'Pindah direktori') !== false,
]);
$totalPass += $r[0]; $totalFail += $r[1];

// 7) Link https
$r = runFixture('link https', '[Buka contoh](https://example.com/materi)', [
    'href https dipertahankan'       => fn($h) => strpos($h, 'href="https://example.com/materi"') !== false,
    'rel noopener'                   => fn($h) => strpos($h, 'rel="noopener noreferrer"') !== false,
]);
$totalPass += $r[0]; $totalFail += $r[1];

// 8) Link javascript:
$r = runFixture('link javascript:', '[Jangan klik](javascript:alert(1))', [
    'tidak ada javascript: di HTML'  => fn($h) => stripos($h, 'javascript:') === false,
    'tidak ada href berbahaya'       => fn($h) => preg_match('/href\s*=\s*"[^"]*javascript/i', $h) === 0,
]);
$totalPass += $r[0]; $totalFail += $r[1];

// 9) HTML mentah <script>
$r = runFixture('html script', "Teks aman\n\n<script>alert('xss')</script>", [
    'tidak ada tag <script'          => fn($h) => stripos($h, '<script') === false,
    'isi script ter-escape/terbuang' => fn($h) => strpos($h, 'alert(') === false || strpos($h, '&lt;script') !== false,
]);
$totalPass += $r[0]; $totalFail += $r[1];

// 10) HTML mentah <img onerror>
$r = runFixture('html img onerror', '<img src="x" onerror="alert(1)">', [
    'tidak ada atribut on*'          => fn($h) => preg_match('/<[^>]*\son\w+\s*=/i', $h) === 0,
    'tidak ada img mentah berbahaya' => fn($h) => preg_match('/<img[^>]*onerror/i', $h) === 0,
]);
$totalPass += $r[0]; $totalFail += $r[1];

echo "==============================================\n";
echo "TOTAL: {$totalPass} LULUS, {$totalFail} GAGAL\n";
echo "==============================================\n";

exit($totalFail === 0 ? 0 : 1);
