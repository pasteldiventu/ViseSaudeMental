import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_theme.dart';
import '../../../data/repositories/cadastros_repository.dart';
import '../../common/ui_kit.dart';
import '../painel/painel_widgets.dart'
    show corDoNivel, corHex, formatarDataHora;

/// Resultado de um aluno: situação, observação, classificação por categoria e as respostas.
class ResultadoAlunoView extends StatelessWidget {
  const ResultadoAlunoView({
    super.key,
    required this.resultado,
    this.onAbrirAluno,
    this.onAbrirAplicacao,
  });

  final ResultadoAluno resultado;
  final VoidCallback? onAbrirAluno;
  final VoidCallback? onAbrirAplicacao;

  @override
  Widget build(BuildContext context) {
    final r = resultado;
    final cor = corDoNivel(r.nivel);
    final local = [
      r.turma,
      if (r.turno != null && r.turno!.isNotEmpty)
        '${r.turno![0].toUpperCase()}${r.turno!.substring(1)}',
      r.escola,
    ].whereType<String>().where((s) => s.isNotEmpty).join(' · ');
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _Cartao(
          borda: Border(top: BorderSide(color: cor, width: 5)),
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              InkWell(
                onTap: onAbrirAluno,
                child: Text(
                  r.aluno,
                  style: GoogleFonts.figtree(
                    fontSize: 22,
                    fontWeight: FontWeight.w800,
                    height: 1.2,
                  ),
                ),
              ),
              if (local.isNotEmpty) ...[
                const SizedBox(height: 2),
                Text(local, style: const TextStyle(color: AppColors.muted)),
              ],
              const SizedBox(height: 12),
              Selo(r.nivelRotulo, cor: cor, solido: true),
              const SizedBox(height: 12),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.surfaceRaised,
                  borderRadius: BorderRadius.circular(AppRadii.sm),
                ),
                child: Text.rich(
                  TextSpan(
                    children: [
                      const TextSpan(
                        text: 'Observação: ',
                        style: TextStyle(fontWeight: FontWeight.w800),
                      ),
                      TextSpan(text: r.observacao),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 8),
              _Info('Questionário', r.questionario ?? '—'),
              _Info(
                'Aplicação',
                r.codigoSala == null
                    ? '#${r.aplicacaoId}'
                    : 'Sala ${r.codigoSala}',
                onTap: onAbrirAplicacao,
              ),
              _Info('Concluído em', formatarDataHora(r.concluidoEm)),
              _Info(
                'Perguntas respondidas',
                '${r.respondidas} de ${r.totalPerguntas}',
              ),
            ],
          ),
        ),
        for (final categoria in r.categorias) ...[
          const SizedBox(height: 12),
          _CategoriaCard(categoria: categoria),
        ],
      ],
    );
  }
}

/// Cartão arredondado com uma faixa colorida em um dos lados.
class _Cartao extends StatelessWidget {
  const _Cartao({
    required this.borda,
    required this.padding,
    required this.child,
  });

  final Border borda;
  final EdgeInsets padding;
  final Widget child;

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(AppRadii.md),
    child: Container(
      decoration: BoxDecoration(color: AppColors.surface, border: borda),
      padding: padding,
      child: child,
    ),
  );
}

class _Info extends StatelessWidget {
  const _Info(this.rotulo, this.valor, {this.onTap});

  final String rotulo;
  final String valor;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 150,
            child: Text(
              rotulo,
              style: const TextStyle(color: AppColors.muted, fontSize: 13),
            ),
          ),
          Expanded(
            child: Text(
              valor,
              style: TextStyle(
                fontWeight: FontWeight.w700,
                color: onTap == null ? AppColors.text : AppColors.mint,
              ),
            ),
          ),
        ],
      ),
    ),
  );
}

class _CategoriaCard extends StatelessWidget {
  const _CategoriaCard({required this.categoria});

  final CategoriaResultado categoria;

  @override
  Widget build(BuildContext context) {
    final c = categoria;
    final corNivel = corDoNivel(c.nivel);
    return _Cartao(
      borda: Border(
        left: BorderSide(color: corHex(c.cor) ?? AppColors.locked, width: 5),
      ),
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            c.nome,
            style: GoogleFonts.figtree(
              fontSize: 17,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 6,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Selo(c.rotulo ?? 'Sem regra de classificação', cor: corNivel),
              if (c.total != null)
                Text(
                  _pontos(c.total!),
                  style: const TextStyle(color: AppColors.muted, fontSize: 13),
                ),
            ],
          ),
          if (c.descricao != null && c.descricao!.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              c.descricao!,
              style: const TextStyle(color: AppColors.muted, height: 1.35),
            ),
          ],
          const SizedBox(height: 6),
          for (final p in c.perguntas)
            Container(
              padding: const EdgeInsets.symmetric(vertical: 10),
              decoration: const BoxDecoration(
                border: Border(top: BorderSide(color: AppColors.pathLine)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${p.numero}. ${p.texto}',
                    style: const TextStyle(color: AppColors.muted, height: 1.3),
                  ),
                  const SizedBox(height: 4),
                  if (p.respondida)
                    Wrap(
                      spacing: 8,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        Text(
                          [
                            if (p.emoji != null && p.emoji!.isNotEmpty) p.emoji,
                            p.resposta ?? '',
                          ].join(' '),
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                        if (p.pontos != null)
                          Selo(
                            _pontos(p.pontos!.toDouble()),
                            cor: AppColors.muted,
                          ),
                      ],
                    )
                  else
                    const Text(
                      'Não respondida',
                      style: TextStyle(
                        color: AppColors.muted,
                        fontStyle: FontStyle.italic,
                      ),
                    ),
                ],
              ),
            ),
          if (c.perguntas.isEmpty)
            const Padding(
              padding: EdgeInsets.only(bottom: 10),
              child: Text(
                'Nenhuma pergunta desta categoria.',
                style: TextStyle(color: AppColors.muted),
              ),
            ),
        ],
      ),
    );
  }
}

String _pontos(double valor) {
  final texto = valor == valor.roundToDouble()
      ? valor.toInt().toString()
      : valor
            .toStringAsFixed(2)
            .replaceAll(RegExp(r'0+$'), '')
            .replaceAll('.', ',');
  return '$texto ${texto == '1' ? 'ponto' : 'pontos'}';
}
