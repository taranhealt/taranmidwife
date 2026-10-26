# TaranMidwife

Monorepo PT Taran Rainmaker Group. Status aktual: lihat `docs/DEVELOPMENT_STATUS.md`.

- `backend/` Laravel REST API (PostgreSQL)
- `apps/` aplikasi Flutter pasien dan mitra (belum dibuat)
- `docs/` status, matriks peran, status pesanan

## Menjalankan backend (Termux)
1. `pkg install php php-pgsql composer postgresql`
2. Inisialisasi dan jalankan PostgreSQL, lalu buat role dan database `taranmidwife` dan `taranmidwife_test`.
3. `cd backend && cp .env.example .env && php artisan key:generate`, isi `DB_*` di `.env` (jangan commit).
4. `cp .env .env.testing`, ubah `DB_DATABASE=taranmidwife_test`, `APP_ENV=testing`, `CACHE_STORE=array`.
5. `php artisan migrate && php artisan serve`
6. Tes: `php artisan test`
7. Data demo sintetis (hanya lokal): `php artisan db:seed --class=DevDemoSeeder` (kredensial ditulis ke `storage/app/private/demo-credentials.txt`).
