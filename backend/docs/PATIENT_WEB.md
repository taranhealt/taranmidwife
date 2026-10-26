# UI Pasien (web, DRAF)

- Alamat: `/app` (dilayani Laravel). Berkas: `resources/views/patient.blade.php`, `public/js/patient.js`, `public/css/patient.css`. Tanpa build (tanpa Node/Vite).
- Fitur: masuk dan daftar, pilih layanan dan wilayah, tanggal dan slot (08, 10, 12, 14, 16 WIB), alamat dan telepon, lokasi (GPS, ketuk peta, geser pin, koordinat manual), cek jangkauan (`GET /coverage`), kirim pesanan dengan `Idempotency-Key` (kunci dipakai ulang bila jaringan gagal), daftar pesanan (muat ulang otomatis tiap 30 detik saat tab terlihat), batalkan pesanan.
- GPS browser hanya berfungsi di `http://127.0.0.1` atau `localhost` pada ponsel yang sama, atau lewat HTTPS. Alamat IP jaringan lokal tanpa HTTPS ditolak browser.
- Token masuk disimpan di `sessionStorage` (hilang saat tab ditutup). Semua data dari server ditampilkan lewat `textContent`, bukan HTML.
- Pihak ketiga: peta memuat Leaflet 1.9.4 dari `unpkg.com` (tanpa SRI) dan tile dari `tile.openstreetmap.org`. Permintaan tile mengungkap area peta yang dilihat dan alamat IP pengguna kepada OpenStreetMap. Untuk produksi: hosting Leaflet sendiri, SRI, dan penyedia tile yang sesuai kebijakan penggunaan.
- BELUM TERUJI: interaksi di browser, bentuk permintaan daftar dan masuk terhadap `AuthController` (UI mengirim `name`, `email`, `password`, `password_confirmation`; `email` dan `password` untuk masuk; token dicari di `token`, `data.token`, `data.access_token`, `access_token`), tampilan di berbagai ukuran layar, aksesibilitas.
- NEEDS REVIEW (klinis/hukum): teks pemberitahuan darurat dan keterangan layanan; persetujuan penggunaan lokasi.

## Pembaruan (26 Okt 2026)
- Formulir daftar kini meminta kata sandi minimal 10 karakter (sesuai server, diuji).
- Bentuk permintaan daftar, masuk, dan token sudah dicocokkan dengan kode `AuthController` (dibaca, bukan diuji dari browser): token ada di `token` tingkat atas.
