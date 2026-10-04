-- ============================================================
-- STEP 2 — Migration data: toggle log_aktivitas_status di tabel pengaturan
-- Ini PENAMBAHAN DATA, bukan perubahan struktur tabel existing.
-- Format nilai mengikuti registrasi_status: 'buka' / 'tutup' (hasil recon
-- toggle_registrasi.php:27 dan signup.php:127).
-- Default: 'tutup' (nonaktif).
-- Idempoten: INSERT ... ON DUPLICATE KEY UPDATE, key sudah UNIQUE.
-- ============================================================

INSERT INTO `pengaturan` (`key`, `value`)
VALUES ('log_aktivitas_status', 'tutup')
ON DUPLICATE KEY UPDATE `key` = `key`;
