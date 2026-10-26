<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TaranMidwife - Mitra bidan</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="/css/patient.css">
</head>
<body>
<div id="cfg" hidden data-lat="{{ config('taran.pilot.center_lat') }}" data-lng="{{ config('taran.pilot.center_lng') }}" data-radius="{{ config('taran.pilot.radius_km') }}" data-name="{{ config('taran.pilot.name') }}"></div>
<header>
  <h1>TaranMidwife Mitra</h1>
  <button id="logout" class="link" type="button" hidden>Keluar</button>
</header>
<main>
  <div id="msg" class="msg" role="status" hidden></div>

  <section id="auth">
    <h2>Masuk sebagai mitra</h2>
    <form id="login-form">
      <label for="l-email">Email</label>
      <input id="l-email" type="email" autocomplete="username" required>
      <label for="l-pass">Kata sandi</label>
      <input id="l-pass" type="password" autocomplete="current-password" required>
      <div class="row"><button type="submit">Masuk</button><button id="show-register" class="secondary" type="button">Daftar sebagai mitra</button></div>
    </form>
    <form id="register-form" hidden>
      <label for="r-name">Nama</label>
      <input id="r-name" type="text" autocomplete="name" required maxlength="100">
      <label for="r-email">Email</label>
      <input id="r-email" type="email" autocomplete="email" required>
      <label for="r-pass">Kata sandi (minimal 10 karakter)</label>
      <input id="r-pass" type="password" autocomplete="new-password" required minlength="10">
      <div class="row"><button type="submit">Daftar</button><button id="show-login" class="secondary" type="button">Sudah punya akun</button></div>
    </form>
    <p class="note">Pendaftar baru berstatus draf dan belum dapat menerima tawaran sampai akunnya diverifikasi dan diaktifkan.</p>
  </section>

  <div id="app" hidden>
    <section>
      <h2>Status akun</h2>
      <div id="status"></div>
    </section>

    <section>
      <h2>Tawaran dan kunjungan</h2>
      <div class="row"><button id="refresh" class="secondary" type="button">Muat ulang</button></div>
      <h3>Tawaran menunggu jawaban</h3>
      <div id="offers"></div>
      <h3>Kunjungan aktif</h3>
      <div id="active"></div>
      <h3>Riwayat</h3>
      <div id="history"></div>
      <p class="note">Alamat dan telepon pasien baru tampil setelah Anda menerima tawaran. Sebelum itu hanya jarak perkiraan yang terlihat.</p>
    </section>

    <section>
      <h2>Pengaturan layanan</h2>
      <form id="offer-form">
        <label class="chk"><input id="accepting" type="checkbox"><span>Saya sedang menerima tawaran</span></label>

        <h3>Layanan yang saya berikan</h3>
        <div id="services"></div>
        <h3>Wilayah yang saya layani</h3>
        <div id="areas"></div>

        <h3>Jadwal tersedia (WIB)</h3>
        <div class="tbl-wrap"><table class="avail" id="avail"></table></div>

        <h3>Titik dan radius layanan</h3>
        <p class="note">Gunakan titik perkiraan (misalnya pusat kelurahan), bukan alamat rumah. Tanpa titik layanan, Anda tidak menerima tawaran untuk pesanan yang memakai lokasi GPS.</p>
        <div class="row"><button id="gps" class="secondary" type="button">Gunakan lokasi saya</button><button id="clear-point" class="secondary" type="button">Hapus titik layanan</button></div>
        <div id="map"></div>
        <p id="map-note" class="note" hidden>Peta tidak dapat dimuat (perlu internet). Isi koordinat secara manual.</p>
        <div class="two">
          <div><label for="lat">Lintang</label><input id="lat" type="text" inputmode="decimal"></div>
          <div><label for="lng">Bujur</label><input id="lng" type="text" inputmode="decimal"></div>
        </div>
        <label for="radius">Radius jangkauan (km, 1 sampai 100)</label>
        <input id="radius" type="number" min="1" max="100" step="1" value="10">
        <div class="row"><button id="apply-coords" class="secondary" type="button">Terapkan koordinat</button></div>

        <div class="row"><button id="save" type="submit" disabled>Simpan pengaturan</button></div>
      </form>
    </section>
  </div>
</main>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/js/mitra.js"></script>
</body>
</html>
