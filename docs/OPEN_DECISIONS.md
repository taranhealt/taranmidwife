# Keputusan Produk dan Operasional — TaranMidwife

Keputusan berikut ditetapkan oleh pemilik proyek pada 1 November 2026 dan menjadi konfigurasi/pedoman pengembangan saat ini. Perubahan berikutnya harus melalui persetujuan pemilik.

## 1. Retensi teks pengaduan saat akun dihapus

**Keputusan: A — hapus isi teks bebas.**

Saat pasien atau mitra menghapus akun secara sah:
- Isi `complaints.description` untuk pengaduan yang dilaporkan akun tersebut diganti menjadi `[DIHAPUS]`.
- Gunakan model Eloquent agar encrypted cast tetap diterapkan.
- Riwayat pengaduan dan metadata penanganan yang diperlukan untuk audit dapat dipertahankan sesuai kebijakan retensi yang telah ditinjau.
- Jangan mengklaim seluruh data terkait akun dihapus secara fisik; penghapusan akun saat ini merupakan anonimisasi dan pembersihan data tertentu.
- Tambahkan regression test yang membuktikan teks bebas tidak lagi dapat dibaca setelah penghapusan akun.

## 2. Lokasi/GPS Patient App

**Keputusan: A — tetap tanpa GPS.**

- Tidak meminta izin lokasi pada Patient App.
- Tidak menambahkan pengambilan GPS otomatis.
- Konfigurasi server harus mempertahankan `TARAN_REQUIRE_GPS=false`.
- Tanpa koordinat pasien, radius titik layanan mitra tidak digunakan untuk matching; perilaku fallback backend tetap berlaku.
- Jangan mengaktifkan `TARAN_REQUIRE_GPS=true` sampai pemilik menyetujui perubahan produk, UX, privasi dan Data Safety.

## 3. Email reset password

**Keputusan: A — tetap sinkron untuk tahap pilot.**

- Pertahankan pengiriman email reset secara sinkron sementara.
- Jangan mengaktifkan queue atau mengubah konfigurasi mail ke queued job sebelum infrastruktur worker tersedia dan dimonitor.
- Catat rencana migrasi:
  1. Pilih queue backend yang sesuai dengan hosting (database/Redis).
  2. Pastikan worker berjalan sebagai service persisten dengan restart otomatis.
  3. Tambahkan retry/backoff, failed-jobs monitoring, alerting, dan prosedur replay.
  4. Pastikan token reset tetap single-use dan masa berlaku tetap 60 menit.
  5. Tambahkan test untuk dispatch, retry, failure, dan respons generik anti-enumeration.
  6. Jalankan uji staging dengan mail provider nyata sebelum mengaktifkan queue di produksi.
- Evaluasi timing side-channel dan anti-enumeration secara terpisah; respons teks seragam saja tidak membuktikan waktu respons seragam.

## Status operasional

- Keputusan GPS harus diterapkan dengan env `TARAN_REQUIRE_GPS=false`; nilai runtime staging/produksi tetap perlu diverifikasi.
- Keputusan redaksi pengaduan harus dibuktikan oleh migration-free application logic dan regression test.
- Queue email tetap belum diimplementasikan; ini keputusan penundaan, bukan fitur selesai.
