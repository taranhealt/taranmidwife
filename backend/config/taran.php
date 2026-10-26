<?php
return [
    // Area pilot. NILAI AWAL INI PERKIRAAN KASAR dan BELUM DIVERIFIKASI; ganti lewat .env.
    'pilot' => [
        'name' => env('TARAN_PILOT_NAME', 'Kabupaten Batu Bara, Sumatera Utara'),
        'center_lat' => (float) env('TARAN_PILOT_LAT', 3.20),
        'center_lng' => (float) env('TARAN_PILOT_LNG', 99.50),
        'radius_km' => (float) env('TARAN_PILOT_RADIUS_KM', 40),
    ],
    // Bila true, booking wajib menyertakan latitude dan longitude.
    'require_gps' => (bool) env('TARAN_REQUIRE_GPS', false),
    // Bila true, booking baru langsung disaring dan ditawarkan otomatis tanpa menunggu admin.
    // BAWAAN false: pemeriksaan administratif oleh admin tetap berlaku. Lihat docs/AUTO_SCREEN.md.
    'auto_screen' => (bool) env('TARAN_AUTO_SCREEN', false),
];
