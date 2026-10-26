<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TaranMidwife - Pesan layanan bidan</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="/css/patient.css">
</head>
<body>
<div id="cfg" hidden data-lat="{{ config('taran.pilot.center_lat') }}" data-lng="{{ config('taran.pilot.center_lng') }}" data-radius="{{ config('taran.pilot.radius_km') }}" data-name="{{ config('taran.pilot.name') }}"></div>
<header>
  <h1>TaranMidwife</h1>
  <button id="logout" class="link" type="button" hidden>Keluar</button>
</header>
<main>
  <div id="msg" class="msg" role="status" hidden></div>

  <section id="auth">
    <h2>Masuk</h2>
    <form id="login-form">
      <label for="l-email">Email</label>
      <input id="l-email" type="email" autocomplete="username" required>
      <label for="l-pass">Kata sandi</label>
      <input id="l-pass" type="password" autocomplete="current-password" required>
      <div class="row"><button type="submit">Masuk</button><button id="show-register" class="secondary" type="button">Daftar akun baru</button></div>
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
    <p class="note">Layanan ini bukan layanan gawat darurat. Jika terjadi keadaan darurat, segera ke fasilitas kesehatan terdekat.</p>
  </section>

  <div id="app" hidden>
    <section>
      <h2>Pesan kunjungan</h2>
      <form id="booking-form">
        <label for="service">Layanan</label>
        <select id="service" required></select>
        <label for="area">Wilayah</label>
        <select id="area" required></select>
        <div class="two">
          <div><label for="date">Tanggal</label><input id="date" type="date" required></div>
          <div><label for="hour">Jam</label><select id="hour" required></select></div>
        </div>
        <label for="address">Alamat lengkap</label>
        <textarea id="address" required maxlength="300"></textarea>
        <label for="landmark">Patokan (opsional)</label>
        <input id="landmark" type="text" maxlength="100">
        <label for="phone">Nomor telepon yang dapat dihubungi</label>
        <input id="phone" type="tel" inputmode="tel" pattern="\+?[0-9]{8,15}" placeholder="08xxxxxxxxxx" required>

        <label>Lokasi kunjungan</label>
        <div class="row"><button id="gps" class="secondary" type="button">Gunakan lokasi saya</button></div>
        <div id="map"></div>
        <p id="map-note" class="note" hidden>Peta tidak dapat dimuat (perlu internet). Isi koordinat secara manual.</p>
        <div class="two">
          <div><label for="lat">Lintang</label><input id="lat" type="text" inputmode="decimal"></div>
          <div><label for="lng">Bujur</label><input id="lng" type="text" inputmode="decimal"></div>
        </div>
        <div class="row"><button id="apply-coords" class="secondary" type="button">Terapkan koordinat</button></div>
        <p class="note">Ketuk peta atau geser pin untuk memperbaiki titik. Lokasi hanya dipakai untuk mencocokkan bidan terdekat; bidan hanya melihat jarak perkiraan.</p>
        <div id="coverage" class="cov" hidden></div>

        <div class="row"><button id="submit" type="submit">Ajukan permintaan</button></div>
        <p class="note">Jadwal belum final sampai dikonfirmasi.</p>
      </form>
    </section>

    <section>
      <h2>Pesanan saya</h2>
      <div class="row"><button id="refresh" class="secondary" type="button">Muat ulang</button></div>
      <div id="bookings"></div>
    </section>
  </div>
</main>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/js/patient.js"></script>
</body>
</html>
