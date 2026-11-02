import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:taran_core/taran_core.dart';

import '../data/midwife_repository.dart';
import '../widgets/retry_view.dart';

class ProfileTab extends StatefulWidget {
  const ProfileTab({super.key});

  @override
  State<ProfileTab> createState() => _ProfileTabState();
}

class _ProfileTabState extends State<ProfileTab> {
  MidwifeProfile? profile;
  bool loading = true;
  bool saving = false;
  String? error;

  final nik = TextEditingController();
  final sip = TextEditingController();
  final issuer = TextEditingController();
  final expiry = TextEditingController();
  final area = TextEditingController();

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    for (final c in [nik, sip, issuer, expiry, area]) {
      c.dispose();
    }
    super.dispose();
  }

  void _apply(MidwifeProfile p) {
    profile = p;
    nik.clear(); // NIK dari server selalu dimasking; tidak pernah diisikan kembali.
    sip.text = p.sipNumber ?? '';
    issuer.text = p.sipIssuer ?? '';
    expiry.text = p.sipExpiresOn ?? '';
    area.text = p.practiceArea ?? '';
  }

  Future<void> load() async {
    try {
      final p = await context.read<MidwifeRepository>().profile();
      if (!mounted) return;
      setState(() {
        _apply(p);
        loading = false;
        error = null;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        error = e.displayMessage;
        loading = false;
      });
    }
  }

  void _toast(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  /// Menjalankan [job] dengan penanda `saving`, menampilkan galat API sebagai snackbar.
  Future<void> _run(Future<void> Function() job) async {
    setState(() => saving = true);
    try {
      await job();
    } on ApiException catch (e) {
      _toast(e.displayMessage);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Future<void> pickExpiry() async {
    final now = DateTime.now();
    final tomorrow = DateTime(now.year, now.month, now.day).add(const Duration(days: 1));
    final current = DateTime.tryParse(expiry.text);
    final picked = await showDatePicker(
      context: context,
      initialDate: current != null && !current.isBefore(tomorrow) ? current : tomorrow,
      firstDate: tomorrow, // Backend mewajibkan tanggal setelah hari ini.
      lastDate: tomorrow.add(const Duration(days: 3650)),
    );
    if (picked == null) return;
    expiry.text = '${picked.year}-${pad2(picked.month)}-${pad2(picked.day)}';
  }

  Future<void> saveProfile() async {
    final n = nik.text.trim();
    if (n.isNotEmpty && !RegExp(r'^\d{16}$').hasMatch(n)) {
      _toast('NIK harus 16 digit.');
      return;
    }
    final body = MidwifeRepository.profileBody(
      nik: n,
      sipNumber: sip.text,
      sipIssuer: issuer.text,
      sipExpiresOn: expiry.text,
      practiceArea: area.text,
    );
    if (body.isEmpty) {
      _toast('Isi minimal satu data sebelum menyimpan.');
      return;
    }
    final repo = context.read<MidwifeRepository>();
    await _run(() async {
      final saved = await repo.updateProfile(body);
      if (!mounted) return;
      setState(() => _apply(saved));
      _toast('Profil disimpan.');
    });
  }

  Future<void> upload(String type) async {
    final repo = context.read<MidwifeRepository>();
    final picked = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: type == 'FACE_PHOTO' ? ['jpg', 'jpeg', 'png'] : ['jpg', 'jpeg', 'png', 'pdf'],
    );
    final file = picked?.files.firstOrNull;
    final path = file?.path;
    if (file == null || path == null || !mounted) return;

    final problem = validateDocument(type: type, fileName: file.name, sizeBytes: file.size);
    if (problem != null) {
      _toast(problem);
      return;
    }
    await _run(() async {
      await repo.uploadDocument(type, File(path));
      final fresh = await repo.profile();
      if (!mounted) return;
      setState(() => _apply(fresh));
      _toast('${documentLabels[type]} berhasil diunggah.');
    });
  }

  Future<void> submitVerification() async {
    final ok = await confirmAction(
      context,
      title: 'Ajukan verifikasi?',
      message: 'Setelah diajukan, data dan dokumen tidak dapat diubah sampai peninjauan selesai.',
      confirmLabel: 'Ajukan',
    );
    if (!ok || !mounted) return;
    final repo = context.read<MidwifeRepository>();
    await _run(() async {
      final saved = await repo.submitVerification();
      if (!mounted) return;
      setState(() => _apply(saved));
      _toast('Pengajuan verifikasi terkirim.');
    });
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const Center(child: CircularProgressIndicator());
    final p = profile;
    if (error != null || p == null) {
      return RetryView(
        message: error ?? 'Profil tidak tersedia.',
        retry: () {
          setState(() => loading = true);
          load();
        },
      );
    }
    final canEdit = p.editable && !saving;
    final missing = p.missingItems;
    final reason = p.reviewReason;
    final nikHint = p.nikMasked ?? 'Belum diisi';

    return RefreshIndicator(
      onRefresh: load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            'Status verifikasi: ${verificationLabel(p.verificationStatus)}',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          if (reason != null && reason.isNotEmpty) Text('Catatan koreksi: $reason'),
          if (!p.editable) const Text('Data terkunci pada status ini.'),
          const SizedBox(height: 8),
          TextField(
            controller: nik,
            keyboardType: TextInputType.number,
            maxLength: 16,
            enabled: canEdit,
            decoration: InputDecoration(
              labelText: 'NIK (16 digit)',
              helperText: 'Tersimpan: $nikHint. Kosongkan jika tidak diubah.',
            ),
          ),
          TextField(
            controller: sip,
            enabled: canEdit,
            decoration: const InputDecoration(labelText: 'Nomor SIP'),
          ),
          TextField(
            controller: issuer,
            enabled: canEdit,
            decoration: const InputDecoration(labelText: 'Penerbit SIP'),
          ),
          TextField(
            controller: expiry,
            enabled: canEdit,
            readOnly: true,
            onTap: pickExpiry,
            decoration: const InputDecoration(
              labelText: 'Tanggal berlaku SIP',
              suffixIcon: Icon(Icons.calendar_today),
            ),
          ),
          TextField(
            controller: area,
            enabled: canEdit,
            decoration: const InputDecoration(labelText: 'Wilayah praktik'),
          ),
          const SizedBox(height: 8),
          if (p.editable) FilledButton(onPressed: saving ? null : saveProfile, child: const Text('Simpan profil')),
          const Divider(height: 32),
          Text('Dokumen verifikasi', style: Theme.of(context).textTheme.titleMedium),
          for (final t in documentTypes)
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(documentLabels[t] ?? t),
              subtitle: Text(p.uploadedDocuments.contains(t) ? 'Sudah diunggah' : 'Belum diunggah'),
              trailing: p.editable
                  ? IconButton(
                      tooltip: 'Unggah ${documentLabels[t]}',
                      onPressed: saving ? null : () => upload(t),
                      icon: const Icon(Icons.upload_file),
                    )
                  : Icon(p.uploadedDocuments.contains(t) ? Icons.check_circle : Icons.lock_outline),
            ),
          const Text('Format JPG, PNG, atau PDF (foto profil: JPG atau PNG), maksimal 5 MB.'),
          if (p.editable) ...[
            const SizedBox(height: 12),
            if (missing.isNotEmpty) Text('Belum lengkap: ${missing.join(', ')}.'),
            FilledButton(
              onPressed: saving ? null : submitVerification,
              child: const Text('Ajukan verifikasi'),
            ),
          ],
          const SizedBox(height: 12),
          OutlinedButton(
            onPressed: () => context.read<AuthController>().logout(),
            child: const Text('Keluar'),
          ),
        ],
      ),
    );
  }
}
