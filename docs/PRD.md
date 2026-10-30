# Product Requirements Document (PRD)

## MONIKA — Monitoring Anak Magang

DOKUMEN KEBUTUHAN PRODUK

# MONIKA

Sistem Informasi Monitoring dan Pengelolaan Magang

Versi 1.0 — Draft

30 Oktober 2026

Dokumen PRD ini disusun berdasarkan dua sketsa alur sistem, file HTML MONIKA, dan keputusan pengembangan menggunakan Laravel 12, PHP 8.2, MySQL, serta Laragon.

## 1. Ringkasan Produk

MONIKA adalah aplikasi web yang dirancang untuk mendukung pengelolaan peserta magang, mulai dari pengajuan, pengelolaan peserta dan mentor, pembagian kelompok, pencatatan kehadiran, pengisian logbook, hingga monitoring kegiatan oleh mentor dan Super Admin.

Tujuan utama: menggantikan proses pencatatan magang yang tersebar menjadi satu sistem terpusat, terstruktur, dan mudah dipantau.

## 2. Pengguna dan Hak Akses

| Pengguna       | Tanggung jawab                                               |
| -------------- | ------------------------------------------------------------ |
| Pengunjung     | Melihat informasi dan mengajukan magang                      |
| Super Admin    | Mengelola pengguna, peserta, mentor, kelompok, dan pengajuan |
| Mentor         | Memantau serta memvalidasi kegiatan magang                   |
| Ketua Kelompok | Mengelola logbook kegiatan kelompok                          |
| Peserta Magang | Melakukan absensi dan melihat kegiatan magang                |

## 3. Kebutuhan Fungsional

| ID    | Modul       | Kebutuhan                                    |
| ----- | ----------- | -------------------------------------------- |
| FR-01 | Beranda     | Menampilkan informasi MONIKA dan navigasi    |
| FR-02 | Pengajuan   | Formulir pendaftaran magang dengan surat PDF |
| FR-03 | Autentikasi | Login, logout, dan akses berdasarkan peran   |
| FR-04 | Super Admin | CRUD data peserta, mentor, dan kelompok      |
| FR-05 | Pengajuan   | Melihat dan mencatat status pengajuan        |
| FR-06 | Absensi     | Peserta mengisi kehadiran pribadi            |
| FR-07 | Logbook     | Ketua mencatat kegiatan kelompok             |
| FR-08 | Validasi    | Mentor memeriksa absensi dan logbook         |
| FR-09 | Monitoring  | Dashboard ringkasan data dan aktivitas       |
| FR-10 | Laporan     | Rekapitulasi aktivitas dan kehadiran         |

## 4. Alur Bisnis Utama

Alur Pengajuan Magang

Pengunjung membuka MONIKA

Mengisi formulir pengajuan

Mengunggah surat permohonan

Pengajuan tersimpan di MySQL

Super Admin meninjau pengajuan

Proses penerimaan di luar sistem

Super Admin membuat akun peserta

Proses pengajuan tidak otomatis mengaktifkan akun peserta. Pembuatan akun dilakukan oleh Super Admin setelah penerimaan dipastikan.

## 5. Kebutuhan Nonfungsional

- Antarmuka responsif untuk desktop dan ponsel.
- Password disimpan menggunakan hashing.
- Hak akses diperiksa pada backend Laravel.
- Validasi formulir dan pembatasan jenis serta ukuran file.
- Data disimpan secara persisten di MySQL.
- Proteksi CSRF, session, dan validasi input.
- Penyimpanan surat pengajuan tidak dapat diakses publik tanpa otorisasi.
- Kemampuan pencadangan dan pemulihan database.

## 6. Rancangan Database

Entitas utama yang dibutuhkan:

| Tabel              | Fungsi                    |
| ------------------ | ------------------------- |
| `users`            | Akun dan autentikasi      |
| `roles`            | Jenis hak akses           |
| `mentors`          | Profil mentor             |
| `peserta_magang`   | Profil peserta            |
| `kelompok`         | Data kelompok magang      |
| `pengajuan_magang` | Data calon pendaftar      |
| `absensi`          | Catatan kehadiran         |
| `logbooks`         | Catatan kegiatan kelompok |

Struktur ini merupakan rancangan awal. Detail kolom, relasi, dan constraint perlu ditetapkan pada tahap desain teknis.

## 7. Prioritas Pengembangan

| Tahap   | Cakupan                                      | Prioritas |
| ------- | -------------------------------------------- | --------- |
| MVP     | Login, pengajuan, CRUD pengguna dan kelompok | P0        |
| Tahap 2 | Absensi, logbook, validasi mentor            | P0        |
| Tahap 3 | Dashboard statistik dan laporan              | P1        |
| Tahap 4 | Ekspor PDF/Excel, notifikasi, audit log      | P2        |

## 8. Kriteria Penerimaan

MONIKA dinyatakan memenuhi kebutuhan MVP apabila:

- Pengunjung dapat mengirim pengajuan yang valid.
- Super Admin dapat melihat pengajuan dan mengelola peserta, mentor, serta kelompok.
- Pengguna hanya dapat mengakses fitur sesuai perannya.
- Peserta dapat mencatat kehadiran.
- Ketua Kelompok dapat mengirim logbook.
- Mentor dapat memvalidasi data yang menjadi tanggung jawabnya.
- Data tetap tersimpan setelah browser ditutup atau server dijalankan ulang.

## 9. Batasan Ruang Lingkup

Pada versi awal, MONIKA tidak mencakup pembayaran, seleksi otomatis calon peserta, integrasi sistem akademik, maupun pengiriman email otomatis.

Keputusan penerimaan magang dilakukan di luar sistem. MONIKA berfungsi sebagai sarana administrasi dan monitoring setelah data dicatat oleh pengguna yang berwenang.

## 10. Indikator Keberhasilan

Target awal yang diusulkan untuk evaluasi setelah implementasi:

- 100% peserta aktif memiliki akun dan data kelompok yang sesuai.
- Minimal 90% absensi tercatat melalui MONIKA.
- Minimal 90% logbook kegiatan terisi sesuai jadwal.
- Mentor dapat melihat dan memvalidasi kegiatan kelompoknya.
- Super Admin dapat memperoleh rekap data tanpa menggabungkan catatan secara manual.

## 11. Keputusan yang Masih Perlu Ditetapkan

Sebelum PRD menjadi acuan implementasi final, perlu dipastikan aturan absensi (harian atau per sesi), mekanisme koreksi absensi, format dan frekuensi logbook, batas waktu validasi mentor, serta jenis laporan yang dibutuhkan.

Status PRD: Draft v1.0 — siap digunakan sebagai dasar pengembangan dan pembahasan kebutuhan, tetapi belum menjadi spesifikasi final yang disetujui pemangku kepentingan.

