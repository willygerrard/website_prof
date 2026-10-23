# Panduan Deploy — AI Module Maker ke Production

Panduan ini mencakup migrasi database, penyiapan storage, symlink publik, konfigurasi Nginx, environment variable, verifikasi, dan rollback.

---

## 1. Ringkasan arsitektur

| Komponen | Path di container | Path di host (contoh) | Peran |
|---|---|---|---|
| Storage privat | `/var/www/data_private/modul_ai/pending/` | `/mnt/hdd2tb/ai_modules_production/modul_ai/pending/` | File HTML asli hasil generate/submit |
| Alias host | — | `/var/www/data_private` → storage privat | Agar Nginx (di host) bisa me-resolve target symlink |
| Pintu publik | `/var/www/html/modul/` | `<DOCROOT>/modul/` | Hanya berisi **symlink** modul yang sudah di-approve |
| Docroot | `/var/www/html` | `/var/www/html/website_prof` | Root Nginx production |

Alur:

1. **Submit** → file ditulis ke storage privat, DB `INSERT` dengan `status='pending'`, `file_path=NULL`.
2. **Approve** → symlink dibuat di `<DOCROOT>/modul/<file>` → storage privat; DB `UPDATE status='approved', file_path='modul/<file>'`.
3. **Siswa** membuka `https://<domain>/modul/<file>` → Nginx mengikuti symlink → file disajikan.
4. **Reject** → file privat + symlink dihapus, baris DB dihapus.

> `<DOCROOT>` production saat ini: `/var/www/html/website_prof` (host), yang di container PHP ter-mount sebagai `/var/www/html`.

---

## 2. Prasyarat

- PHP 8.x dengan `pdo_mysql`; fungsi `symlink()` **tidak** dinonaktifkan.
- MariaDB ≥ 10.0.2 (production saat ini MariaDB 11.x).
- Nginx production dengan docroot `/var/www/html/website_prof`.
- Akses root ke host (untuk symlink dan reload Nginx) serta akses ke container PHP.
- Kode fitur AI Module Maker sudah ter-deploy (termasuk `lib/Parsedown.php`).

---

## 3. Langkah 1 — Backup database

```bash
# sesuaikan nama DB & kredensial dengan .env production
docker exec website_prof_db sh -c \
  'mariadb-dump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
  > backup_sebelum_ai_module_maker_$(date +%F_%H%M).sql
```

Pastikan file backup tidak kosong sebelum lanjut.

---

## 4. Langkah 2 — Migrasi database

File: `migrations/2026-10-23_ai_module_maker_production.sql`

Jalankan ke database aplikasi production:

```bash
docker exec -i website_prof_db mariadb -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" \
  < migrations/2026-10-23_ai_module_maker_production.sql
```

Script bersifat **idempotent** (aman dijalankan ulang) dan akan:

- menambah `ai_file VARCHAR(255) NULL`
- menambah `status ENUM('pending','approved') NOT NULL DEFAULT 'approved'`
- mengubah `file_path` menjadi NULL-able
- menambah index `idx_modules_status`
- menormalkan nilai `status` yang tidak valid menjadi `approved`
- menampilkan hasil verifikasi

Modul manual yang sudah ada otomatis menjadi `approved` — tidak ada perubahan perilaku bagi siswa.

---

## 5. Langkah 3 — Storage privat

```bash
# root di host
mkdir -p /mnt/hdd2tb/ai_modules_production/modul_ai/pending
chown -R www-data:www-data /mnt/hdd2tb/ai_modules_production
chmod -R 775 /mnt/hdd2tb/ai_modules_production
```

Tambahkan volume ke service PHP production (file compose yang dipakai production):

```yaml
volumes:
  - /var/www/html/website_prof:/var/www/html
  - /mnt/hdd2tb/uploads_lms:/var/www/html/uploads
  - /mnt/hdd2tb/ai_modules_production:/var/www/data_private   # <-- baru
```

Recreate container PHP agar mount aktif:

```bash
docker compose -f <file-compose-production>.yml up -d php
```

