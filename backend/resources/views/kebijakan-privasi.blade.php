<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TaranMidwife - Kebijakan privasi (DRAF)</title>
<link rel="stylesheet" href="/css/auth.css">
</head>
<body>
<header><h1>TaranMidwife - Kebijakan privasi</h1></header>
<main>
  <p class="banner"><strong>DRAF.</strong> Dokumen ini disusun dari cara kerja aplikasi saat ini dan belum ditinjau ahli hukum. Bagian bertanda <span class="todo">[ISI]</span> atau <span class="todo">[KEPUTUSAN]</span> belum final. Dokumen ini belum menyatakan kepatuhan terhadap peraturan apa pun.</p>

  <h2>Siapa kami</h2>
  <p>TaranMidwife adalah layanan bidan (kunjungan nifas, konseling menyusui, edukasi bayi baru lahir) yang dikelola PT Taran Rainmaker Group. Alamat dan kontak resmi: <span class="todo">[ISI]</span>.</p>

  <h2>Data yang kami kumpulkan</h2>
  <ul>
    <li><strong>Akun:</strong> nama, email, nomor telepon (opsional), dan kata sandi (disimpan dalam bentuk acak satu arah, tidak dapat dibaca kembali).</li>
    <li><strong>Pesanan (pasien):</strong> layanan, wilayah, jadwal, alamat, patokan, nomor telepon kontak, dan koordinat hanya bila secara eksplisit disediakan pengguna. Patient App saat ini tidak meminta izin GPS; koordinat manual tetap opsional bila antarmuka yang mendukungnya tersedia. Alamat, telepon kontak, dan koordinat disimpan terenkripsi.</li>
    <li><strong>Mitra bidan:</strong> NIK (terenkripsi), nomor dan masa berlaku SIP, penerbit SIP, wilayah praktik, serta dokumen verifikasi (SIP, STR, identitas, foto wajah) yang disimpan pada penyimpanan privat. Titik dan radius layanan yang dipilih mitra.</li>
    <li><strong>Pengaduan:</strong> isi pengaduan dienkripsi. Saat akun pelapor dihapus, teks bebas pengaduan yang dilaporkan akun tersebut diganti menjadi <code>[DIHAPUS]</code>; metadata dan riwayat penanganan dapat tetap disimpan sesuai masa retensi yang belum ditetapkan final.</li>
    <li><strong>Catatan sistem:</strong> catatan audit tindakan penting (misalnya masuk, keluar, perubahan status pesanan) untuk keamanan dan penelusuran.</li>
  </ul>

  <h2>Untuk apa data dipakai</h2>
  <p>Memproses dan mencocokkan pesanan dengan mitra, memverifikasi mitra, menjaga keamanan layanan, menangani pengaduan, dan mengirim email terkait akun (misalnya atur ulang kata sandi).</p>

  <h2>Siapa yang dapat melihat data</h2>
  <ul>
    <li>Mitra baru melihat alamat dan telepon pasien setelah menerima pesanan. Koordinat pasien tidak dibagikan kepada mitra; mitra hanya melihat jarak perkiraan.</li>
    <li>Staf operasional dan peninjau yang berwenang dapat melihat data sesuai tugasnya. Pembukaan dokumen verifikasi mitra dicatat.</li>
    <li>Pihak ketiga lain, termasuk penyedia peta, pembayaran, dan analitik: <span class="todo">[ISI setelah dipastikan]</span>. Saat ini tidak ada payment gateway.</li>
  </ul>

  <h2>Penghapusan akun</h2>
  <p>Anda dapat menghapus akun lewat halaman <a href="/hapus-akun">Hapus akun</a>. Penghapusan menganonimkan identitas akun dan data tertentu, mengganti teks bebas pengaduan yang Anda laporkan menjadi <code>[DIHAPUS]</code>, serta mencabut token masuk. Metadata dan catatan audit tertentu dapat tetap tersimpan. Rincian retensi masih memerlukan tinjauan hukum.</p>

  <h2>Lama penyimpanan</h2>
  <p><span class="todo">[KEPUTUSAN]</span> Masa retensi data pesanan anonim, pengaduan, dan catatan audit belum ditetapkan.</p>

  <h2>Hak Anda dan cara menghubungi kami</h2>
  <p><span class="todo">[ISI]</span> Rincian hak pengguna dan kanal permintaan akan ditetapkan setelah tinjauan hukum.</p>

  <h2>Perubahan dan tanggal berlaku</h2>
  <p><span class="todo">[ISI]</span></p>
</main>
<footer><a href="/app">Kembali ke aplikasi</a></footer>
</body>
</html>
