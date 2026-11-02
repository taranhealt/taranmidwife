import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:taran_core/taran_core.dart';

import '../data/midwife_repository.dart';
import '../widgets/retry_view.dart';

/// Hari 0 = Minggu, mengikuti `extract(dow ...)` pada pencocokan jadwal di backend.
const _dayLabels = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

class OfferingsTab extends StatefulWidget {
  const OfferingsTab({super.key});

  @override
  State<OfferingsTab> createState() => _OfferingsTabState();
}

class _OfferingsTabState extends State<OfferingsTab> {
  List<CatalogItem> services = const [];
  List<CatalogItem> areas = const [];

  bool accepting = false;
  Set<String> selectedServices = {};
  Set<String> selectedAreas = {};
  Map<int, Set<int>> hours = {};

  final lat = TextEditingController();
  final lng = TextEditingController();
  final radius = TextEditingController(text: '20');

  bool loading = true;
  bool saving = false;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    lat.dispose();
    lng.dispose();
    radius.dispose();
    super.dispose();
  }

  /// Menyalin keadaan server ke form. Dipanggil juga dengan respons `PUT`, yang
  /// dibaca ulang backend dari basis data, sehingga form mencerminkan data tersimpan.
  void _apply(MidwifeOfferings o) {
    accepting = o.acceptingRequests;
    selectedServices = {...o.serviceIds};
    selectedAreas = {...o.areaIds};
    hours = {for (final e in o.availability.entries) e.key: {...e.value}};
    final sp = o.servicePoint;
    lat.text = sp == null ? '' : '${sp.lat}';
    lng.text = sp == null ? '' : '${sp.lng}';
    radius.text = sp == null ? '20' : '${sp.radiusKm}';
  }

  Future<void> load() async {
    final repo = context.read<MidwifeRepository>();
    try {
      final results = await Future.wait<Object>([repo.offerings(), repo.services(), repo.areas()]);
      if (!mounted) return;
      setState(() {
        services = results[1] as List<CatalogItem>;
        areas = results[2] as List<CatalogItem>;
        _apply(results[0] as MidwifeOfferings);
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

  Future<void> toggleAccept(bool value) async {
    final repo = context.read<MidwifeRepository>();
    setState(() => saving = true);
    try {
      final o = await repo.setAccepting(value);
      // Hanya status ini yang diperbarui; perubahan formulir yang belum disimpan tidak hilang.
      if (mounted) setState(() => accepting = o.acceptingRequests);
    } on ApiException catch (e) {
      _toast(e.displayMessage);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Future<void> save() async {
    final parsed = parseServicePoint(lat.text, lng.text, radius.text);
    if (parsed.error != null) {
      _toast(parsed.error!);
      return;
    }
    final repo = context.read<MidwifeRepository>();
    setState(() => saving = true);
    try {
      final saved = await repo.saveOfferings(MidwifeOfferings(
        acceptingRequests: accepting,
        serviceIds: selectedServices,
        areaIds: selectedAreas,
        availability: hours,
        servicePoint: parsed.point,
      ));
      if (mounted) setState(() => _apply(saved));
      _toast('Pengaturan layanan disimpan.');
    } on ApiException catch (e) {
      _toast(e.displayMessage);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  void _toggle(Set<String> set, String id, bool? on) {
    setState(() => on == true ? set.add(id) : set.remove(id));
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
    final title = Theme.of(context).textTheme.titleMedium;
    return RefreshIndicator(
      onRefresh: load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Menerima permintaan baru'),
            value: accepting,
            onChanged: saving ? null : toggleAccept,
          ),
          const Divider(),
          Text('Pilih layanan', style: title),
          for (final s in services)
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              value: selectedServices.contains(s.id),
              title: Text(s.name.isEmpty ? 'Layanan' : s.name),
              onChanged: saving ? null : (v) => _toggle(selectedServices, s.id, v),
            ),
          const Divider(),
          Text('Wilayah layanan', style: title),
          for (final a in areas)
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              value: selectedAreas.contains(a.id),
              title: Text(a.name.isEmpty ? 'Wilayah' : a.name),
              onChanged: saving ? null : (v) => _toggle(selectedAreas, a.id, v),
            ),
          const Divider(),
          Text('Jadwal tersedia (WIB)', style: title),
          for (var day = 0; day < 7; day++)
            ExpansionTile(
              title: Text(_dayLabels[day]),
              children: [
                Wrap(
                  spacing: 4,
                  children: [
                    for (final slot in slotHours)
                      FilterChip(
                        label: Text('${pad2(slot)}:00'),
                        selected: (hours[day] ?? const <int>{}).contains(slot),
                        onSelected: saving
                            ? null
                            : (on) => setState(() {
                                  final set = hours.putIfAbsent(day, () => <int>{});
                                  on ? set.add(slot) : set.remove(slot);
                                }),
                      ),
                  ],
                ),
              ],
            ),
          const Divider(),
          Text('Titik layanan', style: title),
          const Text('Kosongkan latitude dan longitude untuk menghapus titik layanan.'),
          TextField(
            controller: lat,
            enabled: !saving,
            keyboardType: const TextInputType.numberWithOptions(decimal: true, signed: true),
            decoration: const InputDecoration(labelText: 'Latitude'),
          ),
          TextField(
            controller: lng,
            enabled: !saving,
            keyboardType: const TextInputType.numberWithOptions(decimal: true, signed: true),
            decoration: const InputDecoration(labelText: 'Longitude'),
          ),
          TextField(
            controller: radius,
            enabled: !saving,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Radius layanan (km, 1-100)'),
          ),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: saving ? null : save,
            child: saving
                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Simpan pengaturan'),
          ),
        ],
      ),
    );
  }
}
