# Matriks peran (sesuai Final MVP Spec v1.0)

| Peran | Boleh | Tidak boleh |
|---|---|---|
| patient | buat/lihat/batalkan booking milik sendiri | booking pasien lain, endpoint admin/mitra |
| partner | lihat dan proses penugasan miliknya (hanya jika ACTIVE) | booking lain; alamat/telepon sebelum CONFIRMED |
| ops_admin | screening, penugasan, pembatalan, lihat semua booking | daftar verifikasi mitra |
| reviewer | daftar mitra untuk verifikasi | booking pasien |
| sysadmin | daftar mitra, lihat booking (tanpa alamat/telepon) | menugaskan/menyaring booking |

Asumsi sementara: peran "approver" belum ada; keputusan persetujuan mitra akan dipegang sysadmin sampai pemilik menetapkan.

## Pengaduan
- patient, partner: ajukan dan lihat pengaduan milik sendiri (booking terkait saja).
- ops_admin, reviewer: lihat dan tangani semua pengaduan.
- sysadmin: lihat saja.

## Penawaran mitra
- partner: kelola layanan, wilayah, jadwal, dan saklar menerima permintaan milik sendiri.
- ops_admin: lihat kandidat mitra untuk booking. Peran lain tidak.
