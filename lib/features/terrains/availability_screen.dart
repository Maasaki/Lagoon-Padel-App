import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_client.dart';
import '../../core/theme/lagoon_theme.dart';
import '../../core/utils/format_xpf.dart';
import '../auth/auth_state.dart';
import '../auth/widgets/auth_sheet.dart';
import '../../widgets/loading_overlay.dart';

class AvailabilityScreen extends StatefulWidget {
  const AvailabilityScreen({
    super.key,
    required this.api,
    required this.initialTerrainId,
  });

  final ApiClient api;
  final int initialTerrainId;

  @override
  State<AvailabilityScreen> createState() => _AvailabilityScreenState();
}

class _AvailabilityScreenState extends State<AvailabilityScreen> {
  late int _terrainId;
  late DateTime _day;
  List<Map<String, dynamic>> _terrains = [];
  List<Map<String, dynamic>> _slots = [];
  int _priceXpf = 5000;
  String? _terrainName;
  bool _loadingTerrains = true;
  bool _loadingSlots = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _terrainId = widget.initialTerrainId;
    _day = DateTime.now();
    _loadTerrains();
  }

  @override
  void didUpdateWidget(covariant AvailabilityScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.initialTerrainId != widget.initialTerrainId) {
      _terrainId = widget.initialTerrainId;
      _loadSlots();
    }
  }

  Future<void> _loadTerrains() async {
    setState(() {
      _loadingTerrains = true;
      _error = null;
    });
    try {
      final list = await widget.api.getTerrains();
      if (!mounted) return;
      setState(() {
        _terrains = list;
        if (list.isNotEmpty &&
            !list.any((t) => (t['id'] as num).toInt() == _terrainId)) {
          _terrainId = (list.first['id'] as num).toInt();
        }
      });
      await _loadSlots();
    } catch (e) {
      if (mounted) setState(() => _error = dioErrorMessage(e));
    } finally {
      if (mounted) setState(() => _loadingTerrains = false);
    }
  }

  Future<void> _loadSlots() async {
    setState(() {
      _loadingSlots = true;
      _error = null;
    });
    try {
      final iso = DateFormat('yyyy-MM-dd').format(_day);
      final res = await widget.api.getSlots(_terrainId, iso);
      if (!mounted) return;
      setState(() {
        _slots = res.slots;
        _priceXpf = res.priceXpf;
        _terrainName = res.terrain['name'] as String?;
      });
    } catch (e) {
      if (mounted) setState(() => _error = dioErrorMessage(e));
    } finally {
      if (mounted) setState(() => _loadingSlots = false);
    }
  }

  Future<void> _pickDate() async {
    final first = DateTime.now();
    final last = first.add(const Duration(days: 365));
    final picked = await showDatePicker(
      context: context,
      initialDate: _day.isBefore(first) ? first : _day,
      firstDate: first,
      lastDate: last,
      locale: const Locale('fr', 'FR'),
    );
    if (picked != null) {
      setState(() => _day = picked);
      await _loadSlots();
    }
  }

  Future<void> _book(Map<String, dynamic> slot) async {
    final auth = context.read<AuthState>();
    if (!auth.isLoggedIn) {
      final ok = await showAuthSheet(context);
      if (!mounted || ok != true) return;
    }
    if (!mounted) return;
    final iso = DateFormat('yyyy-MM-dd').format(_day);
    final start = slot['start_time'] as String;
    final end = slot['end_time'] as String;

    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Confirmer la réservation'),
        content: Text(
          '$start – $end\n${_terrainName ?? "Terrain"}\n${_formatDay(_day)}\n${formatXpf(_priceXpf)}',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Réserver')),
        ],
      ),
    );
    if (confirm != true || !mounted) return;

    setState(() => _error = null);
    try {
      await widget.api.createReservation(
        terrainId: _terrainId,
        date: iso,
        startTime: start,
        endTime: end,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Réservation confirmée')),
      );
      await _loadSlots();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(dioErrorMessage(e))),
        );
      }
    }
  }

  String _formatDay(DateTime d) {
    return DateFormat.yMMMEd('fr_FR').format(d);
  }

  @override
  Widget build(BuildContext context) {
    final loading = _loadingTerrains || _loadingSlots;

    return Scaffold(
      appBar: AppBar(title: const Text('Disponibilités')),
      body: LoadingOverlay(
      loading: loading && _slots.isEmpty && _error == null,
      message: 'Chargement des créneaux…',
      child: RefreshIndicator(
        onRefresh: () async {
          await _loadTerrains();
        },
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            if (_terrains.length > 1)
              DropdownButton<int>(
                isExpanded: true,
                value: _terrainId,
                hint: const Text('Terrain'),
                items: _terrains
                    .map(
                      (t) => DropdownMenuItem(
                        value: (t['id'] as num).toInt(),
                        child: Text(t['name'] as String? ?? ''),
                      ),
                    )
                    .toList(),
                onChanged: (v) {
                  if (v == null) return;
                  setState(() => _terrainId = v);
                  _loadSlots();
                },
              ),
            if (_terrains.length > 1) const SizedBox(height: 12),
            Material(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              child: InkWell(
                onTap: _pickDate,
                borderRadius: BorderRadius.circular(14),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                  child: Row(
                    children: [
                      Icon(Icons.event_rounded, color: LagoonColors.lagoon),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Date',
                              style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                            ),
                            Text(
                              _formatDay(_day),
                              style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const Icon(Icons.chevron_right),
                    ],
                  ),
                ),
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Tarif : ${formatXpf(_priceXpf)} / créneau (1h30)',
              style: TextStyle(color: Colors.grey.shade700, fontSize: 13),
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!, style: TextStyle(color: Colors.red.shade700, fontSize: 13)),
            ],
            const SizedBox(height: 20),
            Text(
              'Créneaux',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
            ),
            const SizedBox(height: 12),
            ..._slots.map((slot) {
              final available = slot['available'] == true;
              final start = slot['start_time'] as String;
              final end = slot['end_time'] as String;
              return Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: AnimatedOpacity(
                  duration: const Duration(milliseconds: 200),
                  opacity: available ? 1 : 0.45,
                  child: Material(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(14),
                    child: ListTile(
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      title: Text(
                        '$start – $end',
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                      subtitle: Text(available ? 'Disponible' : 'Occupé'),
                      trailing: available
                          ? FilledButton(
                              onPressed: () => _book(slot),
                              child: const Text('Réserver'),
                            )
                          : Chip(
                              label: const Text('Complet'),
                              visualDensity: VisualDensity.compact,
                              backgroundColor: Colors.grey.shade200,
                            ),
                    ),
                  ),
                ),
              );
            }),
          ],
        ),
      ),
    ),
    );
  }
}
