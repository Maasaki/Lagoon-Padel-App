/// Base URL de l’API (inclut le préfixe `/api`).
///
/// Définir au build : `--dart-define=API_BASE_URL=http://10.0.2.2:8080/api`
/// (émulateur Android → machine hôte).
class AppConfig {
  AppConfig._();

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8080/api',
  );
}
