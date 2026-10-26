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
- Halaman web permintaan hapus akun: `/hapus-akun` (DRAF, belum diuji di browser, URL publik menunggu deployment). Tinjauan hukum atas teks dan retensi tetap NEEDS REVIEW.
- Akun `deleted` tidak dapat dipulihkan lewat API.

## Koordinat lokasi (26 Okt 2026)
- Pasien: `bookings.patient_lat` dan `patient_lng` pada semua booking pasien diisi NULL saat akun dihapus.
- Mitra: `base_lat`, `base_lng`, `service_radius_km` diisi NULL dan `accepting_requests` menjadi false.
