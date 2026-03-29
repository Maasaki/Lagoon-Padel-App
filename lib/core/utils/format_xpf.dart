import 'package:intl/intl.dart';

/// Affichage monétaire simple en XPF (pas de sous-unité).
String formatXpf(int amount) {
  final n = NumberFormat.decimalPattern('fr_FR');
  return '${n.format(amount)} XPF';
}
