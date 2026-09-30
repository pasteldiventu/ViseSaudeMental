import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/theme/app_theme.dart';
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
      'escolha o que mais combina com o que você sente.';

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
      appBar: AppBar(title: const Text('Bem-vindo')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 16, 24, 28),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Vamos começar\ncom calma.',
                style: GoogleFonts.figtree(
                  fontSize: 36,
                  fontWeight: FontWeight.w800,
                  height: 1.05,
                  letterSpacing: -1.1,
                ),
              ),
              const SizedBox(height: 12),
              Text(
                'Um passo de cada vez. Você pode voltar quando quiser.',
                style: GoogleFonts.figtree(
                  color: AppColors.muted,
                  fontSize: 15.5,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 28),
              AvatarBubble(message: _mensagem, large: true),
              const Spacer(),
              SizedBox(
                height: 52,
                child: FilledButton(
                  onPressed: _comecar,
                  style: FilledButton.styleFrom(
                    backgroundColor: Colors.white,
                    foregroundColor: AppColors.ink,
                    shape: const StadiumBorder(),
                    textStyle: GoogleFonts.figtree(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  child: const Text('Explorar etapas'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
