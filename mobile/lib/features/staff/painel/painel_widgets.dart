import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_theme.dart';
import '../../../data/repositories/painel_repository.dart';

const corAlto = Color(0xFFE5645B);

/// Cores dos níveis de atenção (iguais ao painel web e ao Excel).
Color corDoNivel(String? nivel) => switch (nivel) {
  'baixo' => AppColors.mint,
  'moderado' => AppColors.warn,
  'alto' => corAlto,
  _ => AppColors.locked,
};

String formatarPct(double? valor) {
  if (valor == null) return '—';
  final pct = valor * 100;
  return '${pct > 0 && pct < 10 ? pct.toStringAsFixed(1).replaceAll('.', ',') : pct.round()}%';
}

/// "2026-09-30 13:05:00" (UTC) → "30/09 09:05" no horário do aparelho.
String formatarDataHora(String? utc) {
  if (utc == null || utc.isEmpty) return '';
  final data = DateTime.tryParse('${utc.replaceFirst(' ', 'T')}Z')?.toLocal();
  if (data == null) return utc;
  String dois(int n) => n.toString().padLeft(2, '0');
  return '${dois(data.day)}/${dois(data.month)} ${dois(data.hour)}:${dois(data.minute)}';
}

class CartaoSecao extends StatelessWidget {
  const CartaoSecao({super.key, required this.titulo, required this.child, this.subtitulo, this.acao});

  final String titulo;
  final String? subtitulo;
  final Widget child;
  final Widget? acao;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Material(
        color: AppColors.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadii.md),
          side: BorderSide(color: AppColors.border.withValues(alpha: 0.6)),
        ),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(titulo, style: GoogleFonts.figtree(fontWeight: FontWeight.w800, fontSize: 16)),
                        if (subtitulo != null)
                          Text(subtitulo!, style: GoogleFonts.figtree(color: AppColors.muted, fontSize: 12.5)),
                      ],
                    ),
                  ),
                  ?acao,
                ],
              ),
              const SizedBox(height: 12),
              child,
            ],
          ),
        ),
      ),
    );
  }
}

class Vazio extends StatelessWidget {
  const Vazio(this.texto, {super.key});

  final String texto;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppColors.surfaceRaised.withValues(alpha: 0.5),
        borderRadius: BorderRadius.circular(AppRadii.sm),
      ),
      child: Text(
        texto,
        textAlign: TextAlign.center,
        style: GoogleFonts.figtree(color: AppColors.muted),
      ),
    );
  }
}

class KpiCard extends StatelessWidget {
  const KpiCard({
    super.key,
    required this.rotulo,
    required this.valor,
    required this.detalhe,
    this.cor = AppColors.muted,
    this.destaque = false,
    this.progresso,
  });

  final String rotulo;
  final String valor;
  final String detalhe;
  final Color cor;
  final bool destaque;
  final double? progresso;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
      decoration: BoxDecoration(
        color: destaque ? Color.alphaBlend(cor.withValues(alpha: 0.12), AppColors.surface) : AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadii.sm),
        border: Border(top: BorderSide(color: cor, width: 4)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            rotulo.toUpperCase(),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: GoogleFonts.figtree(
              color: AppColors.muted,
              fontSize: 11,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.6,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            valor,
            style: GoogleFonts.figtree(
              fontSize: 28,
              fontWeight: FontWeight.w800,
              color: destaque ? cor : AppColors.text,
            ),
          ),
          if (progresso != null) ...[
            const SizedBox(height: 6),
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: LinearProgressIndicator(value: progresso!.clamp(0, 1), minHeight: 6),
            ),
          ],
          const SizedBox(height: 4),
          Text(
            detalhe,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: GoogleFonts.figtree(color: AppColors.muted, fontSize: 12),
          ),
        ],
      ),
    );
  }
}

/// Grade responsiva: 2 colunas no celular, até 6 em telas largas.
class GradeKpis extends StatelessWidget {
  const GradeKpis({super.key, required this.cards});

  final List<Widget> cards;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, c) {
        final colunas = (c.maxWidth / 170).floor().clamp(2, 6);
        final largura = (c.maxWidth - (colunas - 1) * 10) / colunas;
        return Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [for (final card in cards) SizedBox(width: largura, child: card)],
        );
      },
    );
  }
}

