import 'package:flutter/material.dart';

class StatusInfo {
  const StatusInfo(this.label, this.icon);
  final String label;
  final IconData icon;
}

StatusInfo bookingStatusInfo(String status) {
  switch (status) {
    case 'REQUESTED':
      return const StatusInfo('Menunggu konfirmasi', Icons.hourglass_top);
    case 'NEEDS_INFO':
      return const StatusInfo('Perlu informasi tambahan', Icons.help_outline);
    case 'WAITING_MIDWIFE':
      return const StatusInfo('Mencari bidan', Icons.search);
    case 'OFFERED':
      return const StatusInfo('Menunggu jawaban bidan', Icons.send);
    case 'CONFIRMED':
      return const StatusInfo('Dikonfirmasi', Icons.check_circle_outline);
    case 'IN_PROGRESS':
      return const StatusInfo('Kunjungan berlangsung', Icons.directions_walk);
    case 'COMPLETED':
      return const StatusInfo('Selesai', Icons.task_alt);
    case 'CANCELLED':
      return const StatusInfo('Dibatalkan', Icons.cancel_outlined);
    case 'UNFULFILLED':
      return const StatusInfo('Tidak terlaksana', Icons.block);
    default:
      return StatusInfo(status, Icons.info_outline);
  }
}

const cancellableStatuses = {
  'REQUESTED',
  'NEEDS_INFO',
  'WAITING_MIDWIFE',
  'OFFERED',
  'CONFIRMED',
};

class StatusBadge extends StatelessWidget {
  const StatusBadge(this.status, {super.key});
  final String status;

  @override
  Widget build(BuildContext context) {
    final info = bookingStatusInfo(status);
    return Chip(
      avatar: Icon(info.icon, size: 18),
      label: Text(info.label),
      visualDensity: VisualDensity.compact,
    );
  }
}
