# Pengaduan

Pelapor (patient/partner), maksimal 10 pengajuan per menit:
- `POST /api/v1/complaints` body: `booking_id`, `category`, `description` (10-1000 karakter).
- `GET /api/v1/complaints`, `GET /api/v1/complaints/{id}`: hanya pengaduan milik sendiri.

Kategori: SERVICE_QUALITY, PARTNER_CONDUCT, SCHEDULING, PRICE_OR_PAYMENT, PRIVACY, SAFETY, OTHER.

Aturan:
- Pasien hanya untuk booking miliknya; mitra hanya untuk booking yang pernah ditugaskan kepadanya. Selain itu 404.
- Pengaduan OPEN/IN_REVIEW dengan booking, pelapor, dan kategori yang sama ditolak (409 DUPLICATE_COMPLAINT).
- Deskripsi dan catatan penanganan dienkripsi di database.
- Kategori SAFETY hanya diberi tanda (`safety_flag`) dan dapat difilter admin; tidak ada keputusan atau eskalasi otomatis. Respons mengingatkan untuk menghubungi layanan darurat setempat.
- Pelapor tidak melihat `safety_flag`; catatan penanganan baru terlihat setelah RESOLVED.

Admin (`/api/v1/admin/complaints`):
- Lihat daftar (filter `status`, `safety=1`) dan detail: ops_admin, reviewer, sysadmin. Setiap pembukaan detail dicatat di audit log.
- Tangani (`start-review`, `resolve` dengan catatan wajib, `close`, `reopen`): ops_admin dan reviewer. Sysadmin hanya membaca.

Status: OPEN -> IN_REVIEW -> RESOLVED -> CLOSED; RESOLVED dapat dibuka lagi ke IN_REVIEW. Setiap perubahan tercatat di `complaint_events` dan audit log.

## Belum diputuskan
- Aturan eskalasi SAFETY dan target waktu respons: menunggu protokol klinis (NEEDS REVIEW klinis).
- Retensi pengaduan, termasuk setelah akun dihapus: keputusan pemilik, NEEDS REVIEW hukum.
- Belum ada notifikasi ke pelapor atau admin.
