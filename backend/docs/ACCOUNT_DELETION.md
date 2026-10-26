# Penghapusan akun

Endpoint: `DELETE /api/v1/me` dengan body `{"password": "..."}`; maksimal 5 percobaan per menit.

- Hanya pasien dan mitra. Akun staf (ops_admin, reviewer, sysadmin) ditolak dan harus lewat administrator sistem.
- Ditolak (409 ACTIVE_BOOKINGS) bila ada pesanan yang belum COMPLETED/CANCELLED/UNFULFILLED, sebagai pasien maupun mitra.
- Nama, email, telepon, dan kata sandi diganti; status menjadi `deleted`; semua token dicabut.
- Alamat dan telepon pada booking diganti `[DIHAPUS]`; landmark dan alasan pembatalan dikosongkan. Baris booking tetap ada.
- Mitra: NIK, data SIP, wilayah praktik, dan semua dokumen (database dan file) dihapus; status verifikasi menjadi SUSPENDED dengan catatan peristiwa.
- Audit log tetap ada; `actor_id` menunjuk akun yang sudah dianonimkan.
- Migrasi `2026_10_26_000030` menambahkan nilai `deleted` ke constraint `users_status_check`.

## Belum diputuskan (keputusan pemilik) dan NEEDS REVIEW hukum
- Masa retensi: asumsi sementara, data transaksi anonim disimpan tanpa batas waktu.
- Kebutuhan retensi riwayat layanan kesehatan menurut aturan yang berlaku belum ditinjau.
- Belum ada halaman/URL web untuk permintaan hapus akun di luar aplikasi (umumnya diminta Google Play).
- Akun `deleted` tidak dapat dipulihkan lewat API.
