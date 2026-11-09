import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/theme/app_theme.dart';
import '../../data/repositories/quiz_repository.dart';
import '../../providers/app_providers.dart';

/// Retorno simplificado ao aluno: uma mensagem acolhedora por categoria.
class RetornoAlunoView extends ConsumerWidget {
  const RetornoAlunoView({super.key, required this.aplicacaoId});

  final int aplicacaoId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final retorno = ref.watch(retornoProvider(aplicacaoId));
    return retorno.when(
      loading: () => const _Aviso(
        icone: Icons.hourglass_top_rounded,
        texto: 'Preparando seu retorno…',
      ),
      error: (_, _) => _Aviso(
        icone: Icons.cloud_off_rounded,
        texto: 'Não foi possível carregar seu retorno agora.',
        acao: TextButton(
          onPressed: () => ref.invalidate(retornoProvider(aplicacaoId)),
          child: const Text('Tentar de novo'),
        ),
      ),
      data: (retorno) => retorno == null || retorno.categorias.isEmpty
          ? const SizedBox.shrink()
          : _Conteudo(retorno: retorno),
    );
  }
}

class _Conteudo extends StatelessWidget {
  const _Conteudo({required this.retorno});

  final RetornoAluno retorno;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          'Seu retorno',
          style: GoogleFonts.figtree(
            fontSize: 20,
            fontWeight: FontWeight.w800,
            letterSpacing: -0.4,
          ),
        ),
        const SizedBox(height: 10),
        for (final categoria in retorno.categorias) ...[
          _CategoriaCard(categoria: categoria),
          const SizedBox(height: 10),
        ],
        if (retorno.ajuda case final ajuda?)
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.surfaceRaised,
              borderRadius: BorderRadius.circular(AppRadii.md),
              border: Border.all(color: AppColors.border),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.call_rounded, color: AppColors.mint),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    ajuda,
                    style: GoogleFonts.figtree(
                      fontSize: 14.5,
                      height: 1.4,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _CategoriaCard extends StatelessWidget {
  const _CategoriaCard({required this.categoria});

  final RetornoCategoria categoria;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: corDoTom(categoria.tom),
        borderRadius: BorderRadius.circular(AppRadii.md),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(categoria.emoji, style: const TextStyle(fontSize: 26)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  categoria.nome,
                  style: GoogleFonts.figtree(
                    color: AppColors.ink,
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  categoria.mensagem,
                  style: GoogleFonts.figtree(
                    color: AppColors.ink,
                    fontSize: 14.5,
                    height: 1.4,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Aviso extends StatelessWidget {
  const _Aviso({required this.icone, required this.texto, this.acao});

  final IconData icone;
  final String texto;
  final Widget? acao;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icone, color: AppColors.muted, size: 20),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            texto,
            style: GoogleFonts.figtree(
              color: AppColors.muted,
              fontSize: 14,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        ?acao,
      ],
    );
  }
}

/// Cores suaves: o retorno nunca deve soar como alarme.
Color corDoTom(String tom) => switch (tom) {
  'bem' => AppColors.pastelMint,
  'atencao' => AppColors.pastelSand,
  'cuidado' => AppColors.pastelRose,
  _ => AppColors.pastelSky,
};
