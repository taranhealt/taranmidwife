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
                          Text('Pembayaran: ${_paymentLabel(b.paymentStatus)}'),
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
  bool _paymentLoading = true;
  String? _paymentError;
  Map<String, dynamic>? _payment;
  bool _reviewLoading = false;
  bool _reviewSubmitting = false;
  String? _reviewError;
  Map<String, dynamic>? _review;
  int _rating = 5;
  final TextEditingController _reviewComment = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadPayment();
    if (widget.booking.status == 'COMPLETED') _loadReview();
  }

  @override
  void dispose() {
    _reviewComment.dispose();
    super.dispose();
  }

  Future<void> _loadReview() async {
    setState(() => _reviewLoading = true);
    try {
      final result = await context.read<ApiClient>().send('GET', '/bookings/${widget.booking.id}/review');
      if (mounted) setState(() { _review = result['data'] as Map<String, dynamic>?; _reviewError = null; _reviewLoading = false; });
    } on ApiException catch (e) {
      if (mounted) setState(() { _reviewError = e.displayMessage; _reviewLoading = false; });
    }
  }

  Future<void> _submitReview() async {
    if (_reviewSubmitting || _review != null) return;
    setState(() { _reviewSubmitting = true; _reviewError = null; });
    try {
      final result = await context.read<ApiClient>().send('POST', '/bookings/${widget.booking.id}/review', body: {
        'rating': _rating, 'comment': _reviewComment.text.trim().isEmpty ? null : _reviewComment.text.trim(),
      });
      if (mounted) setState(() { _review = result['data'] as Map<String, dynamic>?; _reviewError = null; });
    } on ApiException catch (e) {
      if (mounted) setState(() => _reviewError = e.displayMessage);
    } finally {
      if (mounted) setState(() => _reviewSubmitting = false);
    }
  }

  Future<void> _loadPayment() async {
    try {
      final result = await context.read<ApiClient>().send('GET', '/bookings/${widget.booking.id}/payment');
      if (mounted) {
        setState(() {
          _payment = result['data'] as Map<String, dynamic>?;
          _paymentError = null;
          _paymentLoading = false;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _paymentError = e.displayMessage;
          _paymentLoading = false;
        });
      }
    }
  }

  Future<void> _reportComplaint() async {
    final report = await showDialog<Map<String, String>>(
      context: context,
      builder: (_) => const _ComplaintDialog(),
    );
    if (report == null || !mounted) return;
    setState(() { _busy = true; _error = null; });
    try {
      final result = await context.read<ApiClient>().send(
        'POST',
        '/complaints',
        body: {
          'booking_id': widget.booking.id,
          'category': report['category'],
          'description': report['description'],
        },
      );
      if (!mounted) return;
      final notice = result['notice'] as String?;
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Pengaduan terkirim'),
          content: Text(notice ?? 'Pengaduan Anda telah diterima dan akan ditinjau.'),
          actions: [TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Tutup'),
          )],
        ),
      );
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.displayMessage);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

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

  Widget _paymentSection() {
    if (_paymentLoading) return const LinearProgressIndicator();
    if (_paymentError != null) {
      return Row(children: [
        Expanded(child: Text('Riwayat pembayaran: $_paymentError')),
        TextButton(onPressed: () {
          setState(() => _paymentLoading = true);
          _loadPayment();
        }, child: const Text('Coba lagi')),
      ]);
    }
    final data = _payment ?? const <String, dynamic>{};
    final history = data['history'] as List? ?? const [];
    final status = data['status'] as String? ?? widget.booking.paymentStatus;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Pembayaran: ${_paymentLabel(status)}', style: const TextStyle(fontWeight: FontWeight.w600)),
        if (history.isEmpty)
          const Text('Belum ada perubahan pembayaran yang dicatat.')
        else
          for (final item in history)
            if (item is Map<String, dynamic>)
              Text('${_paymentLabel(item['to_status'] as String? ?? 'UNPAID')}'
                  '${item['method'] == null ? '' : ' · ${item['method']}'}'
                  '${item['occurred_at'] == null ? '' : ' · ${formatWib(DateTime.parse(item['occurred_at'] as String))}'}'),
      ],
    );
  }
  Widget _reviewSection() {
    if (widget.booking.status != 'COMPLETED') return const SizedBox.shrink();
    if (_reviewLoading) return const LinearProgressIndicator();
    if (_reviewError != null && _review == null) {
      return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('Ulasan: $_reviewError'),
        TextButton(onPressed: _loadReview, child: const Text('Coba lagi')),
      ]);
    }
    if (_review != null) {
      return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('Terima kasih atas ulasan Anda.'),
        Text('Rating Anda: ${_review!['rating']}/5'),
      ]);
    }
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('Bagaimana pengalaman layanan Anda?', style: TextStyle(fontWeight: FontWeight.w600)),
      Row(children: [for (var star = 1; star <= 5; star++) IconButton(
        tooltip: '$star dari 5 bintang',
        onPressed: _reviewSubmitting ? null : () => setState(() => _rating = star),
        icon: Icon(star <= _rating ? Icons.star : Icons.star_border),
      )]),
      TextField(controller: _reviewComment, maxLength: 500, maxLines: 3,
        decoration: const InputDecoration(labelText: 'Komentar (opsional)', alignLabelWithHint: true)),
      if (_reviewError != null) Text(_reviewError!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
      SizedBox(width: double.infinity, child: FilledButton(
        onPressed: _reviewSubmitting ? null : _submitReview,
        child: _reviewSubmitting ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator()) : const Text('Kirim ulasan'),
      )),
    ]);
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
            const SizedBox(height: 8),
            _paymentSection(),
            const SizedBox(height: 12),
            _reviewSection(),
            if (_error != null) ...[
              const SizedBox(height: 8),
              Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ],
            const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: _busy ? null : _reportComplaint,
                icon: const Icon(Icons.flag_outlined),
                label: const Text('Laporkan masalah layanan'),
              ),
            ),
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


