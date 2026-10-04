-- ============================================================
-- STEP 1 — Migration fitur "Log Aktivitas Belajar + Absensi QR Hybrid"
-- Tabel: absensi_sesi, absensi, log_aktivitas (urutan sesuai dependensi FK)
-- Catatan:
--  - Tipe id rujukan: users.id, modules.id = int(11) SIGNED (hasil recon).
--  - Tidak ada tabel `mapel` di skema existing (keputusan user: Opsi A),
--    jadi `mapel_id` int(11) TANPA foreign key, mengikuti tipe id lain.
--  - Hanya `absensi.sesi_id` yang ber-FK ke absensi_sesi.id.
--  - Aman dijalankan ulang (CREATE TABLE IF NOT EXISTS).
-- ============================================================

CREATE TABLE IF NOT EXISTS `absensi_sesi` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `mapel_id` INT(11) NOT NULL,
  `guru_id` INT(11) NOT NULL,
  `kelas` VARCHAR(50) NOT NULL,
  `tanggal` DATE NOT NULL,
  `jam_mulai` TIME NOT NULL,
  `jam_selesai` TIME NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `expired_at` DATETIME NOT NULL,
  `status` ENUM('buka','tutup') NOT NULL DEFAULT 'buka',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_absensi_sesi_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `absensi` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `sesi_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `nama` VARCHAR(100) NOT NULL,
  `status` ENUM('hadir','sakit','izin','alpa','terlambat') NOT NULL,
  `waktu_scan` DATETIME NULL DEFAULT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `keterangan` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_absensi_sesi_user` (`sesi_id`, `user_id`),
  KEY `idx_absensi_user` (`user_id`),
  CONSTRAINT `fk_absensi_sesi` FOREIGN KEY (`sesi_id`) REFERENCES `absensi_sesi` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `log_aktivitas` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `nama` VARCHAR(100) NOT NULL,
  `modul_id` INT(11) NOT NULL,
  `aksi` ENUM('buka_modul','submit_tugas','kerjakan_quiz') NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_log_user_created` (`user_id`, `created_at`),
  KEY `idx_log_modul` (`modul_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
