# Penawaran otomatis ke mitra

Komponen: `App\Services\OfferDispatcher` (memakai `PartnerMatcher`), perintah `bookings:expire-offers` (terjadwal tiap menit), kolom `assignments.auto_offered` dan `assignments.offer_expires_at`.

Alur:
- Admin menyaring booking (`screen`): booking menjadi WAITING_MIDWIFE, lalu sistem menawarkan ke kandidat teratas (status OFFERED, batas waktu 10 menit). Tanpa kandidat, booking tetap WAITING_MIDWIFE.
- Mitra menolak: penugasan dilepas, sistem menawarkan ke kandidat berikutnya. Bila kandidat habis dan tawaran yang ditolak adalah tawaran otomatis, status menjadi UNFULFILLED. Tawaran manual dari admin yang ditolak kembali ke WAITING_MIDWIFE (perilaku lama).
- `bookings:expire-offers`: tawaran otomatis yang lewat batas diperlakukan seperti penolakan. Lalu booking WAITING_MIDWIFE tanpa penugasan aktif ditawarkan ulang; bila waktu layanan sudah lewat menjadi UNFULFILLED.
- Setiap penawaran memperbarui `last_offered_at` mitra (pemerataan) dan dicatat di audit log (`booking.auto_offer`). Mitra yang sudah ditawari booking itu tidak ditawari lagi.

## Belum ada / catatan
- Batas 10 menit adalah ASUMSI sementara, belum keputusan pemilik.
- Kedaluwarsa hanya diproses oleh perintah terjadwal. Cron untuk `schedule:run` belum ada, jadi di luar test hal ini belum terbukti berjalan. Menerima tawaran yang sudah lewat batas ditolak (409 OFFER_EXPIRED) walau perintah belum berjalan.
- Tidak ada notifikasi ke mitra; mitra harus membuka aplikasi.
- Tindakan sistem tercatat dengan actor kosong (null).
- Booking WAITING_MIDWIFE yang dikelola manual juga ikut disapu perintah ini.
