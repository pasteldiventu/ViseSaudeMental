import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../providers/app_providers.dart';
import '../avatar/avatar_intro_screen.dart';

class TermoScreen extends ConsumerStatefulWidget {
  const TermoScreen({super.key, required this.aplicacaoId, this.onAccepted});

  final int aplicacaoId;
  final Future<void> Function()? onAccepted;

  @override
  ConsumerState<TermoScreen> createState() => _TermoScreenState();
}

class _TermoScreenState extends ConsumerState<TermoScreen> {
  static const _versao = '1.0';
  static const _textoHash =
      '197fe654b4d9646ceda34c8c1b71167f6a74a3db7d1e33b8115c57e6085592e6';

  bool _aceito = false;
  bool _enviando = false;

  Future<void> _continuar() async {
    if (!_aceito || _enviando) return;
    setState(() => _enviando = true);

    try {
      await ref
          .read(apiClientProvider)
          .dio
          .post<void>(
            '/termos',
            data: {'versao': _versao, 'texto_hash': _textoHash},
          );
    } catch (_) {
      // O aceite local permite continuar quando a conexão está indisponível.
    }

    if (!mounted) return;
    if (widget.onAccepted != null) {
      await widget.onAccepted!();
      return;
    }

    await Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(
        builder: (_) => AvatarIntroScreen(aplicacaoId: widget.aplicacaoId),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Termo de participação')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Icon(Icons.verified_user_outlined, size: 64),
              const SizedBox(height: 24),
              const Text(
                'Sua participação é voluntária',
                style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 18),
              const Text(
                'Este questionário faz parte de uma pesquisa sobre saúde '
                'mental de adolescentes. Suas respostas serão tratadas com '
                'privacidade e usadas apenas para fins de pesquisa e cuidado. '
                'Você pode parar de responder a qualquer momento.',
                style: TextStyle(fontSize: 16, height: 1.55),
              ),
              const Spacer(),
              CheckboxListTile(
                value: _aceito,
                onChanged: _enviando
                    ? null
                    : (value) => setState(() => _aceito = value ?? false),
                title: const Text('Li e aceito'),
                controlAffinity: ListTileControlAffinity.leading,
                contentPadding: EdgeInsets.zero,
              ),
              const SizedBox(height: 12),
              FilledButton(
                onPressed: _aceito && !_enviando ? _continuar : null,
                child: _enviando
                    ? const SizedBox.square(
                        dimension: 22,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Continuar'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
