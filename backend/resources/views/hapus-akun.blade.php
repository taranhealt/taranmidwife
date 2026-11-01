<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TaranMidwife - Hapus akun</title>
<link rel="stylesheet" href="/css/patient.css">
</head>
<body>
<header>
  <h1>TaranMidwife - Hapus akun</h1>
  <button id="logout" class="link" type="button" hidden>Keluar</button>
</header>
<main>
  <div id="msg" class="msg" role="status" hidden></div>

  <section id="info">
    <h2>Sebelum Anda menghapus akun</h2>
    <p>Halaman ini untuk pasien dan mitra TaranMidwife yang ingin menghapus akunnya. Penghapusan bersifat permanen dan tidak dapat dibatalkan.</p>
    <ul>
      <li>Nama, email, nomor telepon, dan kata sandi akun diganti sehingga tidak lagi mengarah ke Anda.</li>
      <li>Alamat, telepon, dan koordinat pada pesanan lama dihapus. Catatan pesanan tetap tersimpan tanpa identitas.</li>
      <li>Untuk mitra: data profesi dan dokumen verifikasi dihapus, dan akun tidak dapat menerima tawaran lagi.</li>
      <li>Isi teks bebas pengaduan yang Anda laporkan diganti dengan <code>[DIHAPUS]</code>. Metadata dan riwayat penanganan pengaduan dapat tetap disimpan untuk audit; masa retensinya masih memerlukan tinjauan hukum.</li>
      <li>Catatan audit sistem tetap ada.</li>
      <li>Akun tidak dapat dipulihkan.</li>
      <li>Akun tidak dapat dihapus selama masih ada pesanan yang belum selesai atau dibatalkan.</li>
    </ul>
    <p class="note">Akun staf operasional tidak dapat dihapus dari halaman ini.</p>
  </section>

  <section id="auth">
    <h2>Masuk untuk melanjutkan</h2>
    <form id="login-form">
      <label for="l-email">Email</label>
      <input id="l-email" type="email" autocomplete="username" required>
      <label for="l-pass">Kata sandi</label>
      <input id="l-pass" type="password" autocomplete="current-password" required>
      <div class="row"><button type="submit">Masuk</button></div>
    </form>
  </section>

  <section id="confirm" hidden>
    <h2>Konfirmasi penghapusan</h2>
    <p id="who"></p>
    <form id="delete-form">
      <label for="d-pass">Masukkan kata sandi sekali lagi</label>
      <input id="d-pass" type="password" autocomplete="current-password" required>
      <label class="chk"><input id="d-ok" type="checkbox"><span>Saya mengerti bahwa penghapusan ini permanen.</span></label>
      <div class="row"><button id="d-btn" type="submit" disabled>Hapus akun saya</button></div>
    </form>
  </section>

  <section id="done" hidden>
    <h2>Akun sudah dihapus</h2>
    <p>Akun Anda telah dihapus dan semua sesi masuk dicabut. Terima kasih telah menggunakan TaranMidwife.</p>
  </section>
</main>
<script src="/js/hapus-akun.js"></script>
</body>
</html>
