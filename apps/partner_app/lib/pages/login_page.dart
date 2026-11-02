import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:taran_core/taran_core.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final email = TextEditingController();
  final password = TextEditingController();
  bool busy = false;
  bool hidden = true;
  String? error;

  @override
  void dispose() {
    email.dispose();
    password.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    if (!email.text.contains('@') || password.text.isEmpty) {
      setState(() => error = 'Masukkan email dan kata sandi.');
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await context.read<AuthController>().login(email: email.text.trim(), password: password.text);
    } on ApiException catch (e) {
      if (mounted) setState(() => error = e.displayMessage);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        body: SafeArea(
          child: ListView(
            padding: const EdgeInsets.all(24),
            children: [
              const SizedBox(height: 48),
              Text('TaranMidwife Mitra', style: Theme.of(context).textTheme.headlineMedium),
              const Text('Kelola layanan dan kunjungan kebidanan Anda.'),
              const SizedBox(height: 32),
              TextField(
                controller: email,
                keyboardType: TextInputType.emailAddress,
                autofillHints: const [AutofillHints.email],
                decoration: const InputDecoration(labelText: 'Email'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: password,
                obscureText: hidden,
                autofillHints: const [AutofillHints.password],
                decoration: InputDecoration(
                  labelText: 'Kata sandi',
                  suffixIcon: IconButton(
                    tooltip: hidden ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi',
                    onPressed: () => setState(() => hidden = !hidden),
                    icon: Icon(hidden ? Icons.visibility : Icons.visibility_off),
                  ),
                ),
              ),
              if (error != null)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                ),
              const SizedBox(height: 20),
              FilledButton(
                onPressed: busy ? null : submit,
                child: busy
                    ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Text('Masuk'),
              ),
              const SizedBox(height: 12),
              const Text('Akun mitra dibuat melalui proses pendaftaran dan verifikasi resmi.'),
            ],
          ),
        ),
      );
}
