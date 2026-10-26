<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TaranMidwife - Atur ulang kata sandi</title>
<link rel="stylesheet" href="/css/auth.css">
</head>
<body>
<header><h1>TaranMidwife - Atur ulang kata sandi</h1></header>
<main>
  <div id="msg" class="msg" role="status" hidden></div>
  <form id="form" hidden>
    <label for="password">Kata sandi baru (minimal 10 karakter)</label>
    <input id="password" type="password" autocomplete="new-password" minlength="10" required>
    <label for="password2">Ulangi kata sandi baru</label>
    <input id="password2" type="password" autocomplete="new-password" minlength="10" required>
    <button id="submit" type="submit">Simpan kata sandi</button>
  </form>
</main>
<footer><a href="/app">Masuk</a> &middot; <a href="/lupa-sandi">Minta tautan baru</a> &middot; <a href="/kebijakan-privasi">Kebijakan privasi</a></footer>
<script src="/js/atur-ulang-sandi.js"></script>
</body>
</html>
