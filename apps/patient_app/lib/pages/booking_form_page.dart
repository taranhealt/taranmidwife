import 'dart:math';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:taran_core/taran_core.dart';

class BookingFormPage extends StatefulWidget {
  const BookingFormPage({super.key, required this.service});
  final ServiceItem service;

  @override
  State<BookingFormPage> createState() => _BookingFormPageState();
}

class _BookingFormPageState extends State<BookingFormPage> {
  static const _slots = [8, 10, 12, 14, 16];
  final _form = GlobalKey<FormState>();
  final _address = TextEditingController();
  final _landmark = TextEditingController();
  final _phone = TextEditingController();
  // Satu kunci per formulir: percobaan ulang karena jaringan tidak membuat pesanan ganda.
  final String _key = 'tm-${DateTime.now().microsecondsSinceEpoch}-${Random.secure().nextInt(1 << 30)}';
  List<AreaItem>? _areas;
  String? _areaId;
  DateTime? _date;
  int? _slot;
  String? _loadError;
  String? _error;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _loadAreas();
  }

  @override
  void dispose() {
    _address.dispose();
    _landmark.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _loadAreas() async {
    try {
      final r = await context.read<ApiClient>().send('GET', '/areas', auth: false);
      final list = (r['data'] as List? ?? const [])
          .map((e) => AreaItem.fromJson(e as Map<String, dynamic>))
          .toList();
      if (mounted) {
        setState(() {
          _areas = list;
          _loadError = null;
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _loadError = e.displayMessage);
    }
  }

  Future<void> _pickDate() async {
    final tomorrow = DateTime.now().add(const Duration(days: 1));
    final picked = await showDatePicker(
      context: context,
      initialDate: _date ?? tomorrow,
      firstDate: tomorrow,
      lastDate: tomorrow.add(const Duration(days: 60)),
    );
    if (picked != null) setState(() => _date = picked);
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    if (_areaId == null || _date == null || _slot == null) {
      setState(() => _error = 'Pilih wilayah, tanggal, dan jam kunjungan.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    final d = _date!;
    final scheduled = '${d.year}-${pad2(d.month)}-${pad2(d.day)}T${pad2(_slot!)}:00:00+07:00';
    final landmark = _landmark.text.trim();
    final api = context.read<ApiClient>();
    try {
      await api.send(
        'POST',
        '/bookings',
        headers: {'Idempotency-Key': _key},
        body: {
          'service_id': widget.service.id,
          'area_id': _areaId,
          'scheduled_start': scheduled,
          'address_line': _address.text.trim(),
          if (landmark.isNotEmpty) 'landmark': landmark,
          'contact_phone': _phone.text.trim(),
        },
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Permintaan diterima. Jadwal belum final sampai dikonfirmasi.')),
      );
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.displayMessage);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final d = _date;
    return Scaffold(
      appBar: AppBar(title: Text(widget.service.name)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text('Biaya: ${formatRupiah(widget.service.priceAmount)}', style: const TextStyle(fontWeight: FontWeight.w600)),
          const SizedBox(height: 12),
          Form(
            key: _form,
            child: Column(
              children: [
                if (_loadError != null)
                  Row(children: [
                    Expanded(child: Text(_loadError!)),
                    TextButton(onPressed: _loadAreas, child: const Text('Coba lagi')),
                  ])
                else if (_areas == null)
                  const LinearProgressIndicator()
                else
                  DropdownButtonFormField<String>(
                    initialValue: _areaId,
                    decoration: const InputDecoration(labelText: 'Wilayah'),
                    items: [for (final a in _areas!) DropdownMenuItem(value: a.id, child: Text(a.name))],
                    onChanged: (v) => setState(() => _areaId = v),
                  ),
                const SizedBox(height: 12),
                Align(
                  alignment: Alignment.centerLeft,
                  child: OutlinedButton.icon(
                    onPressed: _pickDate,
                    icon: const Icon(Icons.event),
                    label: Text(d == null ? 'Pilih tanggal' : '${pad2(d.day)}/${pad2(d.month)}/${d.year}'),
                  ),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<int>(
                  initialValue: _slot,
                  decoration: const InputDecoration(labelText: 'Jam kunjungan'),
                  items: [
                    for (final h in _slots)
                      DropdownMenuItem(value: h, child: Text('${pad2(h)}:00 - ${pad2(h + 2)}:00 WIB')),
                  ],
                  onChanged: (v) => setState(() => _slot = v),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _address,
                  decoration: const InputDecoration(labelText: 'Alamat kunjungan'),
                  maxLines: 3,
                  maxLength: 300,
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Alamat wajib diisi' : null,
                ),
                TextFormField(
                  controller: _landmark,
                  decoration: const InputDecoration(labelText: 'Patokan rumah (opsional)'),
                  maxLength: 100,
                ),
                const SizedBox(height: 4),
                TextFormField(
                  controller: _phone,
                  decoration: const InputDecoration(labelText: 'Nomor telepon yang dapat dihubungi'),
                  keyboardType: TextInputType.phone,
                  validator: (v) => RegExp(r'^\+?[0-9]{8,15}$').hasMatch((v ?? '').trim())
                      ? null
                      : 'Nomor telepon 8-15 angka',
                ),
              ],
            ),
          ),
          if (_error != null) ...[
            const SizedBox(height: 8),
            Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Icon(Icons.error_outline, color: Theme.of(context).colorScheme.error),
              const SizedBox(width: 8),
              Expanded(child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
            ]),
          ],
          const SizedBox(height: 12),
          const Text('Jadwal belum final sampai dikonfirmasi oleh tim kami.'),
          const SizedBox(height: 12),
          SizedBox(
            height: 48,
            child: FilledButton(
              onPressed: _busy ? null : _submit,
              child: _busy
                  ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Kirim permintaan'),
            ),
          ),
        ],
      ),
    );
  }
}
