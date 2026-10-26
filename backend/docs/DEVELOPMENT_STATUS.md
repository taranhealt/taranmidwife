# DEVELOPMENT STATUS - TaranMidwife

Diperbarui: 26 Okt 2026. Dasar: commit 6f0f7ae (34 test lulus).
Bukti: `php artisan test` di Termux (aarch64), PHP 8.5.1, Laravel 13.38.0, PostgreSQL 18.2: 94 passed (533 assertions), exit=0 (setelah migrate:fresh).
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
| Data penawaran mitra (layanan, wilayah, jadwal) dan mesin pencocokan | TERUJI (lokal) | terhubung ke alur booking lewat penawaran otomatis |
| Penawaran otomatis ke mitra layak, penolakan/kedaluwarsa berpindah ke mitra berikutnya, perintah bookings:expire-offers | TERUJI (lokal) | batas 10 menit adalah asumsi; jadwal otomatis baru terbukti di staging (belum ada cron); tanpa notifikasi |
| Lokasi GPS pasien, titik dan radius layanan mitra, pencocokan terdekat, cek jangkauan, batas area pilot | TERUJI (lokal) | batas area pilot adalah PERKIRAAN, belum diverifikasi; UI peta belum dibuat; pelacakan mitra langsung belum ada |
| Saring otomatis booking baru (sakelar TARAN_AUTO_SCREEN, bawaan MATI), koordinat ikut dianonimkan saat hapus akun, seeder demo mitra layak | TERUJI (lokal) | sakelar menggantikan pemeriksaan administratif admin: NEEDS REVIEW sebelum dinyalakan; titik layanan demo memakai titik tengah area pilot |
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
- Belum dibangun: notifikasi ke mitra, profil publik bidan, pencarian bidan oleh pasien.
- Zona waktu sesi database semula bukan UTC, sehingga waktu booking (disimpan sebagai UTC) berisiko bergeser. Koneksi pgsql kini diset `timezone => UTC`. Data lokal lama dibuat ulang lewat migrate:fresh; pastikan server staging/produksi memakai pengaturan yang sama.

## Penawaran otomatis (26 Okt 2026)
- Saat admin menyaring booking, sistem menawarkan ke kandidat teratas (`OfferDispatcher`). Tanpa kandidat, status tetap WAITING_MIDWIFE. Detail: `docs/AUTO_OFFER.md`.

## Tindak lanjut penawaran (26 Okt 2026)
- TERUJI (lokal): `accept` menolak tawaran otomatis yang sudah lewat batas (409 OFFER_EXPIRED), walau perintah kedaluwarsa belum berjalan.

## Penugasan manual dan SIP (26 Okt 2026)
- TERUJI (lokal): `assign` manual memeriksa kelayakan mitra (layanan, wilayah, jadwal, ketersediaan, SIP). Mitra tidak layak ditolak 422, kecuali admin mengirim `override: true` dan `override_reason`; override dicatat di audit (`booking.assign_override`) dan riwayat status. Detail: `docs/MANUAL_ASSIGN.md`.
- TERUJI (lokal): mitra melihat tawaran (OFFERED) di daftar booking tanpa alamat dan telepon; kontak baru terbuka setelah CONFIRMED.
- TERUJI (lokal): pencocokan menerima mitra yang SIP-nya berlaku sampai dan termasuk tanggal kunjungan (WIB) dan menolak yang habis sebelumnya.
- TERUJI (lokal): `partners:expire-sip` memakai tanggal WIB; SIP yang berakhir hari ini tetap ACTIVE, yang berakhir kemarin menjadi EXPIRED.
- KEPUTUSAN SEMENTARA, belum disetujui pemilik: opsi override untuk `assign`; arti masa berlaku SIP (sampai dan termasuk tanggal berakhir).
- Sembilan pemanggilan `assign` di `BookingFlowTest` kini memakai override, karena mitra di test itu tidak punya layanan, wilayah, atau jadwal.

## Lokasi GPS (26 Okt 2026)
- TERUJI (lokal): booking dapat menyertakan `latitude` dan `longitude` (terenkripsi); di luar area pilot ditolak 422 `OUT_OF_COVERAGE`; endpoint `GET /api/v1/coverage` memberi tahu apakah lokasi di area pilot dan berapa mitra aktif di sekitarnya. Detail: `docs/GPS_LOCATION.md`.
- TERUJI (lokal): mitra mengatur titik dan radius layanan (`service_point`); pencocokan dengan GPS hanya memilih mitra yang radiusnya mencakup pasien, terdekat lebih dulu; mitra melihat jarak perkiraan, bukan koordinat.
- ASUMSI sementara: titik tengah dan radius area pilot (Kabupaten Batu Bara) adalah perkiraan kasar saya, BELUM DIVERIFIKASI. Ganti lewat `TARAN_PILOT_LAT`, `TARAN_PILOT_LNG`, `TARAN_PILOT_RADIUS_KM` di `.env`.
- Belum dibuat: UI peta dan pin, pelacakan posisi mitra langsung (EN_ROUTE), daftar tunggu untuk area di luar jangkauan.
- NEEDS REVIEW (hukum): data lokasi pasien dan mitra; inventaris Data safety sudah ditambah.

## Blok 5 (26 Okt 2026)
- TERUJI (lokal): `DELETE /api/v1/me` menghapus `patient_lat` dan `patient_lng` pada semua booking pasien; untuk mitra menghapus titik dan radius layanan serta mematikan `accepting_requests`. Temuan di `DATA_SAFETY_INVENTORY.md` sudah ditutup.
- TERUJI (lokal): sakelar `TARAN_AUTO_SCREEN` (bawaan false). Bila true, booking baru yang sudah tersimpan otomatis menjadi WAITING_MIDWIFE lalu ditawarkan ke mitra terdekat. Detail: `docs/AUTO_SCREEN.md`.
- TERUJI (lokal): `DevDemoSeeder` membuat mitra demo yang layak (aktif, SIP berlaku, semua layanan demo, wilayah Sei Suka, jadwal penuh, titik layanan di titik tengah area pilot, radius 15 km) dan aman dijalankan berulang.
- NEEDS REVIEW: apakah pemeriksaan administratif boleh dilewati (keputusan pemilik dan klinis) sebelum sakelar dinyalakan.
