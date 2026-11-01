# DEVELOPMENT STATUS - TaranMidwife

Diperbarui: 26 Okt 2026. Dasar: commit 6f0f7ae (34 test lulus).
Bukti: php artisan test di Termux (PostgreSQL lokal): Tests:    122 passed (688 assertions)
Lingkungan: HANYA lokal. Tidak ada staging/produksi.

| Komponen | Status | Catatan |
|---|---|---|
| Monorepo, backend Laravel | TERUJI (lokal) | |
| Auth Sanctum, peran, rate limit, audit | TERUJI (lokal) | token kedaluwarsa 7 hari |
| Katalog layanan/wilayah, seeder demo sintetis | TERUJI (lokal) | seeder menolak produksi |
| Booking + state machine + penugasan | TERUJI (lokal) | anti double booking, idempotensi, enkripsi alamat |
| Verifikasi mitra, dokumen privat, kedaluwarsa SIP | TERUJI (lokal) | 5 pemeriksaan wajib, persetujuan hanya sysadmin, tanpa auto-approval |
| Penghapusan akun (anonimisasi) | TERUJI (lokal) | hanya pasien/mitra; ditolak bila ada pesanan aktif; masa retensi = keputusan pemilik, NEEDS REVIEW hukum |
| Pengaduan (pasien/mitra) dan penanganan admin | TERUJI (lokal) | teks terenkripsi; kategori SAFETY hanya ditandai, aturan eskalasi NEEDS REVIEW klinis |
| Data penawaran mitra (layanan, wilayah, jadwal) dan mesin pencocokan | TERUJI (lokal) | terhubung ke alur booking lewat penawaran otomatis |
| Penawaran otomatis ke mitra layak, penolakan/kedaluwarsa berpindah ke mitra berikutnya, perintah bookings:expire-offers | TERUJI (lokal) | batas 10 menit adalah asumsi; jadwal otomatis baru terbukti di staging (belum ada cron); tanpa notifikasi |
| Lokasi GPS pasien, titik dan radius layanan mitra, pencocokan terdekat, cek jangkauan, batas area pilot | TERUJI (lokal) | batas area pilot adalah PERKIRAAN, belum diverifikasi; UI peta belum dibuat; pelacakan mitra langsung belum ada |
| Saring otomatis booking baru (sakelar TARAN_AUTO_SCREEN, bawaan MATI), koordinat ikut dianonimkan saat hapus akun, seeder demo mitra layak | TERUJI (lokal) | sakelar menggantikan pemeriksaan administratif admin: NEEDS REVIEW sebelum dinyalakan; titik layanan demo memakai titik tengah area pilot |
| UI Pasien web di /app: masuk, GPS, peta dan pin, cek jangkauan, pesan, daftar dan batalkan pesanan | DRAF (halaman diuji dimuat; interaksi di browser BELUM diuji) | pendaftaran dan masuk dari UI belum diuji terhadap AuthController; peta butuh internet (Leaflet dan tile OpenStreetMap dari CDN, tanpa SRI); teks belum ditinjau klinis |
| UI Mitra web di /mitra: masuk dan daftar mitra, titik dan radius layanan di peta, layanan, wilayah, jadwal, terima atau tolak tawaran, mulai dan selesaikan kunjungan | DRAF (halaman diuji dimuat, batas waktu tawaran TERUJI lokal; interaksi di browser BELUM diuji) | pengajuan dan unggah dokumen verifikasi belum ada di UI; peta butuh internet (CDN tanpa SRI); hanya 50 pesanan terbaru; teks belum ditinjau klinis |
| Halaman hapus akun web di /hapus-akun: masuk, peringatan konsekuensi, konfirmasi kata sandi, panggil DELETE /api/v1/me | DRAF (halaman diuji dimuat dan tanpa pihak ketiga; interaksi di browser BELUM diuji) | belum ada jalur bagi yang lupa kata sandi; teks belum ditinjau hukum; URL produksi menunggu deployment |
| Inventaris Data safety | DRAF | dari skema aktual; belum final, NEEDS REVIEW hukum; sisi aplikasi belum ada |
| Pembayaran | TIDAK DIAKTIFKAN | menunggu keputusan bisnis/hukum |
| Notifikasi | BELUM DIMULAI | |
| Dashboard admin web di /admin: pesanan, kandidat, tugaskan, verifikasi mitra | DRAF (halaman diuji dimuat; interaksi di browser BELUM diuji) | |
| Aplikasi Flutter pasien dan mitra | BELUM DIMULAI | Flutter belum terpasang di Termux |
| Deployment, HTTPS, backup | BELUM DIMULAI | |
| Kepatuhan hukum/klinis | NEEDS REVIEW | tidak dinyatakan terpenuhi |

