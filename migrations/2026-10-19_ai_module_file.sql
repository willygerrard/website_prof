-- ============================================================
-- Migration fitur "AI Module Maker" (Fase 3)
-- Tabel: modules
--  - ai_file  : nama file (basename, bukan path) HTML modul AI di folder
--               private /var/www/data_private/modul_ai/pending/. Diisi saat
--               submit (status='pending'); file_path tetap NULL sampai
--               approval membuat symlink dan mengisi file_path.
--  - status   : 'pending' = modul AI menunggu approval admin,
--               'approved' = boleh tampil ke siswa. Modul manual existing
--               otomatis 'approved' lewat DEFAULT.
--  - file_path dibuat nullable: modul AI belum punya path publik sebelum
--               di-approve (skema lama NOT NULL akan membuat INSERT gagal).
-- Idempoten: ADD COLUMN IF NOT EXISTS didukung MariaDB >= 10.0.2;
-- MODIFY file_path aman dijalankan ulang karena menyetel definisi yang sama.
-- ============================================================

ALTER TABLE `modules`
  ADD COLUMN IF NOT EXISTS `ai_file` VARCHAR(255) NULL DEFAULT NULL AFTER `image_path`,
  ADD COLUMN IF NOT EXISTS `status` ENUM('pending','approved') NOT NULL DEFAULT 'approved';

ALTER TABLE `modules`
  MODIFY `file_path` VARCHAR(255) NULL DEFAULT NULL;
