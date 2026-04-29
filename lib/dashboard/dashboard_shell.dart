import 'package:flutter/material.dart';

import '../core/api/api_client.dart';
import 'dashboard_reservations_page.dart';
import 'dashboard_slot_blocks_page.dart';
import 'dashboard_terrain_day_blocks_page.dart';
import 'dashboard_users_page.dart';

/// Tableau de bord réservé aux comptes avec le rôle administrateur (API).
class DashboardShell extends StatefulWidget {
  const DashboardShell({super.key, required this.api});

  final ApiClient api;

  @override
  State<DashboardShell> createState() => _DashboardShellState();
}

class _DashboardShellState extends State<DashboardShell> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Administration'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: IndexedStack(
        index: _index,
        children: [
          DashboardUsersPage(api: widget.api),
          DashboardReservationsPage(api: widget.api),
          DashboardTerrainDayBlocksPage(api: widget.api),
          DashboardSlotBlocksPage(api: widget.api),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.group_outlined),
            selectedIcon: Icon(Icons.group_rounded),
            label: 'Utilisateurs',
          ),
          NavigationDestination(
            icon: Icon(Icons.event_note_outlined),
            selectedIcon: Icon(Icons.event_note_rounded),
            label: 'Réservations',
          ),
          NavigationDestination(
            icon: Icon(Icons.event_busy_outlined),
            selectedIcon: Icon(Icons.event_busy_rounded),
            label: 'Journées',
          ),
          NavigationDestination(
            icon: Icon(Icons.schedule_outlined),
            selectedIcon: Icon(Icons.schedule_rounded),
            label: 'Créneaux',
          ),
        ],
      ),
    );
  }
}
