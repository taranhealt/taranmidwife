# DEVELOPMENT STATUS - TaranMidwife

Diperbarui: 26 Okt 2026. Dasar: commit 4d02659 + perubahan blok ini.
Bukti: `php artisan test` di Termux (aarch64), PHP 8.5.1, Laravel 13.38.0, PostgreSQL 18.2: 23 lulus (127 assertion) sebelum perubahan blok ini.
Lingkungan: HANYA lokal. Tidak ada staging/produksi.

| Komponen | Status | Catatan |
|---|---|---|
| Monorepo, backend Laravel | TERUJI (lokal) | |
| Auth Sanctum, peran, rate limit, audit | TERUJI (lokal) | token kedaluwarsa 7 hari |
| Katalog layanan/wilayah | TERUJI (lokal) | data demo hanya via seeder |
| Booking + state machine + penugasan | TERUJI (lokal) | anti double booking, idempotensi, enkripsi alamat |
| Verifikasi mitra, unggah dokumen privat | SEDANG DIKERJAKAN | lihat blok berikutnya |
| Penghapusan akun | BELUM DIMULAI | wajib untuk Google Play |
| Pembayaran | TIDAK DIAKTIFKAN | menunggu keputusan bisnis/hukum |
| Notifikasi | BELUM DIMULAI | |
| Dashboard admin web | BELUM DIMULAI | |
| Aplikasi Flutter pasien dan mitra | BELUM DIMULAI | Flutter belum terpasang di Termux |
| Deployment, HTTPS, backup | BELUM DIMULAI | |
| Kepatuhan hukum/klinis | NEEDS REVIEW | tidak dinyatakan terpenuhi |

## Keputusan (usul Claude, disetujui pemilik)
Flutter + Laravel + PostgreSQL; palet #101D3A; dua aplikasi Android; alur sesuai MVP Spec (pasien mengajukan, admin menugaskan, mitra menerima).

## Asumsi sementara
Slot 2 jam; peran approver = sysadmin; ID tabel users berupa angka.

## Keterbatasan
Tes hanya di PostgreSQL lokal; belum ada uji beban, uji keamanan penetrasi, uji pemulihan backup.
