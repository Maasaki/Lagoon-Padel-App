import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_client.dart';
import '../../core/utils/format_xpf.dart';
import '../auth/auth_state.dart';
import '../auth/widgets/auth_sheet.dart';
import '../../widgets/loading_overlay.dart';

class ReservationsScreen extends StatefulWidget {
  const ReservationsScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<ReservationsScreen> createState() => ReservationsScreenState();
}

class ReservationsScreenState extends State<ReservationsScreen> {
  List<Map<String, dynamic>> _items = [];
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      if (context.read<AuthState>().isLoggedIn) {
        refresh();
      }
    });
  }

  Future<void> refresh() => _load();

  Future<void> _load() async {
    final auth = context.read<AuthState>();
    if (!auth.isLoggedIn) return;
    setState(() => _loading = true);
    try {
      final list = await widget.api.myReservations();
      if (mounted) setState(() => _items = list);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(dioErrorMessage(e))),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _ensureAuthAndLoad() async {
    final auth = context.read<AuthState>();
    if (!auth.isLoggedIn) {
      final ok = await showAuthSheet(context);
      if (ok == true && mounted) await _load();
      return;
    }
    await _load();
  }

  Future<void> _cancel(int id) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Annuler la réservation ?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Non')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Oui')),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    try {
      await widget.api.deleteReservation(id);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Réservation annulée')),
        );
        await _load();
      }
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
    final auth = context.watch<AuthState>();

    if (!auth.isReady) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (!auth.isLoggedIn) {
      return Scaffold(
        appBar: AppBar(title: const Text('Mes réservations')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.lock_outline_rounded, size: 48, color: Colors.grey),
                const SizedBox(height: 16),
                const Text(
                  'Connectez-vous pour voir vos réservations.',
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: _ensureAuthAndLoad,
                  child: const Text('Connexion / Inscription'),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Mes réservations')),
      body: LoadingOverlay(
      loading: _loading && _items.isEmpty,
      child: RefreshIndicator(
        onRefresh: _load,
        child: _items.isEmpty && !_loading
            ? ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: const [
                  SizedBox(height: 120),
                  Center(child: Text('Aucune réservation pour le moment.')),
                ],
              )
            : ListView.separated(
                padding: const EdgeInsets.all(20),
                itemCount: _items.length,
                separatorBuilder: (context, _) => const SizedBox(height: 12),
                itemBuilder: (context, i) {
                  final r = _items[i];
                  final id = (r['id'] as num).toInt();
                  final terrain = r['terrain_name'] as String? ?? '';
                  final dateStr = r['date'] as String? ?? '';
                  DateTime? d;
                  try {
                    d = DateTime.parse(dateStr);
                  } catch (_) {}
                  final dayLabel = d != null
                      ? DateFormat.yMMMEd('fr_FR').format(d)
                      : dateStr;
                  final start = r['start_time'] as String? ?? '';
                  final end = r['end_time'] as String? ?? '';
                  final price = (r['price'] as num?)?.toInt() ?? 0;
                  final paymentStatus = r['payment_status'] as String? ?? 'paid';
                  final pendingPayment = paymentStatus == 'pending';
                  return Material(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            terrain,
                            style: const TextStyle(
                              fontSize: 17,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text('$dayLabel · $start – $end'),
                          const SizedBox(height: 4),
                          Text(formatXpf(price)),
                          if (pendingPayment) ...[
                            const SizedBox(height: 8),
                            Text(
                              'Paiement en attente — finalisez sur PayZen si ce n’est pas déjà fait.',
                              style: TextStyle(
                                fontSize: 13,
                                color: Colors.orange.shade800,
                              ),
                            ),
                          ],
                          Align(
                            alignment: Alignment.centerRight,
                            child: TextButton.icon(
                              onPressed: () => _cancel(id),
                              icon: const Icon(Icons.delete_outline_rounded),
                              label: const Text('Annuler'),
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
      ),
    ),
    );
  }
}
