import 'package:flutter/material.dart';

class TaranColors {
  static const navy = Color(0xFF101D3A);
  static const technologyBlue = Color(0xFF1677C8);
  static const teal = Color(0xFF168C8C);
  static const mint = Color(0xFFBDEBDD);
  static const softPink = Color(0xFFEAA6BB);
  static const blushPink = Color(0xFFF9DDE5);
  static const gold = Color(0xFFC9A45C);
  static const lightGray = Color(0xFFF5F7FA);
}

ThemeData taranTheme() {
  final scheme = ColorScheme.fromSeed(seedColor: TaranColors.technologyBlue).copyWith(
    primary: TaranColors.technologyBlue,
    onPrimary: Colors.white,
    secondary: TaranColors.teal,
    onSecondary: Colors.white,
    onSurface: TaranColors.navy,
    error: const Color(0xFFB3261E),
  );
  return ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    scaffoldBackgroundColor: TaranColors.lightGray,
    appBarTheme: const AppBarTheme(
      backgroundColor: TaranColors.navy,
      foregroundColor: Colors.white,
    ),
    inputDecorationTheme: const InputDecorationTheme(border: OutlineInputBorder()),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(minimumSize: const Size(64, 48)),
    ),
  );
}
