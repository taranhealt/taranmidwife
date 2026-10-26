<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TaranMidwife - Lupa kata sandi</title>
<link rel="stylesheet" href="/css/auth.css">
</head>
<body>
<header><h1>TaranMidwife - Lupa kata sandi</h1></header>
<main>
  <p>Masukkan email akun Anda. Jika terdaftar, kami kirim tautan untuk mengatur ulang kata sandi.</p>
  <div id="msg" class="msg" role="status" hidden></div>
  <form id="form">
    <label for="email">Email</label>
    <input id="email" type="email" autocomplete="username" required>
    <button id="submit" type="submit">Kirim tautan</button>
  </form>
</main>
<footer><a href="/app">Kembali masuk</a> &middot; <a href="/kebijakan-privasi">Kebijakan privasi</a></footer>
<script src="/js/lupa-sandi.js"></script>
</body>
</html>
