import 'package:flutter/material.dart';

import 'bookings_tab.dart';
import 'offerings_tab.dart';
import 'profile_tab.dart';

class MitraHome extends StatefulWidget {
  const MitraHome({super.key});

  @override
  State<MitraHome> createState() => _MitraHomeState();
}

class _MitraHomeState extends State<MitraHome> {
  int tab = 0;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('TaranMidwife Mitra')),
        body: IndexedStack(
          index: tab,
          children: const [BookingsTab(), ProfileTab(), OfferingsTab()],
        ),
        bottomNavigationBar: NavigationBar(
          selectedIndex: tab,
          onDestinationSelected: (v) => setState(() => tab = v),
          destinations: const [
            NavigationDestination(icon: Icon(Icons.calendar_month), label: 'Pesanan'),
            NavigationDestination(icon: Icon(Icons.verified_user), label: 'Profil'),
            NavigationDestination(icon: Icon(Icons.tune), label: 'Layanan'),
          ],
        ),
      );
}
