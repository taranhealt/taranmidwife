import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:taran_core/taran_core.dart';
import 'package:taranmidwife/pages/auth_page.dart';

void main() {
  testWidgets('halaman masuk tampil dan memvalidasi isian', (tester) async {
    tester.view.physicalSize = const Size(800, 1600);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    final tokens = MemoryTokenStore();
    final api = ApiClient(baseUrl: 'https://x.test/api/v1', tokens: tokens);
    final auth = AuthController(api, tokens);
    await tester.pumpWidget(
      MultiProvider(
        providers: [
          Provider<ApiClient>.value(value: api),
          ChangeNotifierProvider<AuthController>.value(value: auth),
        ],
        child: MaterialApp(theme: taranTheme(), home: const AuthPage()),
      ),
    );
    expect(find.text('TaranMidwife'), findsOneWidget);
    await tester.tap(find.widgetWithText(FilledButton, 'Masuk'));
    await tester.pump();
    expect(find.text('Email tidak valid'), findsOneWidget);
    expect(find.text('Kata sandi wajib diisi'), findsOneWidget);
  });
}
