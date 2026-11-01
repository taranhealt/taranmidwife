import 'package:flutter_test/flutter_test.dart';
import 'package:taranmidwife_mitra/main.dart';

void main() {
  testWidgets('shows safe setup guidance when HTTPS API URL is missing', (tester) async {
    await tester.pumpWidget(const MitraApp());
    expect(find.text('Konfigurasi belum tersedia'), findsOneWidget);
    expect(find.textContaining('API_BASE_URL HTTPS'), findsOneWidget);
  });
}
