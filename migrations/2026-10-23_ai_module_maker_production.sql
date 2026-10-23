-- ============================================================
-- AI Module Maker — Production Migration
-- Tabel : `modules`
-- Target: MariaDB >= 10.0.2 / MySQL >= 5.7
-- Sifat : idempotent — aman dijalankan ulang
--
-- Perubahan:
--   1. `ai_file`   VARCHAR(255) NULL  : nama file HTML modul AI di storage privat
--   2. `status`    ENUM('pending','approved') NOT NULL DEFAULT 'approved'
--   3. `file_path` dibuat NULL-able   : modul AI belum punya path publik sebelum approve
--   4. Index `idx_modules_status`     : mempercepat query halaman pending
--
-- Catatan:
--   - Modul manual yang sudah ada otomatis bernilai 'approved' lewat DEFAULT.
--   - Tidak ada kolom/tabel/data yang dihapus.
--   - Jalankan pada database aplikasi (bukan database lain).
-- ============================================================

SET @db := DATABASE();

-- ------------------------------------------------------------
-- 1. Kolom ai_file
-- ------------------------------------------------------------
SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'modules' AND COLUMN_NAME = 'ai_file'
);
SET @sql := IF(@has = 0,
  'ALTER TABLE `modules` ADD COLUMN `ai_file` VARCHAR(255) NULL DEFAULT NULL AFTER `image_path`',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 2. Kolom status
-- ------------------------------------------------------------
SET @has := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'modules' AND COLUMN_NAME = 'status'
);
SET @sql := IF(@has = 0,
  "ALTER TABLE `modules` ADD COLUMN `status` ENUM('pending','approved') NOT NULL DEFAULT 'approved'",
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 3. file_path NULL-able
--    (skema lama NOT NULL akan membuat INSERT modul AI gagal
--     karena file_path belum diisi sampai tahap approve)
-- ------------------------------------------------------------
ALTER TABLE `modules`
  MODIFY `file_path` VARCHAR(255) NULL DEFAULT NULL;

-- ------------------------------------------------------------
-- 4. Normalisasi nilai status
--    - NULL / nilai di luar enum dianggap 'approved' (modul existing)
--    - Pastikan definisi kolom sesuai
-- ------------------------------------------------------------
UPDATE `modules`
   SET `status` = 'approved'
 WHERE `status` IS NULL
    OR `status` NOT IN ('pending', 'approved');

ALTER TABLE `modules`
  MODIFY `status` ENUM('pending','approved') NOT NULL DEFAULT 'approved';

-- ------------------------------------------------------------
-- 5. Index status (idempotent)
-- ------------------------------------------------------------
SET @has := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'modules' AND INDEX_NAME = 'idx_modules_status'
);
SET @sql := IF(@has = 0,
  'ALTER TABLE `modules` ADD INDEX `idx_modules_status` (`status`)',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 6. Verifikasi
-- ------------------------------------------------------------
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = @db
   AND TABLE_NAME = 'modules'
   AND COLUMN_NAME IN ('ai_file', 'status', 'file_path')
 ORDER BY COLUMN_NAME;

SELECT `status`, COUNT(*) AS jumlah
  FROM `modules`
 GROUP BY `status`;

-- ============================================================
-- SELESAI
-- ============================================================
