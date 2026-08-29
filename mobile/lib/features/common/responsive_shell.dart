import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

/// Centraliza o conteúdo em telas largas (web/desktop) sem alterar o fluxo mobile.
class ResponsiveShell extends StatelessWidget {
  const ResponsiveShell({super.key, required this.child, this.maxWidth = 560});

  final Widget child;
  final double maxWidth;

  @override
  Widget build(BuildContext context) {
    if (!kIsWeb && MediaQuery.sizeOf(context).width < maxWidth + 48) {
      return child;
    }
    return ColoredBox(
      color: AppColors.background,
      child: Center(
        child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: maxWidth),
          child: child,
        ),
      ),
    );
  }
}
