# Data penawaran mitra dan pencocokan

Mitra (`/api/v1/midwife/offerings`, GET dan PUT; PUT bersifat parsial, kunci yang tidak dikirim tidak diubah):
- `service_ids`, `area_ids`: harus layanan/wilayah aktif.
- `availability`: daftar `{weekday 0-6 (0=Minggu), hours}`; jam hanya slot yang berlaku (08, 10, 12, 14, 16 WIB).
- `accepting_requests`: saklar menerima permintaan.

Mesin pencocokan (`App\Services\PartnerMatcher`). Mitra layak bila:
- akun aktif, verifikasi ACTIVE, SIP belum kedaluwarsa, `accepting_requests` benar;
- melayani layanan dan wilayah booking;
- tersedia pada hari dan jam booking (dihitung WIB dari waktu tersimpan);
- tidak punya penugasan aktif pada slot yang sama;
- belum pernah ditugaskan/ditawari booking itu (termasuk yang menolak).
Urutan: `last_offered_at` terlama dahulu (yang belum pernah ditawari lebih dulu), lalu id.

Admin: `GET /api/v1/admin/bookings/{id}/candidates` (ops_admin) untuk booking REQUESTED atau WAITING_MIDWIFE; dicatat di audit log.

## Belum ada / catatan
- Terhubung ke alur booking lewat OfferDispatcher (lihat `docs/AUTO_OFFER.md`); `last_offered_at` diperbarui saat penawaran.
- Tidak memakai lokasi GPS; hanya wilayah, layanan, jadwal, dan pemerataan.
- Penugasan manual (`assign`) belum memeriksa kelayakan seperti di atas.
- Akun mitra yang dihapus berstatus SUSPENDED, jadi tidak lolos pencocokan; baris penawarannya belum dibersihkan.
