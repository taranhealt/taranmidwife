# Menjalankan TaranMidwife di lokal (Termux)

- Port 8000 di perangkat ini dipakai aplikasi lain (SIMRS Laravel). JANGAN dipakai untuk TaranMidwife.
- Jalankan (tab Termux terpisah; hentikan dengan Ctrl+C di tab itu):
  `cd ~/taranmidwife/backend && PHP_CLI_SERVER_WORKERS=4 php artisan serve --no-reload --host=127.0.0.1 --port=8010`
- Buka lewat `http://localhost:8010/app`, `/mitra`, `/admin` (bukan 127.0.0.1). Cookie browser tidak membedakan nomor port; nama host `localhost` menjaga cookie TaranMidwife terpisah dari aplikasi lain di 127.0.0.1. GPS browser tetap berfungsi di localhost.
- Jangan memakai `pkill -f "artisan serve"`: perintah itu mematikan SEMUA server artisan, termasuk aplikasi lain.
- Nama cookie sesi sebaiknya dibedakan: tambahkan `SESSION_COOKIE=taranmidwife_session` di `.env` TaranMidwife.
- Test otomatis memakai basis data `taranmidwife_test` dan tidak memakai port mana pun.
