<?php
/**
 * ai_game_preview.php — Endpoint preview sementara draft game HTML5 AI (KHUSUS ADMIN).
 *
 * Merender $_SESSION['aigm_draft']['game_html'] sebagai dokumen iframe `src`
 * (BUKAN `srcdoc`), supaya dokumen game TIDAK mewarisi CSP halaman induk.
 * Endpoint GET ini hanya untuk preview; penyimpanan file dan INSERT ke tabel
 * games dilakukan sekali oleh action submit di ai_game_maker.php.
 *
 * Perbaikan v2:
 *  - Mengirim header Content-Security-Policy langsung dari PHP (dengan header_remove
 *    terlebih dahulu) agar tidak bergantung penuh pada pencocokan path Nginx.
 *  - Tetap disarankan menggunakan `fastcgi_hide_header Content-Security-Policy;`
 *    atau override location di Nginx bila Nginx memakai `add_header ... always;`.
 */

require_once 'session.php';

checkLogin();
checkRole(['admin']);

// Draft preview dianggap kedaluwarsa setelah 1 jam.
const AIGM_PREVIEW_TTL = 3600;

function aigmpHeaders(string $contentType, bool $isHtml = false): void {
    @header_remove('Content-Security-Policy');
    @header_remove('X-Frame-Options');
    header('Content-Type: ' . $contentType);
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    if ($isHtml) {
        header("Content-Security-Policy: default-src 'none'; script-src 'unsafe-inline' 'unsafe-eval' blob: data:; style-src 'unsafe-inline'; img-src data: blob:; font-src data:; media-src data: blob:; connect-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'self';");
    }
}

// Hanya GET; metode lain -> 405
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    aigmpHeaders('text/plain; charset=utf-8', false);
    echo 'Metode tidak diizinkan.';
    exit;
}

$draft = $_SESSION['aigm_draft'] ?? null;

$missing = !is_array($draft) || empty($draft['game_html']);
$expired = !$missing && (time() - (int)($draft['created'] ?? 0) > AIGM_PREVIEW_TTL);

if ($missing || $expired) {
    if ($expired && isset($_SESSION['aigm_draft'])) {
        unset($_SESSION['aigm_draft']);
    }
    session_write_close();

    http_response_code(404);
    aigmpHeaders('text/plain; charset=utf-8', false);
    echo 'Draft game tidak ditemukan atau kedaluwarsa. Generate ulang.';
    exit;
}

// Tutup session sebelum output supaya lock tidak ditahan selama iframe dimuat.
session_write_close();

// game_html sudah dibungkus guard (shim + LMS helper + heartbeat) saat generate.
aigmpHeaders('text/html; charset=utf-8', true);
echo (string)$draft['game_html'];