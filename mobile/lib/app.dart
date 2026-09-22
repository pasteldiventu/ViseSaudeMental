import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/theme/app_theme.dart';
import 'features/auth/role_gate_screen.dart';
import 'features/avatar/avatar_intro_screen.dart';
import 'features/categories/categories_screen.dart';
import 'features/common/responsive_shell.dart';
import 'features/home/home_questionarios_screen.dart';
import 'features/staff/staff_home_screen.dart';
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
      title: 'VISE-MT',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.dark,
      locale: const Locale('pt', 'BR'),
      supportedLocales: const [Locale('pt', 'BR')],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      builder: (context, child) =>
          ResponsiveShell(child: child ?? const SizedBox.shrink()),
      home: _home(controller),
    );
  }

  Widget _home(AppController controller) {
    if (controller.inicializando) {
      return const Scaffold(
        body: Center(
          child: CircularProgressIndicator(color: AppColors.mint),
        ),
      );
    }
    if (!controller.autenticado) {
      return const RoleGateScreen();
    }
    if (controller.modoStaff) {
      return const StaffHomeScreen();
    }
    if (!controller.termoAceito) {
      return TermoScreen(onAccepted: controller.registrarTermoAceito);
    }
    final aplicacaoId = controller.aplicacaoId;
    if (aplicacaoId == null) {
      return const HomeQuestionariosScreen();
    }
    if (!controller.avatarIntroduzido) {
      return AvatarIntroScreen(
        aplicacaoId: aplicacaoId,
        onStarted: () {
          controller.concluirAvatarIntro();
        },
      );
    }
    return CategoriesScreen(aplicacaoId: aplicacaoId);
  }
}
