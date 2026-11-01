import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:taran_core/taran_core.dart';

import 'data/midwife_repository.dart';
import 'pages/home_shell.dart';
import 'pages/login_page.dart';
import 'widgets/retry_view.dart';

const apiUrl = String.fromEnvironment('API_BASE_URL', defaultValue: '');

/// API wajib HTTPS tanpa kredensial, query, maupun fragment.
bool isValidApiUrl(String value) {
  final u = Uri.tryParse(value);
  return u != null &&
      u.scheme == 'https' &&
      u.host.isNotEmpty &&
      u.userInfo.isEmpty &&
      u.query.isEmpty &&
      u.fragment.isEmpty;
}

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  final tokens = SecureTokenStore();
  final api = ApiClient(baseUrl: apiUrl, tokens: tokens);
  runApp(MultiProvider(
    providers: [
      Provider<ApiClient>.value(value: api),
      Provider<MidwifeRepository>.value(value: MidwifeRepository(api)),
      ChangeNotifierProvider(create: (_) => AuthController(api, tokens)),
    ],
    child: const MitraApp(),
  ));
}

class MitraApp extends StatelessWidget {
  const MitraApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'TaranMidwife Mitra',
      debugShowCheckedModeBanner: false,
      theme: taranTheme(),
      home: isValidApiUrl(apiUrl)
          ? const AuthGate()
          : const MessagePage(
              title: 'Konfigurasi belum tersedia',
              message: 'Build mitra membutuhkan API_BASE_URL HTTPS yang valid.',
            ),
    );
  }
}

/// Role guard: hanya akun dengan role `partner` yang boleh masuk ke aplikasi.
/// Backend tetap menegakkan role pada setiap endpoint `/midwife/*`.
class AuthGate extends StatefulWidget {
  const AuthGate({super.key});

  @override
  State<AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<AuthGate> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) context.read<AuthController>().bootstrap();
    });
  }

  @override
  Widget build(BuildContext context) {
    final a = context.watch<AuthController>();
    if (a.status == AuthStatus.unknown) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (a.status == AuthStatus.signedOut) return const LoginPage();
    if (a.user?.role != 'partner') {
      return MessagePage(
        title: 'Akun bukan mitra',
        message: 'Aplikasi ini hanya untuk akun mitra/bidan terdaftar.',
        action: FilledButton(
          onPressed: () => context.read<AuthController>().logout(),
          child: const Text('Keluar'),
        ),
      );
    }
    return const MitraHome();
  }
}
