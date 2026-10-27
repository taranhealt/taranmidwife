# Deployment backend TaranMidwife (netral penyedia)

Status: panduan. BELUM ada deployment. Penyedia hosting, domain, dan biaya = keputusan pemilik.

## Prasyarat server
PHP 8.3+ dengan ekstensi pdo_pgsql, mbstring, openssl, tokenizer, xml, ctype, fileinfo; PostgreSQL 16+; Composer; HTTPS (domain resmi) dan web server yang mengarah ke `backend/public`.
Volume penyimpanan PERSISTEN untuk `storage/app/private` (dokumen verifikasi). Tanpa itu dokumen hilang saat redeploy.

## Langkah
1. `composer install --no-dev --optimize-autoloader`
2. Salin `.env.production.example` ke `.env`, isi nilai (JANGAN commit). `php artisan key:generate --force`
3. `php artisan migrate --force` (JANGAN jalankan seeder demo; seeder menolak production)
4. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
5. Cron setiap menit: `* * * * * cd /path/backend && php artisan schedule:run >> /dev/null 2>&1`; cek `php artisan schedule:list`
6. Backup terjadwal: `infrastructure/backup.sh` (harian) dan uji pulih `infrastructure/restore-test.sh` (berkala)

## Uji asap setelah deploy
- `curl -I https://DOMAIN/kebijakan-privasi` dan `/hapus-akun` = 200 (wajib publik tanpa login untuk Google Play)
- `curl https://DOMAIN/api/v1/services` = JSON; header X-Content-Type-Options ada
- `APP_DEBUG=false`: halaman error tidak menampilkan jejak

## Belum tercakup
CSP (peta memakai CDN Leaflet), pemantauan error, WAF, rotasi kredensial, penetration test.
