import 'package:flutter/foundation.dart';

class Env {
  const Env._();

  /// Override with `--dart-define=API_BASE_URL=https://...`
  static const _override = String.fromEnvironment('API_BASE_URL');

  static String get baseUrl {
    if (_override.isNotEmpty) return _override;
    if (kIsWeb) return 'http://localhost:8000/api/v1';
    // Emulador Android → host da máquina
    return 'http://10.0.2.2:8000/api/v1';
  }
}
