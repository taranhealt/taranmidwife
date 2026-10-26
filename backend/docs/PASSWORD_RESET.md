# Lupa dan atur ulang kata sandi

- `POST /api/v1/auth/forgot-password` (publik, 5 per menit): body `email`. Selalu menjawab 200 dengan pesan yang sama, baik email terdaftar maupun tidak. Email hanya dikirim ke akun berstatus `active`.
- `POST /api/v1/auth/reset-password` (publik, 5 per menit): body `email`, `token`, `password` (10 sampai 100 karakter), `password_confirmation`. Berhasil: 200, kata sandi diganti, semua token login akun dicabut. Gagal (token salah, kedaluwarsa, sudah dipakai, atau akun tidak aktif): 422 `INVALID_RESET_TOKEN`.
- Token memakai pembuat token bawaan Laravel (tabel `password_reset_tokens`, berlaku sesuai `auth.passwords.users.expire`, bawaan 60 menit, sekali pakai).
- Halaman web: `/lupa-sandi` dan `/atur-ulang-sandi` (tautan di email mengarah ke sini).
- Audit: `auth.password_reset_request` dan `auth.password_reset`. Alamat email tidak dicatat di audit.

## Uji lokal
- `MAIL_MAILER=log`: email tidak benar-benar terkirim. Khusus env `local`, tautan ditulis ke `storage/logs/laravel.log` dengan awalan `[DEV-LOKAL]`:
  `grep 'DEV-LOKAL' storage/logs/laravel.log | tail -1`
- Penulisan tautan ke log hanya aktif di env `local` dan harus tetap begitu. Jangan dipakai di staging atau produksi.

## Belum ada
- Penyedia email untuk staging dan produksi. Pengirim saat ini `hello@example.com` (bawaan).
- Notifikasi ke pemilik akun bahwa kata sandinya berubah.
