import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:taran_core/taran_core.dart';

import 'config.dart';
import 'pages/auth_page.dart';
import 'pages/home_shell.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  final tokens = SecureTokenStore();
  final api = ApiClient(baseUrl: AppConfig.apiBaseUrl, tokens: tokens);
  final auth = AuthController(api, tokens);
  runApp(PatientApp(api: api, auth: auth));
}

class PatientApp extends StatelessWidget {
  const PatientApp({super.key, required this.api, required this.auth});
  final ApiClient api;
  final AuthController auth;

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        Provider<ApiClient>.value(value: api),
        ChangeNotifierProvider<AuthController>.value(value: auth),
      ],
      child: MaterialApp(
        title: 'TaranMidwife',
        debugShowCheckedModeBanner: false,
        theme: taranTheme(),
        home: const AuthGate(),
      ),
    );
  }
}

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
    final auth = context.watch<AuthController>();
    switch (auth.status) {
      case AuthStatus.unknown:
        return const Scaffold(body: Center(child: CircularProgressIndicator()));
      case AuthStatus.signedOut:
        return const AuthPage();
      case AuthStatus.signedIn:
        if (auth.user?.role != 'patient') return const WrongRolePage();
        return const HomeShell();
    }
  }
}

class WrongRolePage extends StatelessWidget {
  const WrongRolePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Text(
                'Akun ini bukan akun pasien. Gunakan aplikasi TaranMidwife Mitra untuk akun bidan.',
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () => context.read<AuthController>().logout(),
                child: const Text('Keluar'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
