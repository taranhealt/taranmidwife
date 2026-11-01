String pad2(int n) => n.toString().padLeft(2, '0');

String formatRupiah(int amount) {
  final s = amount.toString();
  final buf = StringBuffer();
  for (var i = 0; i < s.length; i++) {
    if (i > 0 && (s.length - i) % 3 == 0) buf.write('.');
    buf.write(s[i]);
  }
  return 'Rp$buf';
}

String formatWib(DateTime t) {
  final w = t.toUtc().add(const Duration(hours: 7));
  return '${pad2(w.day)}/${pad2(w.month)}/${w.year} ${pad2(w.hour)}:${pad2(w.minute)} WIB';
}
