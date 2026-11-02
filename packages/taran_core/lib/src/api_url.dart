/// Validasi URL dasar API yang diberikan saat build (`--dart-define=API_BASE_URL=...`).
///
/// API wajib HTTPS dan tidak boleh memuat kredensial, query, maupun fragment.
/// Satu aturan ini dipakai bersama oleh aplikasi pasien dan mitra agar kedua
/// aplikasi tidak pernah berbeda perilaku pada konfigurasi yang sama.
bool isValidApiBaseUrl(String value) {
  final u = Uri.tryParse(value);
  return u != null &&
      u.scheme == 'https' &&
      u.host.isNotEmpty &&
      u.userInfo.isEmpty &&
      u.query.isEmpty &&
      u.fragment.isEmpty;
}
