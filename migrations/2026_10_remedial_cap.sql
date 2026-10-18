-- ============================================================
-- Migration: Remedial Cap (batas_remedial) + skor_mentah
-- Tabel: kuis_hasil, kuis_sesi
-- ============================================================

-- a. Tambah kolom skor_mentah di sebelah skor (tipe sama, NULL dulu)
ALTER TABLE `kuis_hasil` ADD COLUMN `skor_mentah` INT(11) DEFAULT NULL AFTER `skor`;

-- b. Isi skor_mentah dengan nilai skor yang sudah ada
UPDATE `kuis_hasil` SET `skor_mentah` = `skor`;

-- c. Tambah batas_remedial ke kuis_sesi
ALTER TABLE `kuis_sesi` ADD COLUMN `batas_remedial` TINYINT UNSIGNED NOT NULL DEFAULT 75;

-- d. Pengecekan duplikat (user_id, sesi_id, attempt)
SELECT COUNT(*) AS duplikat FROM `kuis_hasil` GROUP BY `user_id`, `sesi_id`, `attempt` HAVING COUNT(*) > 1;

-- Jika hasil pengecekan di atas = 0 (tidak ada duplikat), jalankan:
ALTER TABLE `kuis_hasil` ADD UNIQUE KEY `uniq_user_sesi_attempt` (`user_id`, `sesi_id`, `attempt`);

ALTER TABLE `kuis_hasil` ADD COLUMN `detail_jawaban` JSON NULL AFTER `skor_mentah`;

SELECT h.id, h.user_id, h.sesi_id, h.attempt, h.skor, h.dikerjakan_at
FROM kuis_hasil h
JOIN (
  SELECT user_id, sesi_id, attempt
  FROM kuis_hasil
  WHERE sesi_id IS NOT NULL
  GROUP BY user_id, sesi_id, attempt
  HAVING COUNT(*) > 1
) d ON d.user_id = h.user_id AND d.sesi_id = h.sesi_id AND d.attempt = h.attempt
ORDER BY h.user_id, h.sesi_id, h.attempt, h.id;

SHOW COLUMNS FROM kuis_hasil WHERE Field IN ('skor_mentah','detail_jawaban');
SHOW COLUMNS FROM kuis_sesi LIKE 'batas_remedial';