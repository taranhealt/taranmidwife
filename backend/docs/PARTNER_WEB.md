# UI Mitra (web, DRAF)

- Alamat: `/mitra` (dilayani Laravel). Berkas: `resources/views/mitra.blade.php`, `public/js/mitra.js`, gaya bersama `public/css/patient.css`. Tanpa build.
- Fitur: masuk dan daftar sebagai mitra (`role: partner`; akun non-mitra ditolak dan token langsung dicabut), status verifikasi, pengaturan layanan (menerima tawaran, layanan, wilayah, jadwal per hari dan jam, titik dan radius layanan di peta dengan lingkaran jangkauan), daftar tawaran (terima atau tolak, jarak perkiraan, batas waktu jawab), kunjungan aktif (alamat, patokan, telepon tampil hanya setelah dikonfirmasi; mulai dan selesaikan), riwayat. Daftar dimuat ulang otomatis tiap 20 detik saat tab terlihat.
- Tombol Simpan baru aktif setelah layanan, wilayah, dan pengaturan mitra berhasil dimuat, supaya kegagalan memuat tidak menghapus pilihan yang sudah tersimpan.
- Tanpa titik layanan, mitra tidak menerima tawaran untuk pesanan yang memakai GPS (aturan pencocokan, lihat `docs/GPS_LOCATION.md`).
- API tambahan: `offer_expires_at` (ISO 8601) pada pesanan berstatus OFFERED yang dilihat mitra bila tawaran otomatis. Terima ditolak server dengan 409 `OFFER_EXPIRED` setelah batas waktu; tombol Terima dinonaktifkan di UI setelah batas.
- Token disimpan di `sessionStorage` (kunci terpisah dari UI Pasien). Semua data server ditampilkan lewat `textContent`; nomor telepon hanya dijadikan tautan `tel:` bila lolos pola angka.
- Pihak ketiga: sama dengan UI Pasien (Leaflet dari `unpkg.com` tanpa SRI, tile OpenStreetMap). Lihat `docs/PATIENT_WEB.md`.
- BELUM ADA: pengajuan verifikasi dan unggah dokumen (SIP, STR, identitas, foto wajah) dari UI; halaman kedua pesanan (hanya 50 terbaru); notifikasi dorong; pelacakan posisi.
- BELUM TERUJI: seluruh interaksi di browser, tampilan berbagai ukuran layar, aksesibilitas.
- NEEDS REVIEW (klinis/hukum): teks UI dan persetujuan penggunaan lokasi mitra.
