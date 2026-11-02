import 'package:taran_core/taran_core.dart';

// Pembantu penguraian JSON bersama untuk lapisan data Partner App.

String? strOrNull(Object? v) => v == null ? null : '$v';

/// Mengambil objek `data` dari respons API; galat bila bentuknya tidak sesuai.
JsonMap dataOf(JsonMap response) {
  final d = response['data'];
  if (d is Map) return Map<String, dynamic>.from(d);
  throw const ApiException(status: 500, message: 'Respons server tidak lengkap.');
}

List<JsonMap> rowsOf(Object? raw) => (raw as List? ?? const [])
    .whereType<Map>()
    .map((e) => Map<String, dynamic>.from(e))
    .toList();
