import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_client.dart';
import '../auth/auth_state.dart';
import '../auth/widgets/auth_sheet.dart';
import '../../dashboard/dashboard_shell.dart';
import '../../widgets/lagoon_logo.dart';
import '../../widgets/loading_overlay.dart';
import '../../widgets/terrain_card.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({
    super.key,
    required this.api,
    required this.onOpenAvailability,
  });

  final ApiClient api;
  final void Function(int terrainId) onOpenAvailability;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  List<Map<String, dynamic>> _terrains = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _fetch();
  }

  Future<void> _fetch() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final list = await widget.api.getTerrains();
      if (mounted) setState(() => _terrains = list);
    } catch (e) {
      if (mounted) setState(() => _error = dioErrorMessage(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const SizedBox.shrink(),
        actions: [
          Consumer<AuthState>(
            builder: (context, auth, _) {
              if (!auth.isReady) return const SizedBox(width: 8);
              if (auth.isLoggedIn) {
                return PopupMenuButton<String>(
                  icon: const Icon(Icons.account_circle_outlined),
                  onSelected: (v) {
                    if (v == 'out') auth.logout();
                    if (v == 'admin') {
                      Navigator.of(context).push<void>(
                        MaterialPageRoute<void>(
                          builder: (ctx) => DashboardShell(api: widget.api),
                        ),
                      );
                    }
                  },
                  itemBuilder: (ctx) => [
                    PopupMenuItem(
                      enabled: false,
                      child: Text(auth.userName ?? 'Compte'),
                    ),
                    if (auth.isAdmin)
                      const PopupMenuItem(
                        value: 'admin',
                        child: Text('Administration'),
                      ),
                    const PopupMenuItem(value: 'out', child: Text('Déconnexion')),
                  ],
                );
              }
              return TextButton(
                onPressed: () => showAuthSheet(context),
                child: const Text('Connexion'),
              );
            },
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: RefreshIndicator(
      onRefresh: _fetch,
      color: Theme.of(context).colorScheme.primary,
      child: LoadingOverlay(
        loading: _loading && _terrains.isEmpty,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(24, 24, 24, 8),
                child: Column(
                  children: [
                    const LagoonLogo(),
                    const SizedBox(height: 28),
                    if (_error != null)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Text(
                          _error!,
                          textAlign: TextAlign.center,
                          style: TextStyle(color: Colors.red.shade700, fontSize: 13),
                        ),
                      ),
                  ],
                ),
              ),
            ),
            SliverPadding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              sliver: SliverList.separated(
                itemCount: _terrains.length,
                separatorBuilder: (context, _) => const SizedBox(height: 12),
                itemBuilder: (context, i) {
                  final t = _terrains[i];
                  final id = (t['id'] as num).toInt();
                  final name = t['name'] as String? ?? 'Terrain';
                  return TerrainCard(
                    name: name,
                    subtitle: 'Voir les créneaux du jour',
                    onTap: () => widget.onOpenAvailability(id),
                  );
                },
              ),
            ),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(24, 24, 24, 32),
                child: FilledButton.icon(
                  onPressed: _terrains.isEmpty
                      ? null
                      : () => widget.onOpenAvailability(
                            (_terrains.first['id'] as num).toInt(),
                          ),
                  icon: const Icon(Icons.calendar_month_rounded),
                  label: const Text('Voir les disponibilités'),
                ),
              ),
            ),
          ],
        ),
      ),
    ),
    );
  }
}
