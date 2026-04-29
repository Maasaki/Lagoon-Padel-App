import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/api/api_client.dart';

class DashboardUsersPage extends StatefulWidget {
  const DashboardUsersPage({super.key, required this.api});

  final ApiClient api;

  @override
  State<DashboardUsersPage> createState() => _DashboardUsersPageState();
}

class _DashboardUsersPageState extends State<DashboardUsersPage> {
  List<Map<String, dynamic>> _rows = [];
  bool _loading = true;
  String? _error;

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
      final list = await widget.api.adminUsers();
      if (mounted) setState(() => _rows = list);
    } catch (e) {
      if (mounted) setState(() => _error = dioErrorMessage(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _rows.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    return RefreshIndicator(
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
                  style: TextStyle(color: Colors.red.shade700, fontSize: 14),
                ),
              ),
            ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
            sliver: SliverList.separated(
              itemCount: _rows.length,
              separatorBuilder: (_, _) => const SizedBox(height: 8),
              itemBuilder: (context, i) {
                final u = _rows[i];
                final id = (u['id'] as num).toInt();
                final name = u['name'] as String? ?? '';
                final email = u['email'] as String? ?? '';
                final admin = u['is_admin'] == true || u['is_admin'] == 1;
                final created = u['created_at'] as String?;
                DateTime? dt;
                if (created != null && created.isNotEmpty) {
                  dt = DateTime.tryParse(created.replaceFirst(' ', 'T'));
                }
                final dateStr = dt != null
                    ? DateFormat.yMMMd('fr_FR').add_Hm().format(dt.toLocal())
                    : '—';

                return Card(
                  child: ListTile(
                    leading: CircleAvatar(
                      backgroundColor:
                          Theme.of(context).colorScheme.primaryContainer,
                      child: Text(
                        name.isNotEmpty ? name[0].toUpperCase() : '?',
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.onPrimaryContainer,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                    title: Text(name, maxLines: 1, overflow: TextOverflow.ellipsis),
                    subtitle: Text(
                      email,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    trailing: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        if (admin)
                          Chip(
                            label: const Text('Admin'),
                            visualDensity: VisualDensity.compact,
                            labelStyle: const TextStyle(fontSize: 11),
                            padding: EdgeInsets.zero,
                            materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                          ),
                        Text(
                          '#$id · $dateStr',
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                color: Theme.of(context).colorScheme.outline,
                              ),
                        ),
                      ],
                    ),
                    isThreeLine: admin,
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
