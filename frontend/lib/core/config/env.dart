/// Build-time configuration. Override with `--dart-define=API_BASE_URL=...`.
///
/// Android emulator: `http://10.0.2.2:8011/api/v1`.
abstract final class Env {
  static const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://localhost:8011/api/v1',
  );
}
