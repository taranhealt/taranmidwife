# Lokasi GPS dan jangkauan

## Pasien
- `POST /api/v1/bookings` menerima `latitude` dan `longitude` (keduanya atau tidak sama sekali). Wajib bila `TARAN_REQUIRE_GPS=true`.
- Koordinat disimpan terenkripsi di `bookings.patient_lat` dan `patient_lng`, dibulatkan 6 desimal. Tidak pernah ditampilkan ke siapa pun.
- Di luar area pilot: 422 `OUT_OF_COVERAGE`.
- `GET /api/v1/coverage?lat=&lng=&service_id=` (hanya pasien) mengembalikan `in_pilot_area`, `partners_nearby` (jumlah mitra aktif yang radiusnya mencakup titik, tanpa identitas, tanpa memeriksa jadwal), dan `serviceable`.

## Mitra
- `PUT /api/v1/midwife/offerings` menerima `service_point: {lat, lng, radius_km}` (radius 1 sampai 100 km) atau `null` untuk menghapus. `GET` mengembalikan `service_point`.
- Titik layanan sebaiknya titik perkiraan (misalnya pusat kelurahan), bukan alamat rumah.

## Pencocokan
- Booking dengan koordinat: hanya mitra yang punya titik layanan dan jaraknya (haversine, km) tidak melebihi radiusnya; urutan terdekat dulu, lalu yang paling lama tidak ditawari. Ini tambahan atas kriteria layanan, wilayah, jadwal, SIP, dan ketersediaan.
- Booking tanpa koordinat: perilaku lama (tanpa filter jarak).
- Mitra melihat `distance_km` (dibulatkan 0,1 km) pada booking yang diberikan kepadanya; koordinat pasien tidak pernah dikirim ke mitra.

## Area pilot
- `config/taran.php`, diubah lewat `.env`: `TARAN_PILOT_NAME`, `TARAN_PILOT_LAT`, `TARAN_PILOT_LNG`, `TARAN_PILOT_RADIUS_KM` (bawaan 3.20, 99.50, 40).
- NILAI BAWAAN ADALAH PERKIRAAN KASAR dan belum diverifikasi terhadap batas wilayah sebenarnya. Berbentuk lingkaran sederhana, bukan batas kecamatan.

## Belum ada
- UI peta dan pin; pelacakan posisi mitra langsung; daftar tunggu untuk area baru; geocoding alamat.
- Keputusan hukum tentang data lokasi (UU PDP) adalah tanggung jawab pemilik: NEEDS REVIEW.

## Seeder demo dan saring otomatis (26 Okt 2026)
- `DevDemoSeeder` membuat mitra demo dengan titik layanan di titik tengah area pilot (bukan koordinat Sei Suka yang diverifikasi), radius 15 km, jadwal penuh.
- Saring otomatis: lihat `docs/AUTO_SCREEN.md`.
