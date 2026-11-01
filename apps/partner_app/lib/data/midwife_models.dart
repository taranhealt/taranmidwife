import 'package:taran_core/taran_core.dart';

import 'json_helpers.dart';

// Model, konstanta, dan validasi sisi klien untuk kontrak `/midwife/*`.
// Berkas ini murni Dart tanpa akses jaringan, sehingga mudah diuji.

/// Sama dengan aturan backend `max:5120` (KB) pada `POST /midwife/documents`.
const maxDocumentBytes = 5120 * 1024;

/// Jenis dokumen yang diterima backend (`MidwifeProfileController::DOC_TYPES`).
const documentTypes = ['SIP', 'STR', 'IDENTITY', 'FACE_PHOTO'];

const documentLabels = {
  'SIP': 'Surat Izin Praktik (SIP)',
  'STR': 'Surat Tanda Registrasi (STR)',
  'IDENTITY': 'Identitas diri',
  'FACE_PHOTO': 'Foto profil',
};

/// Jam slot yang diterima backend (`BookingController::SLOT_HOURS`).
const slotHours = [8, 10, 12, 14, 16];

String verificationLabel(String status) {
  switch (status) {
    case 'DRAFT':
      return 'Draf';
    case 'SUBMITTED':
      return 'Menunggu peninjauan';
    case 'NEEDS_CORRECTION':
      return 'Perlu perbaikan';
    case 'IN_REVIEW':
      return 'Sedang ditinjau';
    case 'APPROVED':
      return 'Disetujui, menunggu aktivasi';
    case 'ACTIVE':
      return 'Aktif';
    case 'REJECTED':
      return 'Ditolak';
    case 'EXPIRED':
      return 'Kedaluwarsa';
    case 'SUSPENDED':
      return 'Ditangguhkan';
    default:
      return status.isEmpty ? '-' : status;
  }
}

/// Validasi awal berkas sebelum diunggah. Mengembalikan pesan galat atau `null`.
String? validateDocument({
  required String type,
  required String fileName,
  required int sizeBytes,
}) {
  if (!documentTypes.contains(type)) return 'Jenis dokumen tidak dikenal.';
  final dot = fileName.lastIndexOf('.');
  final ext = dot < 0 ? '' : fileName.substring(dot + 1).toLowerCase();
  final isPhoto = type == 'FACE_PHOTO';
  final allowed = isPhoto ? const {'jpg', 'jpeg', 'png'} : const {'jpg', 'jpeg', 'png', 'pdf'};
  if (!allowed.contains(ext)) {
    return isPhoto
        ? 'Foto profil harus berformat JPG atau PNG.'
        : 'Berkas harus berformat JPG, PNG, atau PDF.';
  }
  if (sizeBytes <= 0) return 'Berkas kosong.';
  if (sizeBytes > maxDocumentBytes) return 'Ukuran berkas maksimal 5 MB.';
  return null;
}

/// Profil verifikasi mitra (`GET/PUT /midwife/profile`).
class MidwifeProfile {
  const MidwifeProfile({
    required this.verificationStatus,
    this.nikMasked,
    this.sipNumber,
    this.sipIssuer,
    this.sipExpiresOn,
    this.practiceArea,
    this.reviewReason,
    this.uploadedDocuments = const {},
  });

  factory MidwifeProfile.fromJson(JsonMap j) => MidwifeProfile(
        verificationStatus: '${j['verification_status'] ?? ''}',
        nikMasked: strOrNull(j['nik']),
        sipNumber: strOrNull(j['sip_number']),
        sipIssuer: strOrNull(j['sip_issuer']),
        sipExpiresOn: strOrNull(j['sip_expires_on']),
        practiceArea: strOrNull(j['practice_area']),
        reviewReason: strOrNull(j['review_reason']),
        uploadedDocuments: rowsOf(j['documents']).map((d) => '${d['type']}').toSet(),
      );

  /// Status yang masih boleh diubah (`MidwifeProfileController::EDITABLE`).
  static const editableStatuses = {'DRAFT', 'NEEDS_CORRECTION', 'EXPIRED'};

  final String verificationStatus;
  final String? nikMasked;
  final String? sipNumber;
  final String? sipIssuer;
  final String? sipExpiresOn;
  final String? practiceArea;
  final String? reviewReason;

  /// Jenis dokumen yang sudah diunggah (tanpa isi berkas).
  final Set<String> uploadedDocuments;

  bool get editable => editableStatuses.contains(verificationStatus);
  bool get isActive => verificationStatus == 'ACTIVE';

  /// Kelengkapan yang disyaratkan `POST /midwife/verification/submit`.
  List<String> get missingItems {
    bool blank(String? v) => v == null || v.trim().isEmpty;
    return [
      if (blank(nikMasked)) 'NIK',
      if (blank(sipNumber)) 'Nomor SIP',
      if (blank(sipIssuer)) 'Penerbit SIP',
      if (blank(sipExpiresOn)) 'Tanggal berlaku SIP',
      if (blank(practiceArea)) 'Wilayah praktik',
      for (final t in documentTypes)
        if (!uploadedDocuments.contains(t)) documentLabels[t] ?? t,
    ];
  }
}

class ServicePoint {
  const ServicePoint({required this.lat, required this.lng, required this.radiusKm});

  final double lat;
  final double lng;
  final int radiusKm;

  Map<String, dynamic> toJson() => {'lat': lat, 'lng': lng, 'radius_km': radiusKm};
}

