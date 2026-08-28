import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/theme/app_theme.dart';
import 'features/auth/login_screen.dart';
import 'features/avatar/avatar_intro_screen.dart';
import 'features/categories/categories_screen.dart';
import 'features/termo/termo_screen.dart';
import 'providers/app_providers.dart';

class ViseApp extends ConsumerStatefulWidget {
  const ViseApp({super.key});

  @override
  ConsumerState<ViseApp> createState() => _ViseAppState();
}

class _ViseAppState extends ConsumerState<ViseApp> {
  @override
  void initState() {
    super.initState();
    Future.microtask(ref.read(appControllerProvider).inicializar);
  }

  @override
  Widget build(BuildContext context) {
    final controller = ref.watch(appControllerProvider);
    return MaterialApp(
      title: 'Vise Saúde Mental',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.dark,
      home: _home(controller),
    );
  }

  Widget _home(AppController controller) {
    if (controller.carregando) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (!controller.autenticado || controller.aplicacaoId == null) {
      return const LoginScreen();
    }
    if (!controller.termoAceito) {
      return TermoScreen(
        aplicacaoId: controller.aplicacaoId!,
        onAccepted: controller.registrarTermoAceito,
      );
    }
    if (!controller.avatarIntroduzido) {
      return AvatarIntroScreen(
        aplicacaoId: controller.aplicacaoId!,
        onStarted: controller.concluirAvatarIntro,
      );
    }
    return CategoriesScreen(aplicacaoId: controller.aplicacaoId!);
  }
}