## Keputusan (usul Claude, disetujui pemilik)
Flutter + Laravel + PostgreSQL; palet #101D3A; dua aplikasi Android; alur sesuai MVP Spec (pasien mengajukan, admin menugaskan, mitra menerima).

## Asumsi sementara
Slot 2 jam; peran approver = sysadmin; ID tabel users berupa angka.

## Keterbatasan
Tes hanya di PostgreSQL lokal; belum ada uji beban, uji keamanan penetrasi, uji pemulihan backup.

## Risiko terbuka
- Booking CONFIRMED milik mitra yang kemudian EXPIRED/SUSPENDED belum ditangani (keputusan operasional pemilik).
- Perintah `partners:expire-sip` terdaftar di scheduler, tetapi belum ada cron/scheduler yang menjalankannya (butuh deployment).
- Hasil `unverified_limited` (mis. IHS) berarti verifikasi belum penuh, bukan klaim terverifikasi.
- Aturan eskalasi pengaduan keselamatan (kategori SAFETY) belum ada; menunggu protokol klinis. Saat ini hanya ditandai dan dapat difilter admin.
- Pengaduan tetap tersimpan setelah akun pelapor dihapus; teks bebasnya bisa memuat data pribadi dan belum punya aturan retensi (keputusan pemilik, NEEDS REVIEW hukum).

## Keputusan pemilik (26 Okt 2026)
- Model penugasan: hibrida. Pasien mengajukan; admin menyaring (sementara, sampai protokol klinis ada); sistem menawarkan otomatis ke satu mitra layak per waktu, mirip aplikasi ojek online; admin tetap dapat menugaskan manual. Dokumen konteks bagian 9 menggambarkan pasien memilih bidan; perbedaan ini belum direkonsiliasi penuh.
- ASUMSI sementara (belum diputuskan): batas waktu mitra menjawab tawaran 10 menit; pemilihan mitra tanpa GPS (wilayah, layanan, jadwal, pemerataan).
- Belum dibangun: notifikasi ke mitra, profil publik bidan, pencarian bidan oleh pasien.
- Zona waktu sesi database semula bukan UTC, sehingga waktu booking (disimpan sebagai UTC) berisiko bergeser. Koneksi pgsql kini diset `timezone => UTC`. Data lokal lama dibuat ulang lewat migrate:fresh; pastikan server staging/produksi memakai pengaturan yang sama.

## Penawaran otomatis (26 Okt 2026)
- Saat admin menyaring booking, sistem menawarkan ke kandidat teratas (`OfferDispatcher`). Tanpa kandidat, status tetap WAITING_MIDWIFE. Detail: `docs/AUTO_OFFER.md`.

## Tindak lanjut penawaran (26 Okt 2026)
- TERUJI (lokal): `accept` menolak tawaran otomatis yang sudah lewat batas (409 OFFER_EXPIRED), walau perintah kedaluwarsa belum berjalan.

## Penugasan manual dan SIP (26 Okt 2026)
- TERUJI (lokal): `assign` manual memeriksa kelayakan mitra (layanan, wilayah, jadwal, ketersediaan, SIP). Mitra tidak layak ditolak 422, kecuali admin mengirim `override: true` dan `override_reason`; override dicatat di audit (`booking.assign_override`) dan riwayat status. Detail: `docs/MANUAL_ASSIGN.md`.
- TERUJI (lokal): mitra melihat tawaran (OFFERED) di daftar booking tanpa alamat dan telepon; kontak baru terbuka setelah CONFIRMED.
- TERUJI (lokal): pencocokan menerima mitra yang SIP-nya berlaku sampai dan termasuk tanggal kunjungan (WIB) dan menolak yang habis sebelumnya.
- TERUJI (lokal): `partners:expire-sip` memakai tanggal WIB; SIP yang berakhir hari ini tetap ACTIVE, yang berakhir kemarin menjadi EXPIRED.
- KEPUTUSAN SEMENTARA, belum disetujui pemilik: opsi override untuk `assign`; arti masa berlaku SIP (sampai dan termasuk tanggal berakhir).
- Sembilan pemanggilan `assign` di `BookingFlowTest` kini memakai override, karena mitra di test itu tidak punya layanan, wilayah, atau jadwal.

