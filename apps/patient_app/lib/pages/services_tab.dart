import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:taran_core/taran_core.dart';

import '../widgets/emergency_note.dart';
import '../widgets/retry_view.dart';
import 'booking_form_page.dart';

class ServicesTab extends StatefulWidget {
  const ServicesTab({super.key, required this.onBooked});
  final VoidCallback onBooked;

  @override
  State<ServicesTab> createState() => _ServicesTabState();
}

class _ServicesTabState extends State<ServicesTab> {
  List<ServiceItem>? _items;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final r = await context.read<ApiClient>().send('GET', '/services', auth: false);
      final list = (r['data'] as List? ?? const [])
          .map((e) => ServiceItem.fromJson(e as Map<String, dynamic>))
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
    final items = _items ?? const <ServiceItem>[];
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const EmergencyNote(),
          const SizedBox(height: 12),
          if (items.isEmpty)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: Text('Belum ada layanan yang tersedia saat ini.')),
            ),
          for (final s in items) _ServiceCard(service: s, onBooked: widget.onBooked),
        ],
      ),
    );
  }
}

class _ServiceCard extends StatelessWidget {
  const _ServiceCard({required this.service, required this.onBooked});
  final ServiceItem service;
  final VoidCallback onBooked;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(service.name, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
            if (service.description != null && service.description!.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(service.description!),
            ],
            if (service.limitations != null && service.limitations!.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text('Batas layanan: ${service.limitations}'),
            ],
            const SizedBox(height: 8),
            Text(formatRupiah(service.priceAmount), style: const TextStyle(fontWeight: FontWeight.w600)),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: () async {
                  final ok = await Navigator.of(context).push<bool>(
                    MaterialPageRoute(builder: (_) => BookingFormPage(service: service)),
                  );
                  if (ok == true) onBooked();
                },
                child: const Text('Pesan kunjungan'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
