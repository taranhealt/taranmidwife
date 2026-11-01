import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:taran_core/taran_core.dart';

import '../widgets/retry_view.dart';

class OrdersTab extends StatefulWidget {
  const OrdersTab({super.key});

  @override
  State<OrdersTab> createState() => _OrdersTabState();
}

class _OrdersTabState extends State<OrdersTab> {
  List<BookingItem>? _items;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final r = await context.read<ApiClient>().send('GET', '/bookings', query: {'per_page': '50'});
      final list = (r['data'] as List? ?? const [])
          .map((e) => BookingItem.fromJson(e as Map<String, dynamic>))
          .toList();
      if (mounted) {
        setState(() {
          _items = list;
          _error = null;
          _loading = false;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _error = e.displayMessage;
          _loading = false;
        });
      }
    }
  }

  void _retry() {
    setState(() {
      _loading = true;
      _error = null;
    });
    _load();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) return RetryView(message: _error!, onRetry: _retry);
    final items = _items ?? const <BookingItem>[];
    return RefreshIndicator(
      onRefresh: _load,
      child: items.isEmpty
          ? ListView(children: const [
              Padding(
                padding: EdgeInsets.all(32),
                child: Center(child: Text('Belum ada pesanan. Pilih layanan di Beranda untuk memesan.')),
              ),
            ])
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                for (final b in items)
                  Card(
                    child: ListTile(
                      title: Text('${b.service ?? 'Layanan'} · ${b.code}'),
                      subtitle: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(formatWib(b.scheduledStart)),
                          StatusBadge(b.status),
                        ],
                      ),
                      onTap: () => showModalBottomSheet<void>(
                        context: context,
                        isScrollControlled: true,
                        builder: (_) => _BookingSheet(booking: b, onChanged: _load),
                      ),
                    ),
                  ),
              ],
            ),
    );
  }
}

class _BookingSheet extends StatefulWidget {
  const _BookingSheet({required this.booking, required this.onChanged});
  final BookingItem booking;
  final Future<void> Function() onChanged;

  @override
  State<_BookingSheet> createState() => _BookingSheetState();
}

class _BookingSheetState extends State<_BookingSheet> {
  bool _busy = false;
  String? _error;

  Future<void> _cancel() async {
    final reason = await showDialog<String>(context: context, builder: (_) => const _CancelDialog());
    if (reason == null || !mounted) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    final api = context.read<ApiClient>();
    final navigator = Navigator.of(context);
    try {
      await api.send(
        'POST',
        '/bookings/${widget.booking.id}/cancel',
        body: reason.isEmpty ? <String, dynamic>{} : {'reason': reason},
      );
      navigator.pop();
      await widget.onChanged();
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _error = e.displayMessage;
          _busy = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final b = widget.booking;
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.fromLTRB(16, 16, 16, 16 + MediaQuery.of(context).viewInsets.bottom),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(b.service ?? 'Layanan', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 4),
            Text('Kode: ${b.code}'),
            Text('Jadwal: ${formatWib(b.scheduledStart)}'),
            if (b.area != null) Text('Wilayah: ${b.area}'),
            Text('Biaya: ${formatRupiah(b.priceAmount)}'),
            if (b.addressLine != null) Text('Alamat: ${b.addressLine}'),
            const SizedBox(height: 8),
            StatusBadge(b.status),
            if (_error != null) ...[
              const SizedBox(height: 8),
              Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ],
            if (cancellableStatuses.contains(b.status)) ...[
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton(
                  onPressed: _busy ? null : _cancel,
                  child: const Text('Batalkan pesanan'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _CancelDialog extends StatefulWidget {
  const _CancelDialog();

  @override
  State<_CancelDialog> createState() => _CancelDialogState();
}

class _CancelDialogState extends State<_CancelDialog> {
  final _reason = TextEditingController();

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Batalkan pesanan?'),
      content: TextField(
        controller: _reason,
        maxLength: 200,
        decoration: const InputDecoration(labelText: 'Alasan (opsional)'),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Kembali')),
        FilledButton(
          onPressed: () => Navigator.of(context).pop(_reason.text.trim()),
          child: const Text('Batalkan pesanan'),
        ),
      ],
    );
  }
}
