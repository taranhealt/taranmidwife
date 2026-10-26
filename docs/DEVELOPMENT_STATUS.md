
## Risiko terbuka
- Booking CONFIRMED milik mitra yang kemudian EXPIRED/SUSPENDED belum ditangani (keputusan operasional pemilik).
- Perintah `partners:expire-sip` terdaftar di scheduler tetapi belum ada cron/scheduler yang menjalankannya (butuh deployment).
- Hasil `unverified_limited` (mis. IHS) berarti verifikasi belum penuh; bukan klaim terverifikasi.
