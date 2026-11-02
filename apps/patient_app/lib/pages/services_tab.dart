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
  String? _filterError;
  final TextEditingController _searchController = TextEditingController();
  final TextEditingController _minPriceController = TextEditingController();
  final TextEditingController _maxPriceController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchController.dispose();
    _minPriceController.dispose();
    _maxPriceController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final query = <String, String>{};
      final search = _searchController.text.trim();
      final minPrice = _minPriceController.text.trim();
      final maxPrice = _maxPriceController.text.trim();
      if (search.isNotEmpty) query['search'] = search;
      if (minPrice.isNotEmpty) query['min_price'] = minPrice;
      if (maxPrice.isNotEmpty) query['max_price'] = maxPrice;
      final r = await context.read<ApiClient>().send('GET', '/services', auth: false, query: query);
      final list = (r['data'] as List? ?? const [])
          .map((e) => ServiceItem.fromJson(e as Map<String, dynamic>))
          .toList();
      if (mounted) {
        setState(() {
          _items = list;
          _error = null;
          _filterError = null;
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

  void _applyFilters() {
    final min = int.tryParse(_minPriceController.text.trim());
    final max = int.tryParse(_maxPriceController.text.trim());
    if ((_minPriceController.text.trim().isNotEmpty && (min == null || min < 0)) ||
        (_maxPriceController.text.trim().isNotEmpty && (max == null || max < 0))) {
      setState(() => _filterError = 'Masukkan rentang harga berupa angka nol atau lebih.');
      return;
    }
    if (min != null && max != null && min > max) {
      setState(() => _filterError = 'Harga minimum tidak boleh melebihi harga maksimum.');
      return;
    }
    setState(() { _loading = true; _filterError = null; });
    _load();
  }

  void _clearFilters() {
    _searchController.clear();
    _minPriceController.clear();
    _maxPriceController.clear();
    setState(() { _loading = true; _filterError = null; });
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
          const SizedBox(height: 12),          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                children: [
                  TextField(
                    controller: _searchController,
                    textInputAction: TextInputAction.search,
                    onSubmitted: (_) => _applyFilters(),
                    decoration: const InputDecoration(
                      labelText: 'Cari layanan',
                      hintText: 'Contoh: kunjungan ibu',
                      prefixIcon: Icon(Icons.search),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(children: [
                    Expanded(child: TextField(
                      controller: _minPriceController,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(labelText: 'Harga minimum', prefixText: 'Rp '),
                    )),
                    const SizedBox(width: 8),
                    Expanded(child: TextField(
                      controller: _maxPriceController,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(labelText: 'Harga maksimum', prefixText: 'Rp '),
                    )),
                  ]),
                  if (_filterError != null)
                    Align(alignment: Alignment.centerLeft, child: Text(_filterError!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
                  const SizedBox(height: 8),
                  Row(children: [
                    Expanded(child: FilledButton(onPressed: _applyFilters, child: const Text('Terapkan'))),
                    const SizedBox(width: 8),
                    TextButton(onPressed: _clearFilters, child: const Text('Reset')),
                  ]),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),

          const _CoverageCard(),
          const SizedBox(height: 12),
          if (items.isEmpty)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: Text('Tidak ada layanan yang sesuai dengan pencarian atau rentang harga.')),
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


class _CoverageCard extends StatefulWidget {
  const _CoverageCard();

  @override
  State<_CoverageCard> createState() => _CoverageCardState();
}

class _CoverageCardState extends State<_CoverageCard> {
  final _lat = TextEditingController();
  final _lng = TextEditingController();
  bool _busy = false;
  String? _result;
  String? _error;

  @override
  void dispose() {
    _lat.dispose();
    _lng.dispose();
    super.dispose();
  }

  Future<void> _check() async {
    final lat = double.tryParse(_lat.text.trim());
    final lng = double.tryParse(_lng.text.trim());
    if (lat == null || lng == null || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
      setState(() => _error = 'Masukkan koordinat lintang dan bujur yang valid.');
      return;
    }
    setState(() { _busy = true; _error = null; _result = null; });
    try {
      final r = await context.read<ApiClient>().send(
        'GET',
        '/coverage',
        query: {'lat': lat.toString(), 'lng': lng.toString()},
      );
      final d = Map<String, dynamic>.from(r['data'] as Map);
      final inPilot = d['in_pilot_area'] == true;
      final serviceable = d['serviceable'] == true;
      final pilot = (d['pilot_name'] ?? 'wilayah pilot').toString();
      final count = (d['partners_nearby'] ?? 0).toString();
      if (mounted) {
        setState(() {
          _result = serviceable
              ? 'Layanan tersedia di sekitar lokasi Anda. Mitra terdekat: $count.'
              : inPilot
                  ? 'Lokasi termasuk $pilot, tetapi belum ada mitra yang tersedia.'
                  : 'Lokasi berada di luar wilayah pilot $pilot.';
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.displayMessage);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('Cek jangkauan layanan', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 4),
        const Text('Masukkan koordinat lokasi secara manual. Lokasi perangkat tidak diambil otomatis.'),
        const SizedBox(height: 8),
        Row(children: [
          Expanded(child: TextField(controller: _lat, keyboardType: const TextInputType.numberWithOptions(decimal: true, signed: true), decoration: const InputDecoration(labelText: 'Latitude'))),
          const SizedBox(width: 8),
          Expanded(child: TextField(controller: _lng, keyboardType: const TextInputType.numberWithOptions(decimal: true, signed: true), decoration: const InputDecoration(labelText: 'Longitude'))),
        ]),
        const SizedBox(height: 8),
        SizedBox(width: double.infinity, child: OutlinedButton(
          onPressed: _busy ? null : _check,
          child: _busy ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Periksa jangkauan'),
        )),
        if (_result != null) Text(_result!),
        if (_error != null) Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
      ]),
    ),
  );
}
