#!/usr/bin/env bash
# Dump penuh DB production (Docker) -> import ke staging -> anonimkan data pribadi siswa.
# Production HANYA dibaca (mysqldump). Semua UPDATE dijalankan di staging saja.
#
# Pakai:
#   export PROD_PW='...' STG_PW='...'
#   ./refresh_staging_from_prod.sh
#
# Catatan: kalau image MariaDB-mu tidak punya `mysqldump`, ganti jadi `mariadb-dump`
# (dan `mysql` jadi `mariadb`) pada perintah docker exec di bawah.

set -euo pipefail

# ---------- SESUAIKAN ----------
PROD_CTR="website_prof"       		# nama container DB production
STG_CTR="website_prof_staging"     # nama container DB staging
PROD_DB="db_website_prof"             # nama database production
STG_DB="website_prof_db_staging"      # nama database staging
PROD_USER="willy"
STG_USER="willy"
OUT_DIR="$HOME/dumps"
# -------------------------------

: "${PROD_PW:?Set PROD_PW dulu (export PROD_PW=...)}"
: "${STG_PW:?Set STG_PW dulu (export STG_PW=...)}"

# ---------- GUARD: jangan sampai menulis ke production ----------
[[ "$STG_DB" == *staging* ]]   || { echo "ABORT: STG_DB harus mengandung 'staging'"; exit 1; }
[[ "$STG_DB" != "$PROD_DB" ]]  || { echo "ABORT: STG_DB sama dengan PROD_DB"; exit 1; }
[[ "$STG_CTR" != "$PROD_CTR" ]] || { echo "ABORT: container staging dan production sama"; exit 1; }

echo "Dump : $PROD_CTR / $PROD_DB   (hanya baca)"
echo "Import: $STG_CTR / $STG_DB    (data staging akan ditimpa, termasuk data dummy)"
read -rp "Ketik 'ya' untuk lanjut: " ok
[[ "$ok" == "ya" ]] || { echo "Dibatalkan."; exit 1; }

# ---------- 1. DUMP PRODUCTION ----------
umask 077                                  # file dump hanya bisa dibaca pemilik
mkdir -p "$OUT_DIR"
DUMP="$OUT_DIR/prod_$(date +%Y%m%d_%H%M%S).sql.gz"

# MYSQL_PWD diteruskan lewat environment (tidak muncul di daftar proses)
MYSQL_PWD="$PROD_PW" docker exec -e MYSQL_PWD "$PROD_CTR" \
  mysqldump -u"$PROD_USER" \
    --single-transaction --quick \
    --routines --triggers --events \
    --default-character-set=utf8mb4 \
    --ignore-table="$PROD_DB.pengumpulan_tugas_backup_20260921" \
    "$PROD_DB" | gzip > "$DUMP"

echo "Dump selesai: $DUMP ($(du -h "$DUMP" | cut -f1))"

# ---------- 2. IMPORT KE STAGING ----------
gunzip -c "$DUMP" | MYSQL_PWD="$STG_PW" docker exec -i -e MYSQL_PWD "$STG_CTR" \
  mysql -u"$STG_USER" "$STG_DB"
echo "Import selesai."

# ---------- 3. ANONIMKAN (hanya di staging) ----------
# Sesuaikan 'id' dan nilai role siswa dengan skema/kode aslinya.
MYSQL_PWD="$STG_PW" docker exec -i -e MYSQL_PWD "$STG_CTR" \
  mysql -u"$STG_USER" "$STG_DB" <<'SQL'
START TRANSACTION;

UPDATE users
SET nama        = CONCAT('Siswa ', LPAD(id, 4, '0')),
    no_wa_ortu  = CONCAT('0812000', LPAD(id, 5, '0'))
WHERE role = 'siswa';

-- Opsional: kalau username berisi NIS/nama asli, anonimkan juga.
-- UPDATE users SET username = CONCAT('siswa', LPAD(id, 4, '0')) WHERE role = 'siswa';

-- Opsional: samakan semua password di staging (isi dengan hash dari password_hash() PHP).
-- UPDATE users SET password = '<HASH_BCRYPT>';

COMMIT;
SQL

# ---------- 4. VERIFIKASI ----------
LEFT=$(MYSQL_PWD="$STG_PW" docker exec -e MYSQL_PWD "$STG_CTR" \
  mysql -u"$STG_USER" -N -B "$STG_DB" \
  -e "SELECT COUNT(*) FROM users WHERE role='siswa' AND (no_wa_ortu NOT LIKE '0812000%' OR nama NOT LIKE 'Siswa %');")

if [[ "$LEFT" != "0" ]]; then
  echo "GAGAL: masih ada $LEFT siswa yang belum teranonimkan. Cek staging sebelum dipakai!"
  exit 1
fi
echo "OK: semua siswa di staging sudah teranonimkan."

echo
echo "Kolom lain yang mungkin berisi data pribadi/rahasia (cek manual):"
MYSQL_PWD="$STG_PW" docker exec -e MYSQL_PWD "$STG_CTR" \
  mysql -u"$STG_USER" "$STG_DB" -e \
  "SELECT table_name, column_name FROM information_schema.columns
   WHERE table_schema = '$STG_DB'
     AND column_name REGEXP 'nama|wa|telp|hp|email|nis|alamat|token|api|key'
   ORDER BY table_name;"

# ---------- 5. BERSIHKAN FILE DUMP (berisi data asli) ----------
if [[ "${KEEP_DUMP:-0}" != "1" ]]; then
  rm -f "$DUMP"
  echo "File dump dihapus. (Set KEEP_DUMP=1 kalau mau menyimpannya.)"
else
  echo "File dump DISIMPAN di $DUMP, berisi data asli. Amankan atau hapus setelah dipakai."
fi