Verifikasi dari dalam container:

```bash
docker exec website_prof_php sh -c 'ls -ld /var/www/data_private/modul_ai/pending'
```

---

## 6. Langkah 4 — Folder publik `<DOCROOT>/modul`

Folder ini **tidak menyimpan file**, hanya symlink. Harus writable oleh user PHP (`www-data`, uid 33).

```bash
mkdir -p /var/www/html/website_prof/modul
chgrp www-data /var/www/html/website_prof/modul
chmod 2775 /var/www/html/website_prof/modul
```

Verifikasi dari container:

```bash
docker exec website_prof_php sh -c \
  'su -s /bin/sh www-data -c "touch /var/www/html/modul/.probe && rm /var/www/html/modul/.probe" && echo OK'
```

> Kode membuat folder ini otomatis bila belum ada, tetapi hanya berhasil jika docroot writable. Cara di atas lebih pasti.

---

## 7. Langkah 5 — Symlink host `/var/www/data_private`

Symlink dibuat PHP memakai path container `/var/www/data_private/...`. Karena Nginx berjalan di **host**, host juga harus bisa me-resolve path tersebut.

```bash
# root di host — hanya jika belum ada
ln -s /mnt/hdd2tb/ai_modules_production /var/www/data_private

ls -ld /var/www/data_private
# harus: /var/www/data_private -> /mnt/hdd2tb/ai_modules_production
```

Jika `/var/www/data_private` sudah ada sebagai direktori, jangan ditimpa — pindahkan isinya dulu atau sesuaikan mount.

---

## 8. Langkah 6 — Nginx

Tambahkan di dalam blok `server` production (`/etc/nginx/conf.d/website_prof.conf`):

```nginx
# Blokir akses langsung ke storage privat (defense-in-depth)
location ^~ /data_private/ {
    deny all;
    return 403;
}

# Izinkan akses symlink modul yang sudah di-approve
location ^~ /modul/ {
    disable_symlinks off;
    location ~ \.php$ { deny all; }
}
```

Lalu:

```bash
nginx -t && systemctl reload nginx
```

Catatan: `/modul/` memakai `root` yang sudah ada (`/var/www/html/website_prof`), jadi file dilayani dari `<DOCROOT>/modul/`.

---

## 9. Langkah 7 — Environment variable

Di container PHP production, pastikan tersedia:

| Variabel | Wajib | Contoh |
|---|---|---|
| `GEMINI_API_KEY` | ya | kunci API Gemini |
| `GEMINI_MODULE_MODEL` | tidak | `gemini-3.5-flash` (default bila kosong) |
| `APP_ENV` | ya | `production` (agar `koneksi.php` membaca `.env`) |

Aplikasi membaca nilai lewat `aiqEnv()` yang juga membaca file `.env` / `.env.staging` sesuai `APP_ENV`. Jangan menaruh kunci API di dalam repo.

---

## 10. Langkah 8 — Deploy kode

File yang harus ikut ter-deploy:

- `ai_module_maker.php`
- `ai_module_preview.php`
- `management_pending.php`
- `management_modul.php`
- `includes/navbar.php`
- `ai_helper.php`
- `lib/Parsedown.php` ← **wajib**, konverter Markdown
- `migrations/2026-10-23_ai_module_maker_production.sql`

Opsional: `tests/markdown_test.php` (hanya untuk pengujian CLI, tidak diakses web).

Pastikan permission file mengikuti file lain di docroot (`www-data` read).

---

## 11. Verifikasi

### 11.1 Database

```sql
SHOW COLUMNS FROM modules LIKE 'ai_file';
SHOW COLUMNS FROM modules LIKE 'status';
SHOW COLUMNS FROM modules LIKE 'file_path';
SHOW INDEX FROM modules WHERE Key_name = 'idx_modules_status';
SELECT status, COUNT(*) FROM modules GROUP BY status;
```

Harapan: `ai_file` NULL-able, `status` enum dengan default `approved`, `file_path` NULL-able, index ada.

### 11.2 Alur aplikasi