/// Mengurai isian titik layanan. Kedua koordinat kosong berarti titik dihapus.
({ServicePoint? point, String? error}) parseServicePoint(String lat, String lng, String radius) {
  final a = lat.trim();
  final b = lng.trim();
  if (a.isEmpty && b.isEmpty) return (point: null, error: null);
  final x = double.tryParse(a.replaceAll(',', '.'));
  final y = double.tryParse(b.replaceAll(',', '.'));
  if (x == null || y == null) {
    return (point: null, error: 'Latitude dan longitude harus berupa angka.');
  }
  if (x < -90 || x > 90) return (point: null, error: 'Latitude harus antara -90 dan 90.');
  if (y < -180 || y > 180) return (point: null, error: 'Longitude harus antara -180 dan 180.');
  final r = int.tryParse(radius.trim());
  if (r == null || r < 1 || r > 100) {
    return (point: null, error: 'Radius layanan harus 1 sampai 100 km.');
  }
  return (point: ServicePoint(lat: x, lng: y, radiusKm: r), error: null);
}

/// Layanan, wilayah, jadwal, titik layanan, dan status menerima pesanan
/// (`GET/PUT /midwife/offerings`).
class MidwifeOfferings {
  const MidwifeOfferings({
    required this.acceptingRequests,
    this.serviceIds = const {},
    this.areaIds = const {},
    this.availability = const {},
    this.servicePoint,
  });

  factory MidwifeOfferings.fromJson(JsonMap j) {
    final avail = <int, Set<int>>{};
    for (final v in rowsOf(j['availability'])) {
      final day = int.tryParse('${v['weekday']}');
      if (day == null) continue;
      avail[day] = (v['hours'] as List? ?? const [])
          .map((x) => int.tryParse('$x'))
          .whereType<int>()
          .toSet();
    }
    ServicePoint? point;
    final sp = j['service_point'];
    if (sp is Map) {
      final lat = double.tryParse('${sp['lat']}');
      final lng = double.tryParse('${sp['lng']}');
      final radius = int.tryParse('${sp['radius_km']}');
      if (lat != null && lng != null && radius != null) {
        point = ServicePoint(lat: lat, lng: lng, radiusKm: radius);
      }
    }
    return MidwifeOfferings(
      acceptingRequests: j['accepting_requests'] == true,
      serviceIds: (j['service_ids'] as List? ?? const []).map((x) => '$x').toSet(),
      areaIds: (j['area_ids'] as List? ?? const []).map((x) => '$x').toSet(),
      availability: avail,
      servicePoint: point,
    );
  }

  final bool acceptingRequests;
  final Set<String> serviceIds;
  final Set<String> areaIds;

  /// Kunci: hari (0 = Minggu, mengikuti `extract(dow ...)` di backend).
  final Map<int, Set<int>> availability;
  final ServicePoint? servicePoint;

  /// Payload `PUT /midwife/offerings`. `accepting_requests` sengaja tidak
  /// disertakan: status tersebut diubah lewat [MidwifeRepository.setAccepting].
  Map<String, dynamic> toSaveBody() {
    final days = availability.keys.where((d) => availability[d]!.isNotEmpty).toList()..sort();
    return {
      'service_ids': serviceIds.toList()..sort(),
      'area_ids': areaIds.toList()..sort(),
      'availability': [
        for (final d in days) {'weekday': d, 'hours': availability[d]!.toList()..sort()},
      ],
      'service_point': servicePoint?.toJson(),
    };
  }
}

class CatalogItem {
  const CatalogItem({required this.id, required this.name});

  factory CatalogItem.fromJson(JsonMap j) => CatalogItem(id: '${j['id']}', name: '${j['name'] ?? ''}');

  final String id;
  final String name;
}

/// Pesanan dari sudut pandang mitra. Alamat dan kontak pasien hanya terisi bila
/// backend mengizinkan (status CONFIRMED atau IN_PROGRESS).
class PartnerBooking {
  const PartnerBooking({
    required this.id,
    required this.code,
    required this.status,
    this.service,
    this.area,
    this.scheduledStart,
    this.distanceKm,
    this.offerExpiresAt,
    this.addressLine,
    this.landmark,
    this.contactPhone,
  });

  factory PartnerBooking.fromJson(JsonMap j) => PartnerBooking(
        id: '${j['id'] ?? ''}',
        code: '${j['code'] ?? j['id'] ?? ''}',
        status: '${j['status'] ?? ''}',
        service: strOrNull(j['service']),
        area: strOrNull(j['area']),
        scheduledStart: DateTime.tryParse('${j['scheduled_start'] ?? ''}'),
        distanceKm: (j['distance_km'] as num?)?.toDouble(),
        offerExpiresAt: DateTime.tryParse('${j['offer_expires_at'] ?? ''}'),
        addressLine: strOrNull(j['address_line']),
        landmark: strOrNull(j['landmark']),
        contactPhone: strOrNull(j['contact_phone']),
      );

  final String id;
  final String code;
  final String status;
  final String? service;
  final String? area;
  final DateTime? scheduledStart;
  final double? distanceKm;
  final DateTime? offerExpiresAt;
  final String? addressLine;
  final String? landmark;
  final String? contactPhone;
}

/// Tindakan mitra pada pesanan; `name` dipakai langsung sebagai segmen URL
/// `/midwife/bookings/{id}/{action}`.
enum BookingAction { accept, decline, start, complete }
