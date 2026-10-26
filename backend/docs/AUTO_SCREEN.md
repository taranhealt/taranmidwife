# Saring otomatis

- Sakelar: `TARAN_AUTO_SCREEN` di `.env` (bawaan `false`, dibaca di `config/taran.php`).
- Bila `true`: setelah booking baru berhasil disimpan (setelah transaksi commit), `AutoScreener` mengubah status REQUESTED menjadi WAITING_MIDWIFE dan memanggil `OfferDispatcher::offerNext`. Hasilnya sama dengan admin menekan `POST /admin/bookings/{id}/screen`, tanpa pemeriksaan manual.
- Tanpa kandidat: booking tetap WAITING_MIDWIFE dan ditangani sapuan penawaran yang sudah ada.
- Bila proses otomatis gagal: kesalahan dicatat di log (`auto_screen_failed`), booking tetap REQUESTED, dan admin dapat menyaring manual seperti biasa. Pembuatan booking tidak ikut gagal.
- Audit: `booking.auto_screen`.
- Respons `POST /bookings` dapat masih menampilkan status REQUESTED; status terbaru ada pada `GET /bookings/{id}`. Belum diuji di sisi klien.
- NEEDS REVIEW: sakelar melewati pemeriksaan administratif. Jangan dinyalakan sebelum pemilik memutuskan, termasuk kebutuhan penyaringan klinis.
