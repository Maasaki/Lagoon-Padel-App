import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/api/api_client.dart';

class DashboardReservationsPage extends StatefulWidget {
  const DashboardReservationsPage({super.key, required this.api});

  final ApiClient api;

  @override
  State<DashboardReservationsPage> createState() =>
      _DashboardReservationsPageState();
}

class _DashboardReservationsPageState extends State<DashboardReservationsPage> {
  List<Map<String, dynamic>> _rows = [];
  bool _loading = true;
  String? _error;
  DateTime? _filterFrom;
  DateTime? _filterTo;

  String _paymentLabel(String? s) {
    switch (s) {
      case 'pending':
        return 'en attente';
      case 'paid':
        return 'payé';
      case 'failed':
        return 'échoué';
      case 'cancelled':
        return 'annulé';
      default:
        return s ?? '—';
    }
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final list = await widget.api.adminReservations(
        from: _filterFrom != null
            ? DateFormat('yyyy-MM-dd').format(_filterFrom!)
            : null,
        to: _filterTo != null ? DateFormat('yyyy-MM-dd').format(_filterTo!) : null,
      );
      if (mounted) setState(() => _rows = list);
    } catch (e) {
      if (mounted) setState(() => _error = dioErrorMessage(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _pickFrom() async {
    final now = DateTime.now();
    final d = await showDatePicker(
      context: context,
      initialDate: _filterFrom ?? now,
      firstDate: DateTime(now.year - 1),
      lastDate: DateTime(now.year + 2),
      locale: const Locale('fr', 'FR'),
    );
    if (d != null) setState(() => _filterFrom = d);
  }

  Future<void> _pickTo() async {
    final now = DateTime.now();
    final d = await showDatePicker(
      context: context,
      initialDate: _filterTo ?? now,
      firstDate: DateTime(now.year - 1),
      lastDate: DateTime(now.year + 2),
      locale: const Locale('fr', 'FR'),
    );
    if (d != null) setState(() => _filterTo = d);
  }

  Future<void> _confirmDelete(Map<String, dynamic> row) async {
    final id = (row['id'] as num).toInt();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Annuler la réservation ?'),
        content: Text(
          '${row['terrain_name']} · ${row['date']} ${row['start_time']}\n'
          '${row['user_name']} (${row['user_email']})',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Retour'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Supprimer'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    try {
      await widget.api.adminDeleteReservation(id);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Réservation annulée.')),
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
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: Wrap(
            spacing: 8,
            runSpacing: 8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              OutlinedButton.icon(
                onPressed: _pickFrom,
                icon: const Icon(Icons.calendar_today, size: 18),
                label: Text(
                  _filterFrom == null
                      ? 'Du…'
                      : DateFormat.yMd('fr_FR').format(_filterFrom!),
                ),
              ),
              OutlinedButton.icon(
                onPressed: _pickTo,
                icon: const Icon(Icons.calendar_today, size: 18),
                label: Text(
                  _filterTo == null
                      ? 'Au…'
                      : DateFormat.yMd('fr_FR').format(_filterTo!),
                ),
              ),
              TextButton(
                onPressed: () {
                  setState(() {
                    _filterFrom = null;
                    _filterTo = null;
                  });
                  _load();
                },
                child: const Text('Réinitialiser'),
              ),
              FilledButton.icon(
                onPressed: _loading ? null : _load,
                icon: const Icon(Icons.filter_list_rounded, size: 18),
                label: const Text('Filtrer'),
              ),
            ],
          ),
        ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Text(
              _error!,
              style: TextStyle(color: Colors.red.shade700, fontSize: 13),
            ),
          ),
        Expanded(
          child: _loading && _rows.isEmpty
              ? const Center(child: CircularProgressIndicator())
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: _rows.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 8),
                    itemBuilder: (context, i) {
                      final r = _rows[i];
                      return Card(
                        child: ListTile(
                          title: Text(
                            '${r['terrain_name']} · ${r['date']} '
                            '${r['start_time']}–${r['end_time']}',
                          ),
                          subtitle: Text(
                            '${r['user_name']} · ${r['user_email']}\n'
                            '${r['price']} XPF · Paiement : ${_paymentLabel(r['payment_status'] as String?)}',
                            style: const TextStyle(height: 1.35),
                          ),
                          isThreeLine: true,
                          trailing: IconButton(
                            icon: const Icon(Icons.delete_outline_rounded),
                            tooltip: 'Annuler',
                            onPressed: () => _confirmDelete(r),
                          ),
                        ),
                      );
                    },
                  ),
                ),
        ),
      ],
    );
  }
}
