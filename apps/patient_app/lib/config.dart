class AppConfig {
  static const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: '',
  );
  static const privacyUrl = String.fromEnvironment(
    'PRIVACY_URL',
    defaultValue: 'https://midwife.taranrainmaker.biz.id/kebijakan-privasi',
  );
  static const deletionUrl = String.fromEnvironment(
    'DELETION_URL',
    defaultValue: 'https://midwife.taranrainmaker.biz.id/hapus-akun',
  );

  static bool get hasValidApiBaseUrl {
    final value = apiBaseUrl.trim();
    if (value.isEmpty) return false;

    final uri = Uri.tryParse(value);
    return uri != null &&
        uri.scheme == 'https' &&
        uri.host.isNotEmpty &&
        uri.userInfo.isEmpty &&
        uri.query.isEmpty &&
        uri.fragment.isEmpty;
  }
}
