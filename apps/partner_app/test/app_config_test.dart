import 'package:flutter_test/flutter_test.dart';
import 'package:taranmidwife_mitra/main.dart';

void main() {
  testWidgets('shows safe setup guidance when HTTPS API URL is missing', (tester) async {
    await tester.pumpWidget(const MitraApp());
    expect(find.text('Konfigurasi belum tersedia'), findsOneWidget);
    expect(find.textContaining('API_BASE_URL HTTPS'), findsOneWidget);
  });

  test('isValidApiUrl hanya menerima HTTPS bersih', () {
    expect(isValidApiUrl('https://api.example.test/api/v1'), isTrue);
    expect(isValidApiUrl(''), isFalse);
    expect(isValidApiUrl('http://api.example.test/api/v1'), isFalse);
    expect(isValidApiUrl('https://user:pw@api.example.test'), isFalse);
    expect(isValidApiUrl('https://api.example.test/api/v1?x=1'), isFalse);
    expect(isValidApiUrl('https://api.example.test/api/v1#frag'), isFalse);
  });
}
