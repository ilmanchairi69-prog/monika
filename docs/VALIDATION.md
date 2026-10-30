# Hasil validasi

Validasi dilakukan pada 30 Oktober 2026 dengan PHP 8.2.35, Laravel 12.70.2, MySQL 8.0.46, dan Chromium pada Linux.

- PHPUnit SQLite in-memory: **9 tes lulus, 82 assertion**.
- PHPUnit pada database MySQL khusus pengujian: **9 tes lulus, 82 assertion**.
- Pemeriksaan sintaks PHP: seluruh file aplikasi, route, migrasi, seeder, dan tes lulus.
- Composer validate --strict: lulus; dependensi dikunci untuk PHP 8.2.
- Kompilasi seluruh Blade view: lulus.
- Browser: beranda, login admin, dashboard, pengguna, kelompok, pengajuan, dan laporan berhasil dimuat.
- Tampilan dashboard desktop 1440 px dan ponsel 390 px diperiksa; navigasi ponsel bekerja dan tidak ada overflow horizontal atau error JavaScript.
- Request POST tanpa token CSRF ditolak dengan HTTP 419.
- SQL struktur berhasil diimpor ke database MySQL kosong; seeder berhasil dan seluruh migrasi terbaca sudah dijalankan.

Tes mencakup pembatasan akses admin, data peserta pribadi, isolasi kelompok mentor, pengajuan tanpa pembuatan akun otomatis, unduhan surat privat, validasi file, absensi ganda, pengiriman logbook oleh ketua, validasi mentor, CRUD akun/kelompok, perlindungan akun sendiri, dan penonaktifan akun.

Laragon pada Windows belum dijalankan langsung di lingkungan ini. Panduan Laragon menggunakan perintah Laravel/MySQL standar. Pengujian beban, audit keamanan independen, dan integrasi layanan eksternal tidak dilakukan.
