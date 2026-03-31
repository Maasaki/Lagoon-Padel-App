/// Base URL de l’API (inclut le préfixe `/api`).
///
/// Développement local (ex. émulateur Android → machine hôte) :
/// `--dart-define=API_BASE_URL=http://10.0.2.2:8080/api`
class AppConfig {
  AppConfig._();

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://lagoon-padel-api.whiteprovider.net/api',
  );
}
