import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:provider/provider.dart';

import 'core/api/api_client.dart';
import 'core/config/secure_keys.dart';
import 'core/theme/lagoon_theme.dart';
import 'features/auth/auth_state.dart';
import 'features/reservations/reservations_screen.dart';
import 'features/terrains/availability_screen.dart';
import 'features/terrains/home_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('fr_FR');
  const secure = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );
  final api = ApiClient(
    tokenReader: () => secure.read(key: SecureKeys.jwt),
  );
  final auth = AuthState(api, secure);

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider<AuthState>.value(value: auth),
        Provider<ApiClient>.value(value: api),
      ],
      child: const LagoonApp(),
    ),
  );
}

class LagoonApp extends StatelessWidget {
  const LagoonApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Lagoon Padel',
      debugShowCheckedModeBanner: false,
      theme: buildLagoonTheme(),
      locale: const Locale('fr', 'FR'),
      supportedLocales: const [Locale('fr', 'FR')],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: const MainShell(),
    );
  }
}

class MainShell extends StatefulWidget {
  const MainShell({super.key});

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int _index = 0;
  int _terrainId = 1;
  final GlobalKey<ReservationsScreenState> _reservationsKey =
      GlobalKey<ReservationsScreenState>();

  void _openAvailability(int terrainId) {
    setState(() {
      _terrainId = terrainId;
      _index = 1;
    });
  }

  @override
  Widget build(BuildContext context) {
    final api = context.read<ApiClient>();

    return Scaffold(
      body: IndexedStack(
        index: _index,
        children: [
          HomeScreen(api: api, onOpenAvailability: _openAvailability),
          AvailabilityScreen(
            key: ValueKey(_terrainId),
            api: api,
            initialTerrainId: _terrainId,
          ),
          ReservationsScreen(key: _reservationsKey, api: api),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) {
          setState(() => _index = i);
          if (i == 2) {
            _reservationsKey.currentState?.refresh();
          }
        },
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home_rounded),
            label: 'Accueil',
          ),
          NavigationDestination(
            icon: Icon(Icons.event_available_outlined),
            selectedIcon: Icon(Icons.event_available_rounded),
            label: 'Créneaux',
          ),
          NavigationDestination(
            icon: Icon(Icons.receipt_long_outlined),
            selectedIcon: Icon(Icons.receipt_long_rounded),
            label: 'Mes résas',
          ),
        ],
      ),
    );
  }
}
