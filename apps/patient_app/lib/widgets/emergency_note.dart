import 'package:flutter/material.dart';
import 'package:taran_core/taran_core.dart';

class EmergencyNote extends StatelessWidget {
  const EmergencyNote({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: TaranColors.blushPink,
        borderRadius: BorderRadius.circular(12),
      ),
      child: const Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.warning_amber_rounded),
          SizedBox(width: 8),
          Expanded(
            child: Text(
              'TaranMidwife bukan layanan gawat darurat. Jika ini keadaan darurat, '
              'jangan menunggu balasan aplikasi. Segera hubungi layanan gawat darurat '
              'atau fasilitas kesehatan terdekat.',
            ),
          ),
        ],
      ),
    );
  }
}
