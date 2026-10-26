# UI Admin (`/admin`)

Halaman web untuk staf, memakai API yang sudah ada. Berkas: `resources/views/admin.blade.php`, `public/js/admin.js`, `public/css/admin.css` (memakai juga `patient.css`). Tanpa sumber luar.

## Login dan peran
- Masuk lewat `POST /api/v1/auth/login`. Akun pasien dan mitra ditolak di halaman ini (token langsung dicabut).
- Token disimpan di `sessionStorage` (hilang saat tab ditutup).
- Tab menurut peran:
  - ops_admin: Pesanan, Pengaduan.
  - reviewer: Mitra, Pengaduan.
  - sysadmin: Mitra, Pesanan (hanya lihat), Pengaduan.

## Pesanan (ops_admin)
- Filter status (bawaan: menunggu pemeriksaan), maksimal 50 terbaru.
- Saring, lihat kandidat (nama, jarak, terakhir ditawari), tugaskan dari daftar kandidat, batalkan dengan alasan.
- Override penugasan untuk mitra yang tidak layak belum ada di UI (hanya API; lihat `docs/MANUAL_ASSIGN.md`).

## Mitra (reviewer dan sysadmin)
- Daftar dengan filter status; detail memuat data profil (NIK disamarkan), dokumen, dan pemeriksaan.
- Dokumen dibuka lewat permintaan terautentikasi; setiap pembukaan tercatat di audit. Foto tampil di halaman, PDF diunduh.
- Lima pemeriksaan wajib dicatat pada status IN_REVIEW (hasil dan cara pemeriksaan wajib). Butir bertanda "tidak dapat diverifikasi penuh" tampil sebagai keterbatasan.
- Tindakan menurut peran dan status: mulai tinjau dan minta perbaikan (reviewer, sysadmin); setujui, tolak, aktifkan, tangguhkan (hanya sysadmin). Server tetap penentu transisi yang sah.

## Pengaduan
- Belum ada di UI. API sudah tersedia (`/api/v1/admin/complaints`).

## Uji manual (ponsel)
- Jalankan server di port 8010 dan buka `http://localhost:8010/admin` (lihat `docs/LOCAL_RUN.md`; port 8000 bukan milik TaranMidwife).
- Akun demo: `ops.demo@example.test`, `reviewer.demo@example.test`, `sysadmin.demo@example.test`. Kata sandi ada di `storage/app/private/demo-credentials.txt` (jangan dibagikan).
- Bila login ditolak karena data demo hilang: `php artisan db:seed --class=DevDemoSeeder` (membuat kata sandi baru di berkas yang sama).