## Lokasi GPS (26 Okt 2026)
- TERUJI (lokal): booking dapat menyertakan `latitude` dan `longitude` (terenkripsi); di luar area pilot ditolak 422 `OUT_OF_COVERAGE`; endpoint `GET /api/v1/coverage` memberi tahu apakah lokasi di area pilot dan berapa mitra aktif di sekitarnya. Detail: `docs/GPS_LOCATION.md`.
- TERUJI (lokal): mitra mengatur titik dan radius layanan (`service_point`); pencocokan dengan GPS hanya memilih mitra yang radiusnya mencakup pasien, terdekat lebih dulu; mitra melihat jarak perkiraan, bukan koordinat.
- ASUMSI sementara: titik tengah dan radius area pilot (Kabupaten Batu Bara) adalah perkiraan kasar saya, BELUM DIVERIFIKASI. Ganti lewat `TARAN_PILOT_LAT`, `TARAN_PILOT_LNG`, `TARAN_PILOT_RADIUS_KM` di `.env`.
- Belum dibuat: UI peta dan pin, pelacakan posisi mitra langsung (EN_ROUTE), daftar tunggu untuk area di luar jangkauan.
- NEEDS REVIEW (hukum): data lokasi pasien dan mitra; inventaris Data safety sudah ditambah.

## Blok 5 (26 Okt 2026)
- TERUJI (lokal): `DELETE /api/v1/me` menghapus `patient_lat` dan `patient_lng` pada semua booking pasien; untuk mitra menghapus titik dan radius layanan serta mematikan `accepting_requests`. Temuan di `DATA_SAFETY_INVENTORY.md` sudah ditutup.
- TERUJI (lokal): sakelar `TARAN_AUTO_SCREEN` (bawaan false). Bila true, booking baru yang sudah tersimpan otomatis menjadi WAITING_MIDWIFE lalu ditawarkan ke mitra terdekat. Detail: `docs/AUTO_SCREEN.md`.
- TERUJI (lokal): `DevDemoSeeder` membuat mitra demo yang layak (aktif, SIP berlaku, semua layanan demo, wilayah Sei Suka, jadwal penuh, titik layanan di titik tengah area pilot, radius 15 km) dan aman dijalankan berulang.
- NEEDS REVIEW: apakah pemeriksaan administratif boleh dilewati (keputusan pemilik dan klinis) sebelum sakelar dinyalakan.

## Blok 6 (26 Okt 2026)
- TERUJI (lokal): saring otomatis dipindah dari event model ke `BookingController::store`. Respons `POST /bookings` kini memuat status terbaru dan riwayat status berurutan (REQUESTED lebih dulu). Dua temuan blok 5 ditutup.
- DRAF: UI Pasien web di `/app` (`resources/views/patient.blade.php`, `public/js/patient.js`, `public/css/patient.css`). Diuji hanya: halaman termuat, aset ada, titik tengah peta mengikuti konfigurasi. Interaksi (GPS, peta, kirim pesanan) BELUM diuji di browser; lihat `docs/PATIENT_WEB.md`.
- NEEDS REVIEW: teks UI (pemberitahuan darurat, keterangan layanan) belum ditinjau klinis.

## Blok 7 (26 Okt 2026)
- TERUJI (lokal): respons pesanan untuk mitra memuat `offer_expires_at` pada tawaran otomatis berstatus OFFERED; tidak ada pada penugasan manual dan tidak pernah dikirim ke pasien.
- DRAF: UI Mitra web di `/mitra` (`resources/views/mitra.blade.php`, `public/js/mitra.js`, memakai `public/css/patient.css`). Diuji hanya: halaman termuat dan aset ada. Interaksi (peta, simpan pengaturan, terima, tolak, mulai, selesaikan) BELUM diuji di browser; lihat `docs/PARTNER_WEB.md`.
- Diperiksa dengan membaca kode `AuthController` (bukan diuji dari browser): register menerima `role` patient atau partner dan mengembalikan `token` dan `user.role`; ini dipakai kedua UI.
- Diperbaiki: formulir daftar UI Pasien kini meminta kata sandi minimal 10 karakter, sama dengan server (diuji).
- NEEDS REVIEW: teks UI Mitra belum ditinjau klinis; persetujuan penggunaan lokasi mitra (hukum).

