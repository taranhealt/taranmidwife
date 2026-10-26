# Status pesanan (backend: app/Services/BookingWorkflow.php)

REQUESTED -> WAITING_MIDWIFE (screening admin) -> OFFERED (penugasan) -> CONFIRMED (mitra menerima) -> IN_PROGRESS -> COMPLETED
Cabang: NEEDS_INFO, CANCELLED, UNFULFILLED. OFFERED -> WAITING_MIDWIFE saat mitra menolak.
Belum diimplementasikan (menunggu protokol klinis): SCREENING, ESCALATED, EN_ROUTE, COMPLAINT_REVIEW.
Slot: 08, 10, 12, 14, 16 WIB (asumsi sementara). Double booking dicegah oleh unique index parsial di database.
