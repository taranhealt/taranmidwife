import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:taran_core/taran_core.dart';

import '../config.dart';
import '../open_url.dart';
import '../widgets/emergency_note.dart';

class AuthPage extends StatefulWidget {
  const AuthPage({super.key});

  @override
  State<AuthPage> createState() => _AuthPageState();
}

class _AuthPageState extends State<AuthPage> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  bool _register = false;
  bool _busy = false;
  bool _agree = false;
  bool _hidden = true;
  String? _error;

  @override
  void dispose() {
    for (final c in [_name, _email, _phone, _password, _confirm]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    if (_register && !_agree) {
      setState(() => _error = 'Setujui Kebijakan Privasi untuk melanjutkan.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    final auth = context.read<AuthController>();
    try {
      if (_register) {
        final phone = _phone.text.trim();
        await auth.register(
          name: _name.text.trim(),
          email: _email.text.trim(),
          password: _password.text,
          passwordConfirmation: _confirm.text,
          phone: phone.isEmpty ? null : phone,
          role: 'patient',
        );
      } else {
        await auth.login(email: _email.text.trim(), password: _password.text);
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.displayMessage);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _forgotPassword() async {
    final email = _email.text.trim();
    if (!email.contains('@')) {
      setState(() => _error = 'Masukkan email akun terlebih dahulu.');
      return;
    }
    setState(() { _busy = true; _error = null; });
    try {
      final auth = context.read<AuthController>();
      final result = await auth.api.send(
        'POST',
        '/auth/forgot-password',
        body: {'email': email},
        auth: false,
      );
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Periksa email Anda'),
          content: Text(result['message'] as String? ??
              'Jika email terdaftar, tautan pengaturan ulang kata sandi telah dikirim.'),
          actions: [TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Mengerti'),
          )],
        ),
      );
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.displayMessage);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            const SizedBox(height: 12),
            Text(
              'TaranMidwife',
              style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: TaranColors.navy,
                  ),
            ),
            const SizedBox(height: 4),
            const Text('Pesan kunjungan bidan ke rumah.'),
            const SizedBox(height: 16),
            const EmergencyNote(),
            const SizedBox(height: 16),
            Form(
              key: _form,
              child: Column(
                children: [
                  if (_register) ...[
                    TextFormField(
                      controller: _name,
                      decoration: const InputDecoration(labelText: 'Nama lengkap'),
                      textCapitalization: TextCapitalization.words,
                      validator: (v) => (v == null || v.trim().isEmpty) ? 'Nama wajib diisi' : null,
                    ),
                    const SizedBox(height: 12),
                  ],
                  TextFormField(
                    controller: _email,
                    decoration: const InputDecoration(labelText: 'Email'),
                    keyboardType: TextInputType.emailAddress,
                    autofillHints: const [AutofillHints.email],
                    validator: (v) => (v == null || !v.contains('@')) ? 'Email tidak valid' : null,
                  ),
                  const SizedBox(height: 12),
                  if (_register) ...[
                    TextFormField(
                      controller: _phone,
                      decoration: const InputDecoration(labelText: 'Nomor telepon (opsional)'),
                      keyboardType: TextInputType.phone,
                      validator: (v) {
                        final t = (v ?? '').trim();
                        if (t.isEmpty) return null;
                        return RegExp(r'^\+?[0-9]{8,15}$').hasMatch(t)
                            ? null
                            : 'Nomor telepon 8-15 angka';
                      },
                    ),
                    const SizedBox(height: 12),
                  ],
                  TextFormField(
                    controller: _password,
                    obscureText: _hidden,
                    decoration: InputDecoration(
                      labelText: 'Kata sandi',
                      suffixIcon: IconButton(
                        tooltip: _hidden ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi',
                        icon: Icon(_hidden ? Icons.visibility : Icons.visibility_off),
                        onPressed: () => setState(() => _hidden = !_hidden),
                      ),
                    ),
                    validator: (v) {
                      final t = v ?? '';
                      if (_register) return t.length < 10 ? 'Kata sandi minimal 10 karakter' : null;
                      return t.isEmpty ? 'Kata sandi wajib diisi' : null;
                    },
                  ),
                  if (_register) ...[
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _confirm,
                      obscureText: _hidden,
                      decoration: const InputDecoration(labelText: 'Ulangi kata sandi'),
                      validator: (v) => v == _password.text ? null : 'Kata sandi tidak sama',
                    ),
                    CheckboxListTile(
                      contentPadding: EdgeInsets.zero,
                      controlAffinity: ListTileControlAffinity.leading,
                      value: _agree,
                      onChanged: (v) => setState(() => _agree = v ?? false),
                      title: const Text('Saya menyetujui Kebijakan Privasi dan ketentuan layanan TaranMidwife.'),
                    ),
                    Align(
                      alignment: Alignment.centerLeft,
                      child: TextButton(
                        onPressed: () => openUrl(context, AppConfig.privacyUrl),
                        child: const Text('Baca Kebijakan Privasi'),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            if (_error != null) ...[
              const SizedBox(height: 8),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.error_outline, color: Theme.of(context).colorScheme.error),
                  const SizedBox(width: 8),
                  Expanded(child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
                ],
              ),
            ],
            const SizedBox(height: 16),
            SizedBox(
              height: 48,
              child: FilledButton(
                onPressed: _busy ? null : _submit,
                child: _busy
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                    : Text(_register ? 'Daftar' : 'Masuk'),
              ),
            ),
            if (!_register)
              TextButton(
                onPressed: _busy ? null : _forgotPassword,
                child: const Text('Lupa kata sandi?'),
              ),
            TextButton(
              onPressed: _busy
                  ? null
                  : () => setState(() {
                        _register = !_register;
                        _error = null;
                      }),
              child: Text(_register ? 'Sudah punya akun? Masuk' : 'Belum punya akun? Daftar'),
            ),
          ],
        ),
      ),
    );
  }
}
