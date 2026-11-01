import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:taran_core/taran_core.dart';

import '../data/midwife_repository.dart';
import '../widgets/retry_view.dart';

class BookingsTab extends StatefulWidget {
  const BookingsTab({super.key});

  @override
  State<BookingsTab> createState() => _BookingsTabState();
}

class _BookingsTabState extends State<BookingsTab> {
  List<PartnerBooking> rows = const [];
  MidwifeProfile? profile;
  bool loading = true;
  String? error;

  /// Id pesanan yang sedang diproses; mencegah permintaan ganda karena tap berulang.
  String? busyId;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    final repo = context.read<MidwifeRepository>();
    try {
      final list = await repo.bookings();
      MidwifeProfile? p;
      try {
        p = await repo.profile();
      } on ApiException {
        p = null; // Banner status bersifat tambahan; daftar pesanan tetap tampil.
      }
      if (!mounted) return;
      setState(() {
        rows = list;
        profile = p;
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

  Future<void> act(PartnerBooking b, BookingAction action) async {
    if (busyId != null) return;
    final repo = context.read<MidwifeRepository>();
    final messenger = ScaffoldMessenger.of(context);

    if (action == BookingAction.decline || action == BookingAction.complete) {
      final isDecline = action == BookingAction.decline;
      final ok = await confirmAction(
        context,
        title: isDecline ? 'Tolak pesanan?' : 'Selesaikan kunjungan?',
        message: isDecline
            ? 'Pesanan akan dikembalikan agar ditawarkan ke mitra lain.'
            : 'Pastikan kunjungan benar-benar telah selesai.',
        confirmLabel: isDecline ? 'Tolak' : 'Selesaikan',
      );
      if (!ok || !mounted) return;
    }

    setState(() => busyId = b.id);
    try {
      await repo.bookingAction(b.id, action);
    } on ApiException catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.displayMessage)));
    }
    // Selalu ambil ulang dari server: status bisa berubah (mis. tawaran kedaluwarsa).
    await load();
    if (mounted) setState(() => busyId = null);
  }

  List<Widget> actionsFor(PartnerBooking b) {
    final disabled = busyId != null;
    switch (b.status) {
      case 'OFFERED':
        return [
          OutlinedButton(
            onPressed: disabled ? null : () => act(b, BookingAction.decline),
            child: const Text('Tolak'),
          ),
          FilledButton(
            onPressed: disabled ? null : () => act(b, BookingAction.accept),
            child: const Text('Terima'),
          ),
        ];
      case 'CONFIRMED':
        return [
          FilledButton(
            onPressed: disabled ? null : () => act(b, BookingAction.start),
            child: const Text('Mulai kunjungan'),
          ),
        ];
      case 'IN_PROGRESS':
        return [
          FilledButton(
            onPressed: disabled ? null : () => act(b, BookingAction.complete),
            child: const Text('Selesaikan'),
          ),
        ];
      default:
        return const [];
    }
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const Center(child: CircularProgressIndicator());
    if (error != null) {
      return RetryView(
        message: error!,
        retry: () {
          setState(() => loading = true);
          load();
        },
      );
    }
    final p = profile;
    return RefreshIndicator(
      onRefresh: load,
      child: ListView(
        padding: const EdgeInsets.all(12),
        children: [
          if (p != null && !p.isActive)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Text(
                  'Akun mitra belum aktif (status: ${verificationLabel(p.verificationStatus)}). '
                  'Menerima, memulai, dan menyelesaikan pesanan akan ditolak sistem hingga akun aktif.',
                ),
              ),
            ),
          if (rows.isEmpty)
            const Padding(
              padding: EdgeInsets.all(32),
              child: Center(child: Text('Belum ada pesanan yang ditugaskan.')),
            ),
          for (final b in rows) _BookingCard(booking: b, actions: actionsFor(b)),
        ],
      ),
    );
  }
}

class _BookingCard extends StatelessWidget {
  const _BookingCard({required this.booking, required this.actions});

  final PartnerBooking booking;
  final List<Widget> actions;

  @override
  Widget build(BuildContext context) {
    final b = booking;
    final start = b.scheduledStart;
    final expiry = b.offerExpiresAt;
    final landmark = b.landmark;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(b.service ?? 'Layanan', style: Theme.of(context).textTheme.titleMedium),
            StatusBadge(b.status),
            Text('Kode: ${b.code}'),
            if (start != null) Text('Jadwal: ${formatWib(start)}'),
            if (b.area != null) Text('Wilayah: ${b.area}'),
            if (b.distanceKm != null) Text('Jarak: ${b.distanceKm} km'),
            if (expiry != null) Text('Batas respons: ${formatWib(expiry)}'),
            if (b.addressLine != null) Text('Alamat kunjungan: ${b.addressLine}'),
            if (landmark != null && landmark.isNotEmpty) Text('Patokan: $landmark'),
            if (b.contactPhone != null) Text('Kontak pasien: ${b.contactPhone}'),
            if (actions.isNotEmpty) ...[
              const SizedBox(height: 8),
              Wrap(spacing: 8, children: actions),
            ],
          ],
        ),
      ),
    );
  }
}
