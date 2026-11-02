import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:taran_core/taran_core.dart';
import 'package:taranmidwife_mitra/data/midwife_repository.dart';
import 'package:taranmidwife_mitra/main.dart';

const _json = {'content-type': 'application/json'};

/// Token tersimpan sejak awal, sehingga bootstrap langsung memanggil `/me`.
class _SignedInTokens extends MemoryTokenStore {
  @override
  Future<String?> read() async => 'token-uji';
}

http.Response _ok(Object body) => http.Response(jsonEncode(body), 200, headers: _json);

Widget _app(MockClientHandler handler) {
  final tokens = _SignedInTokens();
  final api = ApiClient(baseUrl: 'https://x.test/api/v1', tokens: tokens, httpClient: MockClient(handler));
  return MultiProvider(
    providers: [
      Provider<ApiClient>.value(value: api),
      Provider<MidwifeRepository>.value(value: MidwifeRepository(api)),
      ChangeNotifierProvider(create: (_) => AuthController(api, tokens)),
    ],
    child: const MaterialApp(home: AuthGate()),
  );
}

Map<String, dynamic> _user(String role) =>
    {'id': 1, 'name': 'Uji', 'email': 'uji@example.test', 'role': role};

void main() {
  testWidgets('role guard menolak akun non-mitra', (tester) async {
    await tester.pumpWidget(_app((req) async {
      if (req.url.path.endsWith('/me')) return _ok({'user': _user('patient')});
      return http.Response('{}', 404, headers: _json);
    }));
    await tester.pumpAndSettle();

    expect(find.text('Akun bukan mitra'), findsOneWidget);
    expect(find.text('Keluar'), findsOneWidget);
    expect(find.text('Pesanan'), findsNothing);
  });

  testWidgets('akun mitra masuk ke beranda dan melihat banner akun belum aktif', (tester) async {
    await tester.pumpWidget(_app((req) async {
      final path = req.url.path;
      if (path.endsWith('/me')) return _ok({'user': _user('partner')});
      if (path.endsWith('/bookings')) return _ok({'data': []});
      if (path.endsWith('/midwife/profile')) {
        return _ok({
          'data': {'verification_status': 'DRAFT', 'documents': []},
        });
      }
      if (path.endsWith('/midwife/offerings')) {
        return _ok({
          'data': {
            'accepting_requests': false,
            'service_ids': [],
            'area_ids': [],
            'availability': [],
            'service_point': null,
          },
        });
      }
      if (path.endsWith('/services') || path.endsWith('/areas')) return _ok({'data': []});
      return http.Response('{}', 404, headers: _json);
    }));
    await tester.pumpAndSettle();

    expect(find.text('Akun bukan mitra'), findsNothing);
    expect(find.text('Belum ada pesanan yang ditugaskan.'), findsOneWidget);
    expect(find.textContaining('belum aktif'), findsOneWidget);
    expect(find.text('Pesanan'), findsOneWidget);
    expect(find.text('Profil'), findsOneWidget);
    expect(find.text('Layanan'), findsOneWidget);
  });
}