1. Login sebagai admin → menu **Admin Panel → Modul Pending (AI)** harus terbuka (HTTP 200).
2. Buka **AI Module Maker** → pilih kategori, isi topik spesifik, Generate.
3. Preview tampil di iframe; tidak ada tanda `*` mentah; daftar bernomor tampil sebagai `<ol>`.
4. Klik **Submit & Simpan Modul** → muncul notifikasi sukses; baris DB `status='pending'`, `ai_file` terisi, `file_path` NULL.
5. Buka halaman pending → **Approve**:
   - symlink muncul di `<DOCROOT>/modul/`
   - DB `status='approved'`, `file_path='modul/<file>'`
6. Buka `https://<domain>/modul/<file>` → **HTTP 200**, isi modul tampil.
7. Login sebagai siswa → modul muncul di daftar dan bisa dibuka.

### 11.3 Cek cepat file

```bash
ls -l /var/www/html/website_prof/modul/
# contoh: ai_judul-modul_1792xxxx.html -> /var/www/data_private/modul_ai/pending/ai_judul-modul_1792xxxx.html

curl -I https://<domain>/modul/<file>
# harapan: HTTP/1.1 200 OK
```

---

## 12. Rollback

Urutan aman:

```bash
# 1) Hentikan fitur: nonaktifkan menu/tautan (opsional) atau rollback kode
# 2) Hapus symlink publik
find /var/www/html/website_prof/modul -maxdepth 1 -type l -delete

# 3) Rollback DB (SETELAH backup)
```

```sql
ALTER TABLE modules DROP INDEX idx_modules_status;
ALTER TABLE modules DROP COLUMN status;
ALTER TABLE modules DROP COLUMN ai_file;
-- file_path: hanya kembalikan NOT NULL jika tidak ada baris NULL
-- ALTER TABLE modules MODIFY file_path VARCHAR(255) NOT NULL;
```

Catatan: menghapus kolom `status` menghilangkan informasi approval. File modul di storage privat tidak terhapus otomatis.

---

## 13. Troubleshooting

| Gejala | Penyebab | Solusi |
|---|---|---|
| Approve gagal "Gagal membuat tautan publik" | `<DOCROOT>/modul` tidak writable oleh `www-data`, atau `symlink()` dinonaktifkan | Perbaiki owner/mode folder; cek `disable_functions` |
| `/modul/<file>` 404 | Host tidak bisa me-resolve `/var/www/data_private`, atau symlink belum ada | Buat symlink host; pastikan modul sudah di-approve |
| `/modul/<file>` 403 | Nginx memblokir, atau file target hilang | Cek blok `location ^~ /modul/` dan target symlink |
| Submit gagal "Gagal menyimpan file modul ke disk" | Storage privat tidak writable / mount belum aktif | Cek mount dan permission `www-data` |
| Generate error 502 "terlalu singkat" | Output AI tidak memenuhi 5 heading h2 / panjang minimum | Perjelas topik spesifik, generate ulang |
| Generate error 422 "Topik tidak relevan" | AI menolak kombinasi kategori–topik | Pilih topik yang sesuai kategori |
| Halaman pending 403 | Bukan admin / belum login | Login sebagai admin |
| Preview kosong | Draft kedaluwarsa (> 1 jam) | Generate ulang |

---

## 14. Git branch & push

Saat panduan ini dibuat, branch lokal `release/kuis-remedial-navbar-2026-10` berisi **26 commit AI Module Maker yang belum di-push**. Nama branch tersebut tidak menggambarkan isinya.

Disarankan membuat branch fitur baru dari commit terakhir:

```bash
git switch -c feature/ai-module-maker
git push -u origin feature/ai-module-maker
```

Lalu buka Pull Request ke `main` (atau `staging` sesuai alur rilis Anda).

Jika tetap ingin memakai branch rilis yang ada:

```bash
git push -u origin release/kuis-remedial-navbar-2026-10
```

Jangan commit `Dockerfile` lokal, `RANCANGAN_*.txt`, atau kredensial/`.env`.
