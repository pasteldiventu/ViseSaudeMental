import 'package:flutter/foundation.dart';
import 'package:shorebird_code_push/shorebird_code_push.dart';

/// Integração leve com Shorebird Code Push.
///
/// Com `auto_update: true` (padrão em [shorebird.yaml]), o patch é baixado
/// em background e aplicado no próximo cold start. Este helper só registra
/// o número do patch atual e avisa no debug se houver atualização.
class ShorebirdService {
  ShorebirdService._();

  static final ShorebirdUpdater _updater = ShorebirdUpdater();

  /// Não bloqueia o startup — chamar com [unawaited] / fire-and-forget.
  static Future<void> verificarEmBackground() async {
    if (kIsWeb) return;
    try {
      final patch = await _updater.readCurrentPatch();
      debugPrint(
        patch == null
            ? 'Shorebird: release sem patch instalado.'
            : 'Shorebird: patch #${patch.number} ativo.',
      );
      final status = await _updater.checkForUpdate();
      if (status == UpdateStatus.outdated) {
        debugPrint(
          'Shorebird: patch novo disponível '
          '(será aplicado no próximo restart se auto_update estiver ativo).',
        );
      }
    } catch (error, stack) {
      // Em builds sem engine Shorebird (flutter run normal), isso é esperado.
      debugPrint('Shorebird: updater indisponível ($error)\n$stack');
    }
  }
}
