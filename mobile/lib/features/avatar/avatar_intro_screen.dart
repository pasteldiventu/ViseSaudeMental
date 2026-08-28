import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../providers/app_providers.dart';
import '../categories/categories_screen.dart';
import '../common/avatar_bubble.dart';

class AvatarIntroScreen extends ConsumerStatefulWidget {
  const AvatarIntroScreen({
    super.key,
    required this.aplicacaoId,
    this.onStarted,
  });

  final int aplicacaoId;
  final VoidCallback? onStarted;

  @override
  ConsumerState<AvatarIntroScreen> createState() => _AvatarIntroScreenState();
}

class _AvatarIntroScreenState extends ConsumerState<AvatarIntroScreen> {
  static const _fallback =
      'Olá! Vou acompanhar você neste questionário. Responda com calma e '
      'escolha as opções que mais combinam com o que você sente.';

  String _mensagem = _fallback;

  @override
  void initState() {
    super.initState();
    Future.microtask(_carregarMensagem);
  }

  Future<void> _carregarMensagem() async {
    try {
      final response = await ref
          .read(apiClientProvider)
          .dio
          .get<dynamic>('/avatar');
      final body = response.data;
      final data = body is Map ? body['data'] : null;
      final message = data is Map ? data['mensagem_padrao']?.toString() : null;
      if (mounted && message != null && message.trim().isNotEmpty) {
        setState(() => _mensagem = message);
      }
    } catch (_) {
      // A mensagem padrão mantém a introdução disponível offline.
    }
  }

  void _comecar() {
    if (widget.onStarted != null) {
      widget.onStarted!();
      return;
    }
    Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(
        builder: (_) => CategoriesScreen(aplicacaoId: widget.aplicacaoId),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('BEM-VINDO')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 32, 24, 28),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              AvatarBubble(message: _mensagem),
              const Spacer(),
              FilledButton(onPressed: _comecar, child: const Text('Começar')),
            ],
          ),
        ),
      ),
    );
  }
}
