import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/theme/app_theme.dart';
import '../../providers/app_providers.dart';
import '../avatar/avatar_intro_screen.dart';
import '../common/tactile_button.dart';

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
      appBar: AppBar(title: const Text('Participação')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 12, 24, 28),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: AppColors.surfaceRaised,
                  borderRadius: BorderRadius.circular(22),
                  border: Border.all(color: AppColors.border),
                ),
                child: Column(
                  children: [
                    Container(
                      width: 64,
                      height: 64,
                      decoration: BoxDecoration(
                        color: AppColors.mintSoft,
                        shape: BoxShape.circle,
                        border: Border.all(color: AppColors.mint, width: 2),
                      ),
                      child: const Icon(
                        Icons.verified_user_rounded,
                        color: AppColors.mint,
                        size: 32,
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Sua participação\né voluntária',
                      textAlign: TextAlign.center,
                      style: GoogleFonts.figtree(
                        fontSize: 26,
                        fontWeight: FontWeight.w800,
                        height: 1.15,
                        letterSpacing: -0.6,
                      ),
                    ),
                    const SizedBox(height: 14),
                    Text(
                      'Este questionário faz parte de uma pesquisa sobre saúde '
                      'mental de adolescentes. Suas respostas serão tratadas com '
                      'privacidade e usadas apenas para fins de pesquisa e cuidado. '
                      'Você pode parar de responder a qualquer momento.',
                      style: GoogleFonts.figtree(
                        fontSize: 15.5,
                        height: 1.55,
                        color: AppColors.muted,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
              const Spacer(),
              CheckboxListTile(
                value: _aceito,
                onChanged: _enviando
                    ? null
                    : (value) => setState(() => _aceito = value ?? false),
                title: Text(
                  'Li e aceito participar',
                  style: GoogleFonts.figtree(fontWeight: FontWeight.w600),
                ),
                controlAffinity: ListTileControlAffinity.leading,
                contentPadding: EdgeInsets.zero,
              ),
              const SizedBox(height: 10),
              TactileButton(
                enabled: _aceito && !_enviando,
                onPressed: _aceito && !_enviando ? _continuar : null,
                child: _enviando
                    ? const SizedBox.square(
                        dimension: 22,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.4,
                          color: Color(0xFF062016),
                        ),
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
