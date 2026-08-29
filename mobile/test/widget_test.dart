import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:vise_sma_app/core/theme/app_theme.dart';
import 'package:vise_sma_app/features/common/avatar_bubble.dart';

void main() {
  testWidgets('renderiza a identidade visual do app', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.dark,
        home: const Scaffold(body: AvatarBubble(message: 'Olá!')),
      ),
    );

    expect(find.text('Olá!'), findsOneWidget);
    expect(find.byIcon(Icons.favorite_rounded), findsOneWidget);
  });
}
