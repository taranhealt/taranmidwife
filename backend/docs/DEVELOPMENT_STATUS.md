# DEVELOPMENT STATUS - TaranMidwife

Diperbarui: 26 Okt 2026. Dasar: commit 6f0f7ae (34 test lulus).
Bukti: `php artisan test` di Termux (aarch64), PHP 8.5.1, Laravel 13.38.0, PostgreSQL 18.2: 58 passed (401 assertions), exit=0 (setelah migrate:fresh).
Lingkungan: HANYA lokal. Tidak ada staging/produksi.

| Komponen | Status | Catatan |
|---|---|---|
| Monorepo, backend Laravel | TERUJI (lokal) | |
| Auth Sanctum, peran, rate limit, audit | TERUJI (lokal) | token kedaluwarsa 7 hari |
| Katalog layanan/wilayah, seeder demo sintetis | TERUJI (lokal) | seeder menolak produksi |
| Booking + state machine + penugasan | TERUJI (lokal) | anti double booking, idempotensi, enkripsi alamat |
| Verifikasi mitra, dokumen privat, kedaluwarsa SIP | TERUJI (lokal) | 5 pemeriksaan wajib, persetujuan hanya sysadmin, tanpa auto-approval |
| Penghapusan akun (anonimisasi) | TERUJI (lokal) | hanya pasien/mitra; ditolak bila ada pesanan aktif; masa retensi = keputusan pemilik, NEEDS REVIEW hukum |
| Pengaduan (pasien/mitra) dan penanganan admin | TERUJI (lokal) | teks terenkripsi; kategori SAFETY hanya ditandai, aturan eskalasi NEEDS REVIEW klinis |
| Data penawaran mitra (layanan, wilayah, jadwal) dan mesin pencocokan | TERUJI (lokal) | belum terhubung ke alur booking; penawaran otomatis belum dibangun |
| Inventaris Data safety | DRAF | dari skema aktual; belum final, NEEDS REVIEW hukum; sisi aplikasi belum ada |
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

## Risiko terbuka
- Booking CONFIRMED milik mitra yang kemudian EXPIRED/SUSPENDED belum ditangani (keputusan operasional pemilik).
- Perintah `partners:expire-sip` terdaftar di scheduler, tetapi belum ada cron/scheduler yang menjalankannya (butuh deployment).
- Hasil `unverified_limited` (mis. IHS) berarti verifikasi belum penuh, bukan klaim terverifikasi.
- Aturan eskalasi pengaduan keselamatan (kategori SAFETY) belum ada; menunggu protokol klinis. Saat ini hanya ditandai dan dapat difilter admin.
- Pengaduan tetap tersimpan setelah akun pelapor dihapus; teks bebasnya bisa memuat data pribadi dan belum punya aturan retensi (keputusan pemilik, NEEDS REVIEW hukum).

## Keputusan pemilik (26 Okt 2026)
- Model penugasan: hibrida. Pasien mengajukan; admin menyaring (sementara, sampai protokol klinis ada); sistem menawarkan otomatis ke satu mitra layak per waktu, mirip aplikasi ojek online; admin tetap dapat menugaskan manual. Dokumen konteks bagian 9 menggambarkan pasien memilih bidan; perbedaan ini belum direkonsiliasi penuh.
- ASUMSI sementara (belum diputuskan): batas waktu mitra menjawab tawaran 10 menit; pemilihan mitra tanpa GPS (wilayah, layanan, jadwal, pemerataan).
- Belum dibangun: penawaran bergilir otomatis, kedaluwarsa tawaran (butuh scheduler berjalan), notifikasi ke mitra, profil publik bidan, pencarian bidan oleh pasien.
- Temuan: penugasan manual admin (`assign`) hanya memeriksa status mitra, belum memeriksa layanan, wilayah, atau jadwal. Inventaris Data safety belum memuat tabel penawaran mitra (perbarui).
- Zona waktu sesi database semula bukan UTC, sehingga waktu booking (disimpan sebagai UTC) berisiko bergeser. Koneksi pgsql kini diset `timezone => UTC`. Data lokal lama dibuat ulang lewat migrate:fresh; pastikan server staging/produksi memakai pengaturan yang sama.
