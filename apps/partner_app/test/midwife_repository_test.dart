import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:taran_core/taran_core.dart';
import 'package:taranmidwife_mitra/data/midwife_repository.dart';

const _json = {'content-type': 'application/json'};

MidwifeRepository _repo(MockClientHandler handler) => MidwifeRepository(ApiClient(
      baseUrl: 'https://x.test/api/v1',
      tokens: MemoryTokenStore(),
      httpClient: MockClient(handler),
    ));

http.Response _ok(Object body) => http.Response(jsonEncode(body), 200, headers: _json);

void main() {
  group('validateDocument', () {
    test('menerima JPG, PNG, PDF dalam batas ukuran', () {
      for (final name in ['a.jpg', 'a.JPEG', 'a.png', 'scan.PDF']) {
        expect(validateDocument(type: 'SIP', fileName: name, sizeBytes: 1000), isNull, reason: name);
      }
      expect(validateDocument(type: 'STR', fileName: 'a.pdf', sizeBytes: maxDocumentBytes), isNull);
    });

    test('foto profil tidak boleh PDF', () {
      expect(validateDocument(type: 'FACE_PHOTO', fileName: 'a.pdf', sizeBytes: 1000), isNotNull);
      expect(validateDocument(type: 'FACE_PHOTO', fileName: 'a.png', sizeBytes: 1000), isNull);
    });

    test('menolak ekstensi, ukuran, dan jenis yang tidak valid', () {
      expect(validateDocument(type: 'SIP', fileName: 'a.exe', sizeBytes: 1000), isNotNull);
      expect(validateDocument(type: 'SIP', fileName: 'tanpa_ekstensi', sizeBytes: 1000), isNotNull);
      expect(validateDocument(type: 'SIP', fileName: 'a.pdf', sizeBytes: 0), isNotNull);
      expect(validateDocument(type: 'SIP', fileName: 'a.pdf', sizeBytes: maxDocumentBytes + 1), isNotNull);
      expect(validateDocument(type: 'LAINNYA', fileName: 'a.pdf', sizeBytes: 1000), isNotNull);
    });
  });

  group('profileBody', () {
    test('hanya memuat isian yang terisi dan memangkas spasi', () {
      final body = MidwifeRepository.profileBody(sipNumber: '  SIP-1 ', practiceArea: 'Bekasi');
      expect(body, {'sip_number': 'SIP-1', 'practice_area': 'Bekasi'});
    });

    test('kosong bila tidak ada isian', () {
      expect(MidwifeRepository.profileBody(sipNumber: '   '), isEmpty);
    });
  });

  group('parseServicePoint', () {
    test('kedua koordinat kosong berarti titik dihapus', () {
      final r = parseServicePoint('', ' ', '20');
      expect(r.point, isNull);
      expect(r.error, isNull);
    });

    test('mengurai koordinat valid, termasuk koma desimal', () {
      final r = parseServicePoint('-6,2', '106.8', '15');
      expect(r.error, isNull);
      expect(r.point!.lat, -6.2);
      expect(r.point!.lng, 106.8);
      expect(r.point!.radiusKm, 15);
    });

    test('menolak nilai di luar batas atau tidak lengkap', () {
      expect(parseServicePoint('-6.2', '', '20').error, isNotNull);
      expect(parseServicePoint('abc', '106', '20').error, isNotNull);
      expect(parseServicePoint('91', '106', '20').error, isNotNull);
      expect(parseServicePoint('-6', '181', '20').error, isNotNull);
      expect(parseServicePoint('-6', '106', '0').error, isNotNull);
      expect(parseServicePoint('-6', '106', '101').error, isNotNull);
      expect(parseServicePoint('-6', '106', 'x').error, isNotNull);
    });
  });

  group('model', () {
    test('MidwifeProfile menghitung kelengkapan sesuai syarat pengajuan', () {
      final empty = MidwifeProfile.fromJson({'verification_status': 'DRAFT', 'documents': []});
      expect(empty.editable, isTrue);
      expect(empty.isActive, isFalse);
      expect(empty.missingItems.length, 9); // 5 isian + 4 dokumen

      final complete = MidwifeProfile.fromJson({
        'verification_status': 'DRAFT',
        'nik': '************3456',
        'sip_number': 'S1',
        'sip_issuer': 'Dinkes',
        'sip_expires_on': '2030-01-01',
        'practice_area': 'Bekasi',
        'documents': [
          {'type': 'SIP'},
          {'type': 'STR'},
          {'type': 'IDENTITY'},
          {'type': 'FACE_PHOTO'},
        ],
      });
      expect(complete.missingItems, isEmpty);
    });

    test('profil terkunci pada status yang tidak boleh diedit', () {
      for (final s in ['SUBMITTED', 'IN_REVIEW', 'APPROVED', 'ACTIVE', 'REJECTED', 'SUSPENDED']) {
        expect(MidwifeProfile.fromJson({'verification_status': s}).editable, isFalse, reason: s);
      }
      for (final s in ['DRAFT', 'NEEDS_CORRECTION', 'EXPIRED']) {
        expect(MidwifeProfile.fromJson({'verification_status': s}).editable, isTrue, reason: s);
      }
    });

    test('MidwifeOfferings.toSaveBody terurut, tanpa hari kosong, tanpa accepting_requests', () {
      const o = MidwifeOfferings(
        acceptingRequests: true,
        serviceIds: {'b', 'a'},
        areaIds: {'z'},
        availability: {
          3: {14, 8},
          1: {},
          0: {10},
        },
        servicePoint: ServicePoint(lat: -6.2, lng: 106.8, radiusKm: 20),
      );
      expect(o.toSaveBody(), {
        'service_ids': ['a', 'b'],
        'area_ids': ['z'],
        'availability': [
          {'weekday': 0, 'hours': [10]},
          {'weekday': 3, 'hours': [8, 14]},
        ],
        'service_point': {'lat': -6.2, 'lng': 106.8, 'radius_km': 20},
      });
    });

    test('service_point null dikirim eksplisit agar titik dihapus di server', () {
      const o = MidwifeOfferings(acceptingRequests: false);
      final body = o.toSaveBody();
      expect(body.containsKey('service_point'), isTrue);
      expect(body['service_point'], isNull);
    });

    test('MidwifeOfferings.fromJson membaca respons backend', () {
      final o = MidwifeOfferings.fromJson({
        'accepting_requests': true,
        'service_ids': ['s1'],
        'area_ids': ['a1', 'a2'],
        'availability': [
          {'weekday': 2, 'hours': [8, 10]},
        ],
        'service_point': {'lat': -6.2, 'lng': 106.8, 'radius_km': 25},
      });
      expect(o.acceptingRequests, isTrue);
      expect(o.serviceIds, {'s1'});
      expect(o.areaIds, {'a1', 'a2'});
      expect(o.availability[2], {8, 10});
      expect(o.servicePoint!.radiusKm, 25);
      expect(MidwifeOfferings.fromJson({'service_point': null}).servicePoint, isNull);
    });
  });

  group('MidwifeRepository (kontrak endpoint)', () {
    test('bookings membaca respons paginasi dan tidak mengisi kontak bila tidak dikirim', () async {
      late http.Request seen;
      final repo = _repo((req) async {
        seen = req;
        return _ok({
          'data': [
            {
              'id': 'B1',
              'code': 'TM-1',
              'status': 'OFFERED',
              'service': 'Kunjungan',
              'scheduled_start': '2026-11-02T01:00:00+00:00',
              'distance_km': 2.5,
              'offer_expires_at': '2026-11-01T15:00:00+00:00',
            },
          ],
        });
      });
      final list = await repo.bookings();
      expect(seen.method, 'GET');
      expect(seen.url.path, '/api/v1/bookings');
      expect(seen.url.queryParameters['per_page'], '50');
      expect(list.single.status, 'OFFERED');
      expect(list.single.distanceKm, 2.5);
      expect(list.single.offerExpiresAt, isNotNull);
      expect(list.single.contactPhone, isNull);
      expect(list.single.addressLine, isNull);
    });

    test('bookingAction memanggil POST /midwife/bookings/{id}/{aksi}', () async {
      final seen = <String>[];
      final repo = _repo((req) async {
        seen.add('${req.method} ${req.url.path}');
        return _ok({'data': {}});
      });
      for (final a in BookingAction.values) {
        await repo.bookingAction('B1', a);
      }
      expect(seen, [
        'POST /api/v1/midwife/bookings/B1/accept',
        'POST /api/v1/midwife/bookings/B1/decline',
        'POST /api/v1/midwife/bookings/B1/start',
        'POST /api/v1/midwife/bookings/B1/complete',
      ]);
    });

    test('setAccepting hanya mengirim accepting_requests', () async {
      late http.Request seen;
      final repo = _repo((req) async {
        seen = req;
        return _ok({
          'data': {'accepting_requests': false, 'service_ids': [], 'area_ids': [], 'availability': []},
        });
      });
      final o = await repo.setAccepting(false);
      expect(seen.method, 'PUT');
      expect(seen.url.path, '/api/v1/midwife/offerings');
      expect(jsonDecode(seen.body), {'accepting_requests': false});
      expect(o.acceptingRequests, isFalse);
    });

    test('updateProfile memakai PUT /midwife/profile dan mengurai hasil', () async {
      late http.Request seen;
      final repo = _repo((req) async {
        seen = req;
        return _ok({
          'data': {'verification_status': 'DRAFT', 'sip_number': 'S1', 'documents': []},
        });
      });
      final p = await repo.updateProfile({'sip_number': 'S1'});
      expect(seen.method, 'PUT');
      expect(seen.url.path, '/api/v1/midwife/profile');
      expect(jsonDecode(seen.body), {'sip_number': 'S1'});
      expect(p.sipNumber, 'S1');
    });

    test('submitVerification memakai POST /midwife/verification/submit', () async {
      late http.Request seen;
      final repo = _repo((req) async {
        seen = req;
        return _ok({
          'data': {'verification_status': 'SUBMITTED', 'documents': []},
        });
      });
      final p = await repo.submitVerification();
      expect(seen.method, 'POST');
      expect(seen.url.path, '/api/v1/midwife/verification/submit');
      expect(p.verificationStatus, 'SUBMITTED');
      expect(p.editable, isFalse);
    });

    test('galat backend diteruskan sebagai ApiException (mis. PROFILE_LOCKED)', () async {
      final repo = _repo((req) async => http.Response(
            jsonEncode({'code': 'PROFILE_LOCKED', 'message': 'Data tidak dapat diubah pada status ini.'}),
            409,
            headers: _json,
          ));
      try {
        await repo.updateProfile({'sip_number': 'S1'});
        fail('harus melempar');
      } on ApiException catch (e) {
        expect(e.status, 409);
        expect(e.code, 'PROFILE_LOCKED');
      }
    });

    test('respons tanpa kunci data ditolak', () async {
      final repo = _repo((req) async => _ok({'foo': 'bar'}));
      expect(repo.profile(), throwsA(isA<ApiException>()));
    });
  });
}
