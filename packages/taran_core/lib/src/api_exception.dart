class ApiException implements Exception {
  const ApiException({
    required this.status,
    required this.message,
    this.code,
    this.fieldErrors = const {},
  });

  factory ApiException.fromResponse(int status, Map<String, dynamic> data) {
    final errors = <String, List<String>>{};
    final raw = data['errors'];
    if (raw is Map) {
      raw.forEach((key, value) {
        if (value is List && value.isNotEmpty) {
          errors['$key'] = value.map((e) => '$e').toList();
        }
      });
    }
    final message = data['message'];
    final code = data['code'];
    return ApiException(
      status: status,
      code: code is String ? code : null,
      message: message is String && message.isNotEmpty ? message : _fallback(status),
      fieldErrors: errors,
    );
  }

  final int status;
  final String message;
  final String? code;
  final Map<String, List<String>> fieldErrors;

  bool get isNetwork => status == 0;

  String get displayMessage {
    for (final list in fieldErrors.values) {
      if (list.isNotEmpty) return list.first;
    }
    return message;
  }

  @override
  String toString() => 'ApiException($status, $code, $message)';
}

String _fallback(int status) {
  if (status == 429) return 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.';
  if (status == 403) return 'Anda tidak memiliki akses untuk tindakan ini.';
  if (status == 404) return 'Data tidak ditemukan.';
  if (status >= 500) return 'Terjadi gangguan pada server. Coba lagi beberapa saat.';
  return 'Permintaan gagal (kode $status).';
}
