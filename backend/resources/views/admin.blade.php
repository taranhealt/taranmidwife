<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TaranMidwife - Admin</title>
<link rel="stylesheet" href="/css/patient.css">
<link rel="stylesheet" href="/css/admin.css">
</head>
<body>
<header>
  <h1>TaranMidwife Admin</h1>
  <button id="logout" class="link" type="button" hidden>Keluar</button>
</header>
<main>
  <div id="msg" class="msg" role="status" hidden></div>

  <section id="auth">
    <h2>Masuk sebagai staf</h2>
    <form id="login-form">
      <label for="l-email">Email</label>
      <input id="l-email" type="email" autocomplete="username" required>
      <label for="l-pass">Kata sandi</label>
      <input id="l-pass" type="password" autocomplete="current-password" required>
      <div class="row"><button type="submit">Masuk</button></div>
    </form>
    <p class="note">Akun staf dibuat oleh administrator sistem. Pasien memakai halaman /app dan mitra memakai /mitra. <a href="/lupa-sandi">Lupa kata sandi</a></p>
  </section>

  <div id="app" hidden>
    <p id="who" class="note"></p>
    <section id="metrics" aria-label="Ringkasan dashboard" class="row">
      <p class="note">Memuat ringkasan...</p>
    </section>
    <div id="tabs" class="tabs"></div>

    <section id="p-orders" hidden>
      <h2>Pesanan</h2>
      <label for="o-status">Status</label>
      <select id="o-status">
        <option value="REQUESTED" selected>Menunggu pemeriksaan</option>
        <option value="WAITING_MIDWIFE">Mencari bidan</option>
        <option value="OFFERED">Ditawarkan ke mitra</option>
        <option value="CONFIRMED">Dikonfirmasi</option>
        <option value="IN_PROGRESS">Berlangsung</option>
        <option value="COMPLETED">Selesai</option>
        <option value="CANCELLED">Dibatalkan</option>
        <option value="UNFULFILLED">Tidak terpenuhi</option>
        <option value="NEEDS_INFO">Perlu informasi</option>
        <option value="">Semua</option>
      </select>
      <div class="row"><button id="o-load" class="secondary" type="button">Muat ulang</button></div>
      <p class="note">Menampilkan sampai 50 pesanan terbaru pada filter ini.</p>
      <div id="orders"></div>
    </section>

    <section id="p-partners" hidden>
      <h2>Verifikasi mitra</h2>
      <label for="pa-status">Status</label>
      <select id="pa-status">
        <option value="">Semua</option>
        <option value="SUBMITTED">Diajukan</option>
        <option value="IN_REVIEW">Sedang ditinjau</option>
        <option value="NEEDS_CORRECTION">Perlu perbaikan</option>
        <option value="APPROVED">Disetujui, menunggu aktivasi</option>
        <option value="ACTIVE">Aktif</option>
        <option value="SUSPENDED">Ditangguhkan</option>
        <option value="EXPIRED">Kedaluwarsa</option>
        <option value="REJECTED">Ditolak</option>
        <option value="DRAFT">Draf</option>
      </select>
      <div class="row"><button id="pa-load" class="secondary" type="button">Muat ulang</button></div>
      <div id="partners"></div>
      <div id="partner-detail" class="card" hidden></div>
    </section>

    <section id="p-complaints" hidden>
      <h2>Pengaduan</h2>
      <label for="c-status">Status</label>
      <select id="c-status">
        <option value="">Semua</option>
        <option value="OPEN">Baru</option>
        <option value="IN_REVIEW">Sedang ditinjau</option>
        <option value="RESOLVED">Diselesaikan</option>
        <option value="CLOSED">Ditutup</option>
      </select>
      <div class="row"><button id="c-load" class="secondary" type="button">Muat ulang</button></div>
      <div id="complaints"></div>
    </section>
  </div>
</main>
<script src="/js/admin.js"></script>
</body>
</html>
