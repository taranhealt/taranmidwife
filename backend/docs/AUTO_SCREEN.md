# Saring otomatis

- Sakelar: `TARAN_AUTO_SCREEN` di `.env` (bawaan `false`, dibaca di `config/taran.php`).
- Bila `true`: setelah booking baru berhasil disimpan (dipanggil dari `BookingController::store` setelah riwayat REQUESTED ditulis), `AutoScreener` mengubah status REQUESTED menjadi WAITING_MIDWIFE dan memanggil `OfferDispatcher::offerNext`. Hasilnya sama dengan admin menekan `POST /admin/bookings/{id}/screen`, tanpa pemeriksaan manual.
- Tanpa kandidat: booking tetap WAITING_MIDWIFE dan ditangani sapuan penawaran yang sudah ada.
- Bila proses otomatis gagal: kesalahan dicatat di log (`auto_screen_failed`), booking tetap REQUESTED, dan admin dapat menyaring manual seperti biasa. Pembuatan booking tidak ikut gagal.
- Audit: `booking.auto_screen`.
- Respons `POST /bookings` memuat status terbaru (setelah saring otomatis); pemicunya ada di `BookingController::store`, setelah riwayat REQUESTED ditulis.
- NEEDS REVIEW: sakelar melewati pemeriksaan administratif. Jangan dinyalakan sebelum pemilik memutuskan, termasuk kebutuhan penyaringan klinis.
