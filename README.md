# MONIKA — Monitoring Anak Magang

Aplikasi full stack Laravel 12 + Blade + PHP 8.2 + MySQL 8, dengan antarmuka responsif berbahasa Indonesia. CSS dan JavaScript disertakan langsung sehingga tidak membutuhkan Node.js, npm, atau koneksi CDN untuk berjalan. Paket ZIP menyertakan folder `vendor` yang sudah terpasang; `composer install` tetap disarankan untuk verifikasi dependensi pada komputer Anda.

## Menjalankan di Laragon (Windows)

1. Ekstrak folder `monika` ke `C:\laragon\www\monika`.
2. Pilih PHP **8.2 atau lebih baru** di Laragon. Aktifkan ekstensi `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `xml`, `ctype`, `curl`, `dom`, dan `zip`. Jalankan Apache/Nginx dan MySQL.
3. Buka terminal Laragon di folder proyek, lalu jalankan:

```bat
composer install
copy .env.example .env
php artisan key:generate
```

4. Buat database melalui HeidiSQL/phpMyAdmin, atau jalankan `database/create-database.sql`. Atur `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=monika
DB_USERNAME=root
DB_PASSWORD=
MONIKA_ADMIN_EMAIL=admin@monika.test
MONIKA_ADMIN_PASSWORD="GantiDenganPasswordKuat123!"
MONIKA_DEMO=false
```

5. Buat semua tabel dan akun admin:

```bat
php artisan migrate --seed
php artisan serve
```

6. Buka **http://127.0.0.1:8000** dan masuk dengan email serta password yang Anda tentukan. Jika menggunakan virtual host `http://monika.test`, pastikan document root mengarah ke folder **public**, bukan akar proyek, dan sesuaikan `APP_URL`.

Seeder menggunakan `firstOrCreate` untuk akun, sehingga menjalankannya ulang tidak mereset password akun yang sudah ada. Tidak ada password admin bawaan di arsip.

## Data demo opsional

Di lingkungan `APP_ENV=local`, ubah `MONIKA_DEMO=true`, lalu jalankan `php artisan db:seed`. Tersedia `mentor@monika.test`, `ketua@monika.test`, dan `peserta@monika.test`, dengan password yang sama dengan `MONIKA_ADMIN_PASSWORD` saat akun pertama dibuat. Kelompok demo berlaku dari awal bulan saat seeding sampai akhir tiga bulan berikutnya. Jangan aktifkan data demo di produksi.

## Fitur dan hak akses

| Peran | Fitur |
|---|---|
| Pengunjung | Beranda, pengajuan dengan surat PDF maksimal 2 MB |
| Super Admin | CRUD akun dan profil mentor/peserta, kelompok, peninjauan pengajuan, monitoring dan laporan |
| Mentor | Absensi/logbook kelompok yang ditugaskan, validasi disetujui/ditolak, laporan kelompoknya |
| Ketua | Absensi pribadi, kirim logbook kelompok, lihat logbook kelompok |
| Peserta | Absensi pribadi, lihat logbook kelompok |

Urutan setup data: **mentor → kelompok dengan mentor dan periode → peserta/ketua dengan kelompok**. Pengajuan diterima tidak otomatis menjadi akun. Admin membuat akun setelah penerimaan dikonfirmasi di luar sistem. Akun dapat dinonaktifkan; setiap request memeriksa status aktif. Akun atau kelompok dengan riwayat terkait tidak dapat dihapus.

## Aturan implementasi versi awal

- Zona waktu **Asia/Jakarta**.
- Absensi hanya untuk hari ini, satu catatan per orang per hari, dalam periode kelompok. Keterangan menyimpan hadir/izin/sakit; validasi adalah status peninjauan, bukan perhitungan otomatis jenis kehadiran.
- Logbook satu per kelompok per tanggal, hanya ketua yang dapat mengirim, tanggal tidak boleh di masa depan dan harus dalam periode magang.
- Satu ketua per kelompok, diperiksa saat menyimpan akun.
- Hanya mentor kelompok terkait yang dapat memvalidasi. Penolakan wajib disertai catatan. Mentor dapat mengubah hasil validasi; pengirim tidak mengedit catatan terkirim pada versi ini. Koreksi dikoordinasikan dengan mentor.
- Riwayat menyimpan kelompok saat catatan dibuat; perpindahan peserta tidak memindahkan riwayat lama.
- Laporan dapat difilter tanggal dan diekspor ke **CSV**, atau dicetak menjadi PDF melalui browser. Ekspor Excel `.xlsx`, generator PDF server, notifikasi, dan audit log belum termasuk.
- Tidak ada pendaftaran akun mandiri, seleksi otomatis, reset password via email, atau email otomatis. Admin mengganti password melalui pengaturan akun.

## Database

`database/migrations` adalah sumber struktur database lengkap dan dapat membangunnya dari kosong. `database/create-database.sql` membuat database MySQL; setelah itu jalankan migrasi. Alternatif impor manual: impor `database/sql/monika-schema.sql` ke database kosong, lalu jalankan `php artisan db:seed`. SQL ini berisi seluruh struktur, empat peran, dan riwayat migrasi; tidak berisi akun atau data pribadi. Pilih migrasi atau impor SQL, bukan keduanya untuk pembuatan struktur awal. `docs/DATABASE.md` memuat relasi dan ERD. `database/seeders/DatabaseSeeder.php` memuat akun awal dan data demo opsional. Tabel Laravel untuk session, cache, dan queue juga disediakan.

Surat disimpan pada `storage/app/private/letters`, tidak pada `public`. Unduhan hanya melalui controller dengan izin admin. Tidak diperlukan `storage:link` untuk surat.

## Pengujian

```bat
php artisan test
```

Default pengujian menggunakan SQLite in-memory; aktifkan `pdo_sqlite`/`sqlite3`. Untuk integrasi MySQL gunakan database khusus pengujian, bukan database operasional:

```bat
mysql -u root -e "CREATE DATABASE monika_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Buat `.env.testing` dari `.env` dan set `APP_ENV=testing`, `DB_DATABASE=monika_testing`, lalu jalankan PHPUnit dengan konfigurasi MySQL:

```bat
php vendor/bin/phpunit --configuration=phpunit.mysql.xml
```

`RefreshDatabase` menghapus dan membangun ulang tabel database pengujian. Lihat `docs/VALIDATION.md` untuk hasil pengujian paket ini.

## Produksi dan backup

Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` HTTPS, `SESSION_SECURE_COOKIE=true`, password database yang kuat, dan `MONIKA_DEMO=false`. Pastikan hanya `public/` yang dilayani web server. Beri izin tulis pada `storage` dan `bootstrap/cache`, lalu jalankan `php artisan optimize` sesudah konfigurasi selesai. Jangan masukkan `.env`, surat pengguna, atau backup ke Git.

Backup database dan surat secara berpasangan, simpan di lokasi privat:

```bat
mysqldump -u root -p --single-transaction monika > monika-backup.sql
```

Salin juga `storage/app/private` dan simpan `APP_KEY` secara aman agar data session terenkripsi tetap konsisten. Pulihkan di database tujuan yang sudah dibuat:

```bat
mysql -u root -p monika < monika-backup.sql
php artisan optimize:clear
```

Pulihkan folder privat dan uji login serta unduhan surat setelah restore. Jangan gunakan `migrate:fresh` pada data operasional.