class GraficoBarras extends StatelessWidget {
  const GraficoBarras({super.key, required this.pontos});

  final List<({String rotulo, int valor})> pontos;

  @override
  Widget build(BuildContext context) {
    final total = pontos.fold<int>(0, (s, p) => s + p.valor);
    if (total == 0) return const Vazio('Nenhum questionário concluído neste período.');
    final maximo = pontos.map((p) => p.valor).reduce((a, b) => a > b ? a : b);
    final cadaRotulo = (pontos.length / 6).ceil().clamp(1, 1000);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SizedBox(
          height: 130,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              for (final p in pontos)
                Expanded(
                  child: Tooltip(
                    message: '${p.rotulo}: ${p.valor}',
                    child: Padding(
                      padding: EdgeInsets.symmetric(horizontal: pontos.length > 40 ? 0.5 : 1.5),
                      child: FractionallySizedBox(
                        heightFactor: p.valor == 0 ? 0.01 : (p.valor / maximo).clamp(0.04, 1.0),
                        alignment: Alignment.bottomCenter,
                        child: Container(
                          decoration: BoxDecoration(
                            color: p.valor == 0 ? AppColors.pathLine : AppColors.mint,
                            borderRadius: const BorderRadius.vertical(top: Radius.circular(3)),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 6),
        Row(
          children: [
            for (var i = 0; i < pontos.length; i++)
              Expanded(
                child: i % cadaRotulo == 0
                    ? Text(
                        pontos[i].rotulo,
                        maxLines: 1,
                        overflow: TextOverflow.visible,
                        softWrap: false,
                        style: GoogleFonts.figtree(color: AppColors.muted, fontSize: 10),
                      )
                    : const SizedBox.shrink(),
              ),
          ],
        ),
        const SizedBox(height: 6),
        Text(
          '$total conclusão(ões) · pico de $maximo',
          style: GoogleFonts.figtree(color: AppColors.muted, fontSize: 12),
        ),
      ],
    );
  }
}

class LegendaNiveis extends StatelessWidget {
  const LegendaNiveis({super.key});

  @override
  Widget build(BuildContext context) {
    Widget item(String nivel, String rotulo) => Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 10,
          height: 10,
          decoration: BoxDecoration(color: corDoNivel(nivel), borderRadius: BorderRadius.circular(3)),
        ),
        const SizedBox(width: 5),
        Text(rotulo, style: GoogleFonts.figtree(color: AppColors.muted, fontSize: 12)),
      ],
    );
    return Wrap(
      spacing: 14,
      runSpacing: 6,
      children: [
        item('baixo', 'Adequado'),
        item('moderado', 'Atenção'),
        item('alto', 'Prioritário'),
        item('', 'Sem nível'),
      ],
    );
  }
}

class BarraNiveis extends StatelessWidget {
  const BarraNiveis({super.key, required this.categoria});

  final CategoriaClassificacao categoria;

  @override
  Widget build(BuildContext context) {
    final media = categoria.media == null ? '' : ' · média ${categoria.media!.toStringAsFixed(1).replaceAll('.', ',')}';
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(categoria.categoria, style: GoogleFonts.figtree(fontWeight: FontWeight.w700)),
          Text(
            '${categoria.questionario} · ${categoria.avaliacoes} avaliação(ões)$media',
            style: GoogleFonts.figtree(color: AppColors.muted, fontSize: 12),
          ),
          const SizedBox(height: 6),
          ClipRRect(
            borderRadius: BorderRadius.circular(6),
            child: SizedBox(
              height: 22,
              child: Row(
                children: [
                  for (final f in categoria.faixas)
                    Expanded(
                      flex: (f.pct * 1000).round().clamp(1, 1000),
                      child: Tooltip(
                        message: '${f.rotulo}: ${f.qtd} (${formatarPct(f.pct)})',
                        child: Container(
                          color: corDoNivel(f.nivel),
                          alignment: Alignment.center,
                          child: f.pct >= 0.14
                              ? Text(
                                  formatarPct(f.pct),
                                  style: GoogleFonts.figtree(
                                    color: AppColors.ink,
                                    fontSize: 11,
                                    fontWeight: FontWeight.w800,
                                  ),
                                )
                              : null,
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
