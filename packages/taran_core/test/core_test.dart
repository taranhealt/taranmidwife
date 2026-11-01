import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:taran_core/taran_core.dart';

const _json = {'content-type': 'application/json'};

void main() {
  test('mengirim token dan mengurai respons', () async {
    final tokens = MemoryTokenStore();
    await tokens.write('abc');
    late http.Request seen;
    final client = MockClient((req) async {
      seen = req;
      return http.Response('{"user":{"id":1}}', 200, headers: _json);
    });
    final api = ApiClient(baseUrl: 'https://x.test/api/v1', tokens: tokens, httpClient: client);
    final r = await api.send('GET', '/me');
    expect(seen.headers['Authorization'], 'Bearer abc');
    expect(seen.url.toString(), 'https://x.test/api/v1/me');
    expect((r['user'] as Map)['id'], 1);
  });

  test('galat 422 diurai menjadi pesan per kolom', () async {
    final client = MockClient((req) async => http.Response(
        '{"message":"x","errors":{"email":["salah"]}}', 422,
        headers: _json));
    final api = ApiClient(baseUrl: 'https://x.test/api/v1', tokens: MemoryTokenStore(), httpClient: client);
    try {
      await api.send('POST', '/auth/login', body: {}, auth: false);
      fail('harus melempar');
    } on ApiException catch (e) {
      expect(e.status, 422);
      expect(e.fieldErrors['email'], ['salah']);
      expect(e.displayMessage, 'salah');
    }
  });

  test('401 menghapus token dan memanggil onUnauthorized', () async {
    final tokens = MemoryTokenStore();
    await tokens.write('abc');
    var called = false;
    final client = MockClient((req) async => http.Response('{"message":"Unauthenticated."}', 401, headers: _json));
    final api = ApiClient(
      baseUrl: 'https://x.test/api/v1',
      tokens: tokens,
      httpClient: client,
      onUnauthorized: () => called = true,
    );
    await expectLater(api.send('GET', '/me'), throwsA(isA<ApiException>()));
    expect(called, isTrue);
    expect(await tokens.read(), isNull);
  });

  test('timeout mencakup pembacaan seluruh response body', () async {
    final client = _DelayedBodyClient();
    final api = ApiClient(
      baseUrl: 'https://x.test/api/v1',
      tokens: MemoryTokenStore(),
      httpClient: client,
      requestTimeout: const Duration(milliseconds: 10),
    );
    await expectLater(
      api.send('GET', '/me', auth: false),
      throwsA(isA<ApiException>().having((e) => e.status, 'status', 0)),
    );
  });

  test('kegagalan jaringan menjadi ApiException status 0', () async {
    final client = MockClient((req) async => throw Exception('putus'));
    final api = ApiClient(baseUrl: 'https://x.test/api/v1', tokens: MemoryTokenStore(), httpClient: client);
    try {
      await api.send('GET', '/services', auth: false);
      fail('harus melempar');
    } on ApiException catch (e) {
      expect(e.isNetwork, isTrue);
    }
  });

  test('login menyimpan token dan pengguna; logout membersihkan', () async {
    final tokens = MemoryTokenStore();
    final client = MockClient((req) async {
      if (req.url.path.endsWith('/auth/login')) {
        expect(req.headers.containsKey('Authorization'), isFalse);
        return http.Response(
            '{"token":"t1","user":{"id":5,"name":"A","email":"a@x.test","role":"patient","verification_status":null}}',
            200,
            headers: _json);
      }
      return http.Response('{"message":"ok"}', 200, headers: _json);
    });
    final api = ApiClient(baseUrl: 'https://x.test/api/v1', tokens: tokens, httpClient: client);
    final auth = AuthController(api, tokens);
    await auth.login(email: 'a@x.test', password: 'rahasia-uji-123');
    expect(auth.status, AuthStatus.signedIn);
    expect(auth.user?.role, 'patient');
    expect(await tokens.read(), 't1');
    await auth.logout();
    expect(auth.status, AuthStatus.signedOut);
    expect(await tokens.read(), isNull);
  });

  test('format rupiah dan waktu WIB', () {
    expect(formatRupiah(100000), 'Rp100.000');
    expect(formatRupiah(950), 'Rp950');
    expect(formatWib(DateTime.utc(2026, 10, 28, 3, 0)), '28/10/2026 10:00 WIB');
  });

  test('model pesanan dan label status', () {
    final b = BookingItem.fromJson({
      'id': 'x', 'code': 'TM-1', 'status': 'CONFIRMED',
      'scheduled_start': '2026-11-05T03:00:00+00:00', 'price_amount': 100000,
      'service': 'Layanan', 'area': 'Wilayah',
    });
    expect(b.priceAmount, 100000);
    expect(b.addressLine, isNull);
    expect(bookingStatusInfo('CONFIRMED').label, 'Dikonfirmasi');
    expect(cancellableStatuses.contains('COMPLETED'), isFalse);
  });

  test('isValidApiBaseUrl hanya menerima HTTPS bersih', () {
    expect(isValidApiBaseUrl('https://api.example.test/api/v1'), isTrue);
    expect(isValidApiBaseUrl(''), isFalse);
    expect(isValidApiBaseUrl('http://api.example.test/api/v1'), isFalse);
    expect(isValidApiBaseUrl('https://user:pw@api.example.test'), isFalse);
    expect(isValidApiBaseUrl('https://api.example.test/api/v1?x=1'), isFalse);
    expect(isValidApiBaseUrl('https://api.example.test/api/v1#frag'), isFalse);
  });

  test('respons non-JSON pada galat server tetap menjadi ApiException', () async {
    final client = MockClient((req) async => http.Response('<html>502</html>', 502));
    final api = ApiClient(baseUrl: 'https://x.test/api/v1', tokens: MemoryTokenStore(), httpClient: client);
    try {
      await api.send('GET', '/me');
      fail('harus melempar');
    } on ApiException catch (e) {
      expect(e.status, 502);
    }
  });

  test('401 pada permintaan tanpa auth tidak menghapus token', () async {
    final tokens = MemoryTokenStore();
    await tokens.write('abc');
    var called = false;
    final client = MockClient((req) async => http.Response('{"message":"salah"}', 401, headers: _json));
    final api = ApiClient(
      baseUrl: 'https://x.test/api/v1',
      tokens: tokens,
      httpClient: client,
      onUnauthorized: () => called = true,
    );
    await expectLater(api.send('POST', '/auth/login', body: {}, auth: false), throwsA(isA<ApiException>()));
    expect(await tokens.read(), 'abc');
    expect(called, isFalse);
  });
}


class _DelayedBodyClient extends http.BaseClient {
  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) async {
    final bytes = utf8.encode('{}');
    final body = Stream<List<int>>.fromFuture(
      Future<List<int>>.delayed(const Duration(milliseconds: 100), () => bytes),
    );
    return http.StreamedResponse(body, 200);
  }
}
