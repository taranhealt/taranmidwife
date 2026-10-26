# Penugasan manual oleh admin

`POST /api/v1/admin/bookings/{id}/assign` (ops_admin), body: `partner_id`, opsional `override` (boolean) dan `override_reason` (wajib bila override, maks 200 karakter).

- Mitra harus berstatus ACTIVE dengan akun aktif (selalu diperiksa, tidak bisa di-override).
- Tanpa override, mitra harus lolos `PartnerMatcher`: melayani layanan dan wilayah, tersedia pada hari dan jam, tidak bentrok, belum pernah ditawari booking itu, SIP berlaku sampai tanggal kunjungan, menerima permintaan. Bila tidak: 422 pada `partner_id`.
- Dengan override, pemeriksaan kelayakan dilewati. Alasan dicatat (dipotong 80 karakter) di riwayat status dan kejadian `booking.assign_override` masuk audit log.
- Bentrok slot tetap ditolak 409 `SLOT_UNAVAILABLE` oleh indeks unik, termasuk saat override.

## Catatan
- Keputusan override dan arti masa berlaku SIP adalah keputusan SEMENTARA, menunggu persetujuan pemilik.
- Mitra yang pernah menolak booking itu dianggap tidak layak tanpa override.
