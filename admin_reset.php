<?php
header('Content-Type: application/json');
require __DIR__ . '/koneksi.php'; // hasilkan $pdo, sesuaikan

$secret = '8cff8d8c0a80a0d2cf06e71b8ec30147d6bea1cd8318c5f23e1dd7b067a313ce';
$sent   = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
if ($secret === '' || !hash_equals($secret, $sent)) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'forbidden']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => 'method not allowed']));
}

$id = (int)($_POST['user_id'] ?? 0);

$st = $pdo->prepare(
    "SELECT COALESCE(NULLIF(nama_asli, ''), username) AS nama, kelas, role
     FROM users WHERE id = ?"
);
$st->execute([$id]);
$u = $st->fetch(PDO::FETCH_ASSOC);

if (!$u || $u['role'] === 'admin') {
    http_response_code(404);
    exit(json_encode(['ok' => false, 'error' => 'user tidak ada']));
}

$up = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
$up->execute([password_hash(DEFAULT_RESET_PASSWORD, PASSWORD_DEFAULT), $id]);

echo json_encode(['ok' => true, 'nama' => $u['nama'], 'kelas' => $u['kelas']]);