import 'package:flutter_test/flutter_test.dart';
import 'package:taranmidwife_mitra/data/midwife_models.dart';

void main() {
  test('model dapat diimpor langsung tanpa lapisan jaringan', () {
    final offerings = MidwifeOfferings.fromJson({
      'accepting_requests': true,
      'service_ids': ['s1'],
      'area_ids': ['a1'],
      'availability': [
        {'weekday': 1, 'hours': [10, 8]},
      ],
      'service_point': {'lat': 3.5, 'lng': 98.6, 'radius_km': 7},
    });
    final body = offerings.toSaveBody();
    expect(body['service_ids'], ['s1']);
    expect(body.containsKey('accepting_requests'), isFalse);
    expect((body['availability'] as List).single, {'weekday': 1, 'hours': [8, 10]});
    expect((body['service_point'] as Map)['radius_km'], 7);
  });

  test('profil lengkap tidak memiliki butir kurang', () {
    final p = MidwifeProfile.fromJson({
      'verification_status': 'DRAFT',
      'nik': '************3456',
      'sip_number': 'SIP-1',
      'sip_issuer': 'Dinas',
      'sip_expires_on': '2030-01-01',
      'practice_area': 'Medan',
      'documents': [
        {'type': 'SIP'},
        {'type': 'STR'},
        {'type': 'IDENTITY'},
        {'type': 'FACE_PHOTO'},
      ],
    });
    expect(p.missingItems, isEmpty);
    expect(p.editable, isTrue);
  });

  test('titik layanan: kosong menghapus, di luar rentang ditolak', () {
    expect(parseServicePoint('', '', '').point, isNull);
    expect(parseServicePoint('', '', '').error, isNull);
    expect(parseServicePoint('91', '98', '5').error, isNotNull);
    expect(parseServicePoint('3,5', '98,6', '101').error, isNotNull);
    expect(parseServicePoint('3,5', '98,6', '5').point?.radiusKm, 5);
  });
}