class _ComplaintDialog extends StatefulWidget {
  const _ComplaintDialog();

  @override
  State<_ComplaintDialog> createState() => _ComplaintDialogState();
}

class _ComplaintDialogState extends State<_ComplaintDialog> {
  final _description = TextEditingController();
  String _category = 'SERVICE_QUALITY';

  static const _categories = <String, String>{
    'SERVICE_QUALITY': 'Kualitas layanan',
    'PARTNER_CONDUCT': 'Perilaku mitra/bidan',
    'SCHEDULING': 'Jadwal kunjungan',
    'PRICE_OR_PAYMENT': 'Biaya atau pembayaran',
    'PRIVACY': 'Privasi dan data pribadi',
    'SAFETY': 'Keselamatan',
    'OTHER': 'Lainnya',
  };

  @override
  void dispose() {
    _description.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Laporkan masalah'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            DropdownButtonFormField<String>(
              initialValue: _category,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'Kategori'),
              items: _categories.entries.map((e) =>
                DropdownMenuItem(value: e.key, child: Text(e.value))).toList(),
              onChanged: (value) {
                if (value != null) setState(() => _category = value);
              },
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _description,
              onChanged: (_) => setState(() {}),
              minLines: 3,
              maxLines: 5,
              maxLength: 1000,
              decoration: const InputDecoration(
                labelText: 'Jelaskan masalah (minimal 10 karakter)',
                alignLabelWithHint: true,
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Batal'),
        ),
        FilledButton(
          onPressed: _description.text.trim().length < 10 ? null : () =>
            Navigator.of(context).pop({
              'category': _category,
              'description': _description.text.trim(),
            }),
          child: const Text('Kirim pengaduan'),
        ),
      ],
    );
  }
}

String _paymentLabel(String status) => switch (status) {
  'PAID' => 'Sudah dibayar',
  'CANCELLED' => 'Dibatalkan',
  _ => 'Belum dibayar',
};
