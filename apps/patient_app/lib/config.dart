import 'package:taran_core/taran_core.dart';

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

  static bool get hasValidApiBaseUrl => isValidApiBaseUrl(apiBaseUrl.trim());
}