## Blok 8 (26 Okt 2026)
- DRAF: halaman web permintaan hapus akun di `/hapus-akun` (`resources/views/hapus-akun.blade.php`, `public/js/hapus-akun.js`). Diuji hanya: halaman termuat tanpa login, aset ada, tanpa sumber pihak ketiga. Alur di browser (masuk, konfirmasi, hapus, pesan 409 dan 429) BELUM diuji.
- Memakai `DELETE /api/v1/me` yang sudah TERUJI (lihat `docs/ACCOUNT_DELETION.md`). Pesan untuk 409 dan 429 ditentukan dari kode status; pesan kesalahan lain dari respons server (bentuk respons kata sandi salah belum saya baca).
- NEEDS REVIEW (hukum): teks peringatan, retensi, dan kesesuaian dengan persyaratan Google Play. URL publik baru ada setelah deployment.

## Lupa kata sandi dan halaman hukum (27 Okt 2026)
- TERUJI (lokal): lupa dan atur ulang kata sandi lewat API dan halaman web (`/lupa-sandi`, `/atur-ulang-sandi`). Respons permintaan selalu sama; akun tidak aktif tidak dikirimi email; token sekali pakai berlaku 60 menit; semua token login dicabut setelah sandi berubah. Detail: `docs/PASSWORD_RESET.md`.
- DRAF (bukan hukum final): halaman `/kebijakan-privasi`, berisi data yang memang dikumpulkan kode dan penanda [ISI] atau [KEPUTUSAN] untuk bagian yang belum ada. NEEDS REVIEW (hukum): seluruh isi, retensi, hak pengguna, dan pihak ketiga.
- Belum ada: pengiriman email sungguhan (MAIL_MAILER=log; penyedia email dipilih saat staging), tautan kebijakan privasi di semua halaman, pengaturan ulang kata sandi lewat aplikasi seluler.
- Hasil uji: 114 passed (644 assertions).

## UI Admin (27 Okt 2026)
- TERUJI (lokal, otomatis): halaman `/admin` termuat tanpa sumber luar; `GET /bookings` mendukung filter `status`; `activate` mitra memakai tanggal WIB (SIP berakhir hari ini masih sah, kemarin ditolak).
- BELUM DIUJI otomatis: perilaku JavaScript UI Admin (login staf, tab menurut peran, antrean pesanan, daftar kandidat, tugaskan, batalkan, dokumen mitra, pemeriksaan lima butir, tindakan verifikasi). Perlu uji manual di ponsel; tombol tindakan mitra mengikuti perkiraan transisi status dan server tetap penentu. Detail: `docs/ADMIN_UI.md`.
- Belum ada di UI: tab pengaduan (API sudah ada), override `assign` (hanya lewat API), pencarian dan halaman lanjutan (daftar dibatasi 50 terbaru).
- Server uji lokal TaranMidwife memakai port 8010 dan dibuka lewat localhost; port 8000 dipakai aplikasi lain di perangkat ini. Lihat `docs/LOCAL_RUN.md`.
- Hasil uji: 118 passed (662 assertions). Kali ini tanpa migrate:fresh (tidak ada perubahan skema) agar akun demo di basis data lokal tidak terhapus.

## Pembaruan deployment (26 Okt 2026)
- Header keamanan HTTP, HTTPS dipaksa di production, template env production, skrip backup dan uji restore: TERUJI (lokal). Satu latihan backup lalu restore ke database sementara berhasil; belum ada backup terjadwal di server.
- Deployment publik (hosting, domain, HTTPS, cron): BELUM DIMULAI; menunggu keputusan pemilik (penyedia, biaya).
- Rilis Google Play: NO-GO. Lihat docs/PLAY_RELEASE_STATUS.md. Aplikasi Android (Flutter) belum ada; build AAB di lingkungan selain Termux.

- Catatan: latihan restore (27 Okt 2026) dijalankan pada database dev yang kosong (users=0). Ini membuktikan skema dan prosedur pulih, bukan integritas data nyata.
- CI GitHub (backend) ditulis; BELUM terbukti sampai dijalankan di GitHub.

---

## Pembaruan repositori dan Android (1 November 2026)

