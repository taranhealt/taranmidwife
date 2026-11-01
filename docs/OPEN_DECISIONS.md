# Keputusan terbuka (perlu persetujuan pemilik)

Tiga hal ini ditemukan saat audit dan **sengaja tidak diubah sepihak** karena menyangkut kebijakan, bukan bug.

## 1. Retensi pengaduan vs janji "tanpa identitas"

- Halaman publik `hapus-akun` menyatakan pengaduan yang pernah diajukan *tetap tersimpan tanpa identitas*.
- Saat akun dihapus, `AccountController` menganonimkan akun dan menghapus alamat/telepon pada pesanan, tetapi **teks pengaduan (hingga 1000 karakter) tidak dibersihkan**. Teks bebas bisa memuat nama, nomor telepon, atau keterangan kesehatan yang ditulis pengguna. Teks itu terenkripsi di database, tetapi tetap bisa dibaca admin.
- Kebijakan privasi masih menandai masa retensi pengaduan sebagai `[KEPUTUSAN]`.
- Pilihan: (a) ganti isi pengaduan dengan penanda `[DIHAPUS]` saat akun dihapus; (b) pertahankan isi, tetapi ubah kalimat di halaman `hapus-akun` agar jujur; (c) tetapkan masa retensi lalu hapus otomatis.

## 2. Lokasi (GPS) di Patient App

- Backend mendukung koordinat pasien: pencocokan mitra memakai radius titik layanan mitra bila koordinat ada. Tanpa koordinat, pencocokan memakai urutan pemerataan dan **radius mitra tidak berlaku**.
- Form booking Patient App belum mengirim koordinat, dan tidak ada izin lokasi di manifest.
- Akibat: bila `TARAN_REQUIRE_GPS=true` diaktifkan, Patient App **tidak bisa membuat pesanan**. Selama nilainya `false` (bawaan), pemesanan berjalan.
- Menambah pengambilan lokasi mengubah deklarasi Data Safety di Play Console. Pilihan: tetap tanpa lokasi (biarkan `false`), isian koordinat manual opsional, atau GPS dengan izin eksplisit.

## 3. Email reset kata sandi dikirim sinkron

- Untuk akun terdaftar, pengiriman email terjadi sebelum respons, sehingga respons sedikit lebih lambat daripada untuk email yang tidak terdaftar. Pesan respons sudah seragam; yang berbeda hanya waktu.
- Memperbaikinya memerlukan antrean (queue worker) yang berjalan di server produksi. Aktifkan hanya bila worker sudah ada.
