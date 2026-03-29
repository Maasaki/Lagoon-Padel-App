import 'package:flutter/material.dart';

import '../core/theme/lagoon_theme.dart';

class LagoonLogo extends StatelessWidget {
  const LagoonLogo({super.key, this.size = 72});

  final double size;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Container(
          width: size,
          height: size,
          decoration: BoxDecoration(
            color: LagoonColors.lagoon,
            borderRadius: BorderRadius.circular(size * 0.22),
            boxShadow: [
              BoxShadow(
                color: LagoonColors.lagoon.withValues(alpha: 0.35),
                blurRadius: 20,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Icon(
            Icons.sports_tennis_rounded,
            size: size * 0.55,
            color: Colors.white,
          ),
        ),
        const SizedBox(height: 12),
        Text(
          'Lagoon Padel',
          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                fontWeight: FontWeight.w700,
                letterSpacing: -0.5,
                color: const Color(0xFF1C1C1E),
              ),
        ),
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
