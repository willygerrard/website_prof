<?php
/**
 * log_aktivitas.php
 * Helper pencatat aktivitas belajar siswa (fitur Log Aktivitas Belajar).
 * File include: berisi fungsi saja, TANPA output, TANPA akses langsung.
 *
 * Cara pakai (setelah session.php + koneksi.php):
 *   require_once 'log_aktivitas.php';
 *   catat_log_aktivitas($pdo, $modul_id, 'buka_modul');
 *
 * Kegagalan logging TIDAK BOLEH merusak halaman: semua error ditulis
 * ke error_log() dan fungsi mengembalikan false.
 */

if (!defined('LOG_AKTIVITAS_INCLUDED')) {
    define('LOG_AKTIVITAS_INCLUDED', true);
}

if (!function_exists('catat_log_aktivitas')) {

    /**
     * Catat aktivitas 'buka_modul' siswa dengan debounce 30 menit.
     *
     * Aturan:
     *  - Hanya role 'siswa' yang dicatat.
     *  - Hanya jika toggle pengaturan log_aktivitas_status = 'buka'
     *    (format nilai mengikuti registrasi_status: 'buka'/'tutup').
     *  - Debounce 30 menit per (user, modul, aksi) via satu statement atomik
     *    INSERT ... SELECT ... WHERE NOT EXISTS.
     *  - Waktu dihitung di PHP (bukan NOW() di SQL).
     *
     * @param PDO    $pdo      Koneksi database aktif
     * @param int    $modul_id ID modul (modules.id)
     * @param string $aksi     Nilai enum: 'buka_modul' (yang lain hanya disediakan di skema)
     * @return bool true jika baris tercatat, false jika tidak (toggle off, bukan siswa,
     *              modul tidak ada, debounce, atau error DB)
     */
    function catat_log_aktivitas(PDO $pdo, int $modul_id, string $aksi = 'buka_modul'): bool
    {
        try {
            // (a) Toggle fitur: keluar tanpa insert jika nonaktif.
            $stmt = $pdo->prepare("SELECT `value` FROM `pengaturan` WHERE `key` = ? LIMIT 1");
            $stmt->execute(['log_aktivitas_status']);
            $status = $stmt->fetchColumn();
            if ($status === false || $status !== 'buka') {
                return false;
            }

            // (b) Hanya siswa; modul_id harus valid dan modulnya ada.
            if (($_SESSION['role'] ?? '') !== 'siswa') {
                return false;
            }
            $user_id = (int)($_SESSION['user_id'] ?? 0);
            if ($user_id <= 0 || $modul_id <= 0) {
                return false;
            }
            // Nilai aksi dibatasi ke enum yang sah.
            $aksi_izin = ['buka_modul', 'submit_tugas', 'kerjakan_quiz'];
            if (!in_array($aksi, $aksi_izin, true)) {
                return false;
            }
            // Fitur ini hanya memasang trigger 'buka_modul' (submit_tugas dan
            // kerjakan_quiz disediakan di skema, di luar scope).
            if ($aksi !== 'buka_modul') {
                return false;
            }

            $stmt_modul = $pdo->prepare("SELECT id FROM `modules` WHERE id = ? LIMIT 1");
            $stmt_modul->execute([$modul_id]);
            if (!$stmt_modul->fetchColumn()) {
                return false;
            }

            // Nama siswa: nama_asli bila ada, fallback username.
            $nama = trim((string)($_SESSION['nama_asli'] ?? '')) ?: (string)($_SESSION['username'] ?? '');
            $nama = mb_substr($nama, 0, 100);

            $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
            $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

            // (c) Debounce 30 menit: batas waktu dihitung di PHP.
            $batas = date('Y-m-d H:i:s', time() - 1800);
            $now   = date('Y-m-d H:i:s');

            $sql = "INSERT INTO log_aktivitas
                        (user_id, nama, modul_id, aksi, ip_address, user_agent, created_at)
                    SELECT ?, ?, ?, ?, ?, ?, ?
                    FROM DUAL
                    WHERE NOT EXISTS (
                        SELECT 1 FROM log_aktivitas
                        WHERE user_id = ? AND modul_id = ? AND aksi = 'buka_modul'
                          AND created_at >= ?
                    )";
            $stmt_ins = $pdo->prepare($sql);
            $stmt_ins->execute([
                $user_id, $nama, $modul_id, $aksi, $ip, $ua, $now,
                $user_id, $modul_id, $batas,
            ]);

            return $stmt_ins->rowCount() > 0;
        } catch (PDOException $e) {
            // Logging tidak boleh merusak halaman.
            error_log('catat_log_aktivitas gagal: ' . $e->getMessage());
            return false;
        } catch (Throwable $t) {
            error_log('catat_log_aktivitas error: ' . $t->getMessage());
            return false;
        }
    }
}
