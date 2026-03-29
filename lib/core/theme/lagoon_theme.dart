import 'package:flutter/material.dart';

/// Palette « bleu lagon » + surfaces type iOS.
abstract final class LagoonColors {
  static const Color lagoon = Color(0xFF0A7EA4);
  static const Color lagoonDark = Color(0xFF065F78);
  static const Color lagoonLight = Color(0xFFB8E8F5);
  static const Color surface = Color(0xFFF2F6F8);
  static const Color card = Color(0xFFFFFFFF);
}

ThemeData buildLagoonTheme() {
  const base = LagoonColors.lagoon;
  final scheme = ColorScheme.fromSeed(
    seedColor: base,
    brightness: Brightness.light,
    primary: LagoonColors.lagoon,
    onPrimary: Colors.white,
    surface: LagoonColors.surface,
  );
  return ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    scaffoldBackgroundColor: LagoonColors.surface,
    appBarTheme: const AppBarTheme(
      centerTitle: true,
      elevation: 0,
      scrolledUnderElevation: 0.5,
      backgroundColor: LagoonColors.surface,
      foregroundColor: Color(0xFF1C1C1E),
    ),
    cardTheme: CardThemeData(
      elevation: 0,
      color: LagoonColors.card,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      margin: EdgeInsets.zero,
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        elevation: 0,
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: Colors.grey.shade300),
      ),
    ),
    pageTransitionsTheme: const PageTransitionsTheme(
      builders: {
        TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
        TargetPlatform.macOS: CupertinoPageTransitionsBuilder(),
        TargetPlatform.android: FadeUpwardsPageTransitionsBuilder(),
      },
    ),
  );
}
