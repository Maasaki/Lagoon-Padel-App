import 'package:flutter/material.dart';

/// Logo officiel [assets/logo_lagoon_padel.png].
class LagoonLogo extends StatelessWidget {
  const LagoonLogo({super.key, this.size = 220});

  /// Largeur maximale du logo (l’image garde ses proportions).
  final double size;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Image.asset(
          'assets/logo_lagoon_padel.png',
          width: size,
          fit: BoxFit.contain,
          filterQuality: FilterQuality.high,
        ),
        const SizedBox(height: 10),
        Text(
          'Tahiti',
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: Colors.grey.shade600,
                letterSpacing: 1.2,
              ),
        ),
      ],
    );
  }
}
