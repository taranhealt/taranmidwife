import 'dart:io';

import 'package:taran_core/taran_core.dart';

import 'json_helpers.dart';
import 'midwife_models.dart';

// Model dan konstanta tetap dapat diimpor dari berkas ini.
export 'midwife_models.dart';

/// Lapisan data Partner App.
///
/// Semua jalur endpoint `/midwife/*` dipusatkan di sini agar UI tidak
/// mengetahui detail kontrak API, dan agar kontrak dapat diuji tanpa membangun
/// widget. Backend (Laravel) tetap menjadi sumber kebenaran: validasi di sisi
/// aplikasi hanya untuk pengalaman pengguna yang lebih baik.
class MidwifeRepository {
  MidwifeRepository(this._api);

  final ApiClient _api;

  Future<List<PartnerBooking>> bookings() async {
    final r = await _api.send('GET', '/bookings', query: {'per_page': '50'});
    return rowsOf(r['data']).map(PartnerBooking.fromJson).toList();
  }

  Future<void> bookingAction(String id, BookingAction action) async {
    await _api.send('POST', '/midwife/bookings/${Uri.encodeComponent(id)}/${action.name}');
  }

  Future<MidwifeProfile> profile() async =>
      MidwifeProfile.fromJson(dataOf(await _api.send('GET', '/midwife/profile')));

  /// Hanya isian yang tidak kosong yang dikirim; backend memakai `sometimes`.
  static Map<String, dynamic> profileBody({
    String nik = '',
    String sipNumber = '',
    String sipIssuer = '',
    String sipExpiresOn = '',
    String practiceArea = '',
  }) {
    final body = <String, dynamic>{};
    void put(String key, String value) {
      final v = value.trim();
      if (v.isNotEmpty) body[key] = v;
    }

    put('nik', nik);
    put('sip_number', sipNumber);
    put('sip_issuer', sipIssuer);
    put('sip_expires_on', sipExpiresOn);
    put('practice_area', practiceArea);
    return body;
  }

  Future<MidwifeProfile> updateProfile(Map<String, dynamic> body) async =>
      MidwifeProfile.fromJson(dataOf(await _api.send('PUT', '/midwife/profile', body: body)));

  Future<void> uploadDocument(String type, File file) async {
    await _api.uploadFile('/midwife/documents', field: 'file', file: file, fields: {'type': type});
  }

  Future<MidwifeProfile> submitVerification() async =>
      MidwifeProfile.fromJson(dataOf(await _api.send('POST', '/midwife/verification/submit')));

  Future<MidwifeOfferings> offerings() async =>
      MidwifeOfferings.fromJson(dataOf(await _api.send('GET', '/midwife/offerings')));

  Future<MidwifeOfferings> saveOfferings(MidwifeOfferings o) async => MidwifeOfferings.fromJson(
      dataOf(await _api.send('PUT', '/midwife/offerings', body: o.toSaveBody())));

  Future<MidwifeOfferings> setAccepting(bool value) async => MidwifeOfferings.fromJson(
      dataOf(await _api.send('PUT', '/midwife/offerings', body: {'accepting_requests': value})));

  Future<List<CatalogItem>> services() async =>
      rowsOf((await _api.send('GET', '/services', auth: false))['data']).map(CatalogItem.fromJson).toList();

  Future<List<CatalogItem>> areas() async =>
      rowsOf((await _api.send('GET', '/areas', auth: false))['data']).map(CatalogItem.fromJson).toList();
}
