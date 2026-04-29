import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/api/api_client.dart';

class DashboardTerrainDayBlocksPage extends StatefulWidget {
  const DashboardTerrainDayBlocksPage({super.key, required this.api});

  final ApiClient api;

  @override
  State<DashboardTerrainDayBlocksPage> createState() =>
      _DashboardTerrainDayBlocksPageState();
}

class _DashboardTerrainDayBlocksPageState
    extends State<DashboardTerrainDayBlocksPage> {
  List<Map<String, dynamic>> _terrains = [];
  List<Map<String, dynamic>> _blocks = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    try {
      final t = await widget.api.getTerrains();
      if (mounted) setState(() => _terrains = t);
    } catch (_) {}
    await _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final list = await widget.api.adminTerrainDayBlocks();
      if (mounted) setState(() => _blocks = list);
    } catch (e) {
      if (mounted) setState(() => _error = dioErrorMessage(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _addBlock() async {
    if (_terrains.isEmpty) return;
    int terrainId = (_terrains.first['id'] as num).toInt();
    DateTime day = DateTime.now();

    final saved = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setS) => AlertDialog(
          title: const Text('Fermer un terrain pour la journée'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              InputDecorator(
                decoration: const InputDecoration(labelText: 'Terrain'),
                child: DropdownButtonHideUnderline(
                  child: DropdownButton<int>(
                    isExpanded: true,
                    value: terrainId,
                    items: _terrains
                        .map(
                          (t) => DropdownMenuItem(
                            value: (t['id'] as num).toInt(),
                            child: Text(t['name'] as String? ?? 'Terrain'),
                          ),
                        )
                        .toList(),
                    onChanged: (v) => setS(() => terrainId = v ?? terrainId),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              ListTile(
                title: Text(DateFormat.yMMMMEEEEd('fr_FR').format(day)),
                trailing: const Icon(Icons.edit_calendar_outlined),
                onTap: () async {
                  final d = await showDatePicker(
                    context: ctx,
                    initialDate: day,
                    firstDate: DateTime(day.year),
                    lastDate: DateTime(day.year + 2),
                    locale: const Locale('fr', 'FR'),
                  );
                  if (d != null) setS(() => day = d);
                },
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Annuler'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Bloquer'),
            ),
          ],
        ),
      ),
    );

    if (saved != true || !mounted) return;
    final iso = DateFormat('yyyy-MM-dd').format(day);
    try {
      await widget.api.adminCreateTerrainDayBlock(
        terrainId: terrainId,
        blockDate: iso,
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Journée bloquée.')),
        );
      }
      await _load();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(dioErrorMessage(e))),
        );
      }
    }
  }

  Future<void> _delete(int id) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Supprimer ce blocage ?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Non'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Oui'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    try {
      await widget.api.adminDeleteTerrainDayBlock(id);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Bloc supprimé.')),
        );
      }
      await _load();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(dioErrorMessage(e))),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _blocks.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    return Stack(
      children: [
        RefreshIndicator(
          onRefresh: _load,
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              if (_error != null)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Text(
                      _error!,
                      style:
                          TextStyle(color: Colors.red.shade700, fontSize: 14),
                    ),
                  ),
                ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 88),
                sliver: SliverList.separated(
                  itemCount: _blocks.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (context, i) {
                    final b = _blocks[i];
                    final id = (b['id'] as num).toInt();
                    return Card(
                      child: ListTile(
                        title: Text(b['terrain_name'] as String? ?? ''),
                        subtitle: Text(
                          DateFormat.yMMMMEEEEd('fr_FR').format(
                            DateTime.parse(b['block_date'] as String),
                          ),
                        ),
                        trailing: IconButton(
                          icon: const Icon(Icons.delete_outline_rounded),
                          onPressed: () => _delete(id),
                        ),
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
        ),
        Positioned(
          right: 16,
          bottom: 16,
          child: FloatingActionButton.extended(
            onPressed: _terrains.isEmpty ? null : _addBlock,
            icon: const Icon(Icons.add_rounded),
            label: const Text('Journée fermée'),
          ),
        ),
      ],
    );
  }
}
