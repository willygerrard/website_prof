<?php
include 'koneksi.php';
require_once 'fonnte.php';
session_start();

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

$user_id  = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? '';
$modul_id = (int)($_GET['id'] ?? 0);

if (!$user_id || !$modul_id) {
    header("Location: index.php");
    exit();
}

// Ambil data modul
$stmt = $pdo->prepare("SELECT * FROM modules WHERE id = ?");
$stmt->execute([$modul_id]);
$modul = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$modul || empty($modul['file_path'])) {
    header("Location: index.php");
    exit();
}

$cek_sesi = $pdo->query("SELECT status FROM notifikasi_sesi ORDER BY id DESC LIMIT 1")->fetchColumn();

if ($cek_sesi === 'aktif') {
    // kirim WA seperti biasa

// Cek apakah notifikasi untuk modul ini sudah pernah dikirim ke siswa ini
$cek = $pdo->prepare("SELECT id FROM notifikasi_modul WHERE user_id = ? AND modul_id = ?");
$cek->execute([$user_id, $modul_id]);

if (!$cek->fetch()) {
    // Belum pernah notif, catat dulu (anti race-condition kalau user klik 2x cepat)
    try {
        $insert = $pdo->prepare("INSERT INTO notifikasi_modul (user_id, modul_id, terkirim_at) VALUES (?, ?, NOW())");
        $insert->execute([$user_id, $modul_id]);

        // Kirim WA hanya kalau insert berhasil (mencegah double-send kalau ada race condition)
        $stmt_wa = $pdo->prepare("SELECT no_wa_ortu FROM users WHERE id = ?");
        $stmt_wa->execute([$user_id]);
        $no_wa = $stmt_wa->fetchColumn();

        if ($no_wa) {
            $tanggal = date('d M Y, H:i');
            $pesan = "📚 *Pusat Pembelajaran SIJA*\n\n" . htmlspecialchars($username) . " membuka modul:\n*" . htmlspecialchars($modul['title']) . "*\n\nPada: $tanggal";
            kirimWA($no_wa, $pesan);
        }
    } catch (PDOException $e) {
        // Kalau gagal insert (misal race condition unique key), abaikan saja, lanjut redirect
        error_log("Notifikasi modul gagal: " . $e->getMessage());
    }
}

}
// Redirect ke link asli modul
header("Location: " . $modul['file_path']);
exit();