import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:taran_core/taran_core.dart';

import '../config.dart';
import '../open_url.dart';
import '../widgets/emergency_note.dart';

class ProfileTab extends StatelessWidget {
  const ProfileTab({super.key});

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthController>().user;
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: ListTile(
            leading: const Icon(Icons.person),
            title: Text(user?.name ?? '-'),
            subtitle: Text(user?.email ?? '-'),
          ),
        ),
        const SizedBox(height: 8),
        const EmergencyNote(),
        const SizedBox(height: 8),
        Card(
          child: Column(
            children: [
              ListTile(
                leading: const Icon(Icons.privacy_tip_outlined),
                title: const Text('Kebijakan Privasi'),
                onTap: () => openUrl(context, AppConfig.privacyUrl),
              ),
              ListTile(
                leading: const Icon(Icons.delete_outline),
                title: const Text('Hapus akun'),
                subtitle: const Text('Menghapus data pribadi Anda dari akun ini.'),
                onTap: () => showDialog<void>(context: context, builder: (_) => const _DeleteDialog()),
              ),
              ListTile(
                leading: const Icon(Icons.open_in_new),
                title: const Text('Permintaan hapus akun lewat web'),
                onTap: () => openUrl(context, AppConfig.deletionUrl),
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        SizedBox(
          width: double.infinity,
          child: OutlinedButton.icon(
            onPressed: () => context.read<AuthController>().logout(),
            icon: const Icon(Icons.logout),
            label: const Text('Keluar'),
          ),
        ),
        const SizedBox(height: 16),
        const Center(child: Text('TaranMidwife 0.1.0', style: TextStyle(fontSize: 12))),
      ],
    );
  }
}

class _DeleteDialog extends StatefulWidget {
  const _DeleteDialog();

  @override
  State<_DeleteDialog> createState() => _DeleteDialogState();
}

class _DeleteDialogState extends State<_DeleteDialog> {
  final _password = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _password.dispose();
    super.dispose();
  }

  Future<void> _delete() async {
    if (_password.text.isEmpty) {
      setState(() => _error = 'Masukkan kata sandi untuk konfirmasi.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    final auth = context.read<AuthController>();
    final navigator = Navigator.of(context);
    try {
      await auth.deleteAccount(_password.text);
      navigator.pop();
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _error = e.code == 'ACTIVE_BOOKINGS'
              ? 'Masih ada pesanan yang berjalan. Selesaikan atau batalkan dulu sebelum menghapus akun.'
              : e.displayMessage;
          _busy = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Hapus akun?'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Nama, email, telepon, dan alamat pada pesanan Anda akan dihapus atau dianonimkan. '
            'Tindakan ini tidak dapat dibatalkan.',
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _password,
            obscureText: true,
            decoration: const InputDecoration(labelText: 'Kata sandi'),
          ),
          if (_error != null) ...[
            const SizedBox(height: 8),
            Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
          ],
        ],
      ),
      actions: [
        TextButton(onPressed: _busy ? null : () => Navigator.of(context).pop(), child: const Text('Batal')),
        FilledButton(onPressed: _busy ? null : _delete, child: const Text('Hapus akun')),
      ],
    );
  }
}
