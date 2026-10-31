<?php
/**
 * ai_game_preview.php — Endpoint preview draft game HTML5 AI (KHUSUS ADMIN).
 *
 * Merender $_SESSION['aigm_draft']['game_html'] sebagai dokumen iframe `src`
 * (BUKAN `srcdoc`), supaya dokumen game TIDAK mewarisi CSP halaman induk.
 *
 * PENTING (lihat nginx-staging.conf.template):
 * Header CSP untuk endpoint ini dipasang di level nginx
 *   location = /ai_game_preview.php { add_header Content-Security-Policy "..."; }
 * karena CSP situs-lebar di server block dikirim ke SEMUA respons (add_header
 * always). Tanpa override di nginx, game akan tetap diblok dan tampil putih.
 *
 * CSP preview (di nginx):
 *   default-src 'none'; script-src 'unsafe-inline' 'unsafe-eval' blob:;
 *   style-src 'unsafe-inline'; img-src data: blob:; font-src data:;
 *   media-src data: blob:; connect-src 'none'; base-uri 'none';
 *   form-action 'none'; frame-ancestors 'self';
 *   sandbox allow-scripts allow-forms
 */

require_once 'session.php';
require 'koneksi.php';

checkLogin();
checkRole(['admin']);

// Draft preview dianggap kedaluwarsa setelah 1 jam (samakan dgn pola modul).
const AIGM_PREVIEW_TTL = 3600;

function aigmpHeaders(string $contentType): void {
    header('Content-Type: ' . $contentType);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
}

// Hanya GET; metode lain -> 405
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    aigmpHeaders('text/plain; charset=utf-8');
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
    aigmpHeaders('text/plain; charset=utf-8');
    echo 'Draft game tidak ditemukan atau kedaluwarsa. Generate ulang.';
    exit;
}

// Tutup session sebelum output supaya lock tidak ditahan selama iframe dimuat.
session_write_close();

// game_html sudah dibungkus guard (meta CSP + shim + heartbeat) saat generate.
aigmpHeaders('text/html; charset=utf-8');
echo (string)$draft['game_html'];