Catatan ini melengkapi snapshot status sebelumnya. Status historis
di atas dipertahankan sebagai catatan perkembangan.

- Source code Flutter aplikasi pasien tersedia di `apps/patient_app/`.
- Source code shared package tersedia di `packages/taran_core/`.
- Konfigurasi Android tersedia di `apps/patient_app/android/`.
- Application ID yang tercantum pada Gradle: `com.taranrainmakergroup.taranmidwife`.
- Workflow Flutter sebelumnya berhasil membangun APK debug.
- Workflow kini dikonfigurasi untuk mengunggah APK debug sebagai artifact;
  keberhasilan upload harus diverifikasi pada run GitHub Actions berikutnya.
- Build debug bukan signed production AAB.
- Konfigurasi release Android masih menggunakan debug signing dan harus
  diperbaiki sebelum publikasi Google Play.
- Aplikasi mitra Android belum boleh dianggap tersedia hanya berdasarkan
  dokumentasi backend atau workflow bootstrap.
- Pengujian end-to-end aplikasi pada perangkat nyata masih diperlukan.
- Inventaris Data Safety harus mencakup aplikasi Flutter dan dependensinya.
- Status Google Play tetap NO-GO sampai semua persyaratan rilis diverifikasi.

Catatan ini tidak mengubah hasil pengujian historis backend dan tidak
menyatakan deployment produksi, kepatuhan hukum, atau kesiapan klinis.

---

## Pembaruan repositori dan Android (1 November 2026)

Catatan ini melengkapi snapshot status sebelumnya. Status historis
di atas dipertahankan sebagai catatan perkembangan.

- Source code Flutter aplikasi pasien tersedia di `apps/patient_app/`.
- Source code shared package tersedia di `packages/taran_core/`.
- Konfigurasi Android tersedia di `apps/patient_app/android/`.
- Application ID yang tercantum pada Gradle: `com.taranrainmakergroup.taranmidwife`.
- Workflow Flutter sebelumnya berhasil membangun APK debug.
- Workflow kini dikonfigurasi untuk mengunggah APK debug sebagai artifact;
  keberhasilan upload harus diverifikasi pada run GitHub Actions berikutnya.
- Build debug bukan signed production AAB.
- Konfigurasi release Android masih menggunakan debug signing dan harus
  diperbaiki sebelum publikasi Google Play.
- Aplikasi mitra Android belum boleh dianggap tersedia hanya berdasarkan
  dokumentasi backend atau workflow bootstrap.
- Pengujian end-to-end aplikasi pada perangkat nyata masih diperlukan.
- Inventaris Data Safety harus mencakup aplikasi Flutter dan dependensinya.
- Status Google Play tetap NO-GO sampai semua persyaratan rilis diverifikasi.

Catatan ini tidak mengubah hasil pengujian historis backend dan tidak
menyatakan deployment produksi, kepatuhan hukum, atau kesiapan klinis.

---

## Pembaruan repositori dan Android (1 November 2026)

Catatan ini melengkapi snapshot status sebelumnya. Status historis
di atas dipertahankan sebagai catatan perkembangan.

- Source code Flutter aplikasi pasien tersedia di `apps/patient_app/`.
- Source code shared package tersedia di `packages/taran_core/`.
- Konfigurasi Android tersedia di `apps/patient_app/android/`.
- Application ID yang tercantum pada Gradle: `com.taranrainmakergroup.taranmidwife`.
- Workflow Flutter sebelumnya berhasil membangun APK debug.
- Workflow kini dikonfigurasi untuk mengunggah APK debug sebagai artifact;
  keberhasilan upload harus diverifikasi pada run GitHub Actions berikutnya.
- Build debug bukan signed production AAB.
- Konfigurasi release Android masih menggunakan debug signing dan harus
  diperbaiki sebelum publikasi Google Play.
- Aplikasi mitra Android belum boleh dianggap tersedia hanya berdasarkan
  dokumentasi backend atau workflow bootstrap.
- Pengujian end-to-end aplikasi pada perangkat nyata masih diperlukan.
- Inventaris Data Safety harus mencakup aplikasi Flutter dan dependensinya.
- Status Google Play tetap NO-GO sampai semua persyaratan rilis diverifikasi.

Catatan ini tidak mengubah hasil pengujian historis backend dan tidak
menyatakan deployment produksi, kepatuhan hukum, atau kesiapan klinis.
