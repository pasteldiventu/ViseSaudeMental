import 'dart:math' as math;

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
  const CartaoSecao({
    super.key,
    required this.titulo,
    required this.child,
    this.subtitulo,
    this.acao,
  });

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
          borderRadius: BorderRadius.circular(AppRadii.lg),
        ),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          titulo,
                          style: GoogleFonts.figtree(
                            fontWeight: FontWeight.w800,
                            fontSize: 17,
                            letterSpacing: -0.3,
                          ),
                        ),
                        if (subtitulo != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 2),
                            child: Text(
                              subtitulo!,
                              style: GoogleFonts.figtree(
                                color: AppColors.muted,
                                fontSize: 12.5,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                  ?acao,
                ],
              ),
              const SizedBox(height: 14),
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
        borderRadius: BorderRadius.circular(AppRadii.md),
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
    this.hero = false,
    this.progresso,
    this.onTap,
  });

  final String rotulo;
  final String valor;
  final String detalhe;
  final Color cor;
  final bool destaque;

  /// Cartão principal com gradiente menta.
  final bool hero;
  final double? progresso;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final texto = hero ? AppColors.ink : AppColors.text;
    final apagado = hero
        ? AppColors.ink.withValues(alpha: 0.7)
        : AppColors.muted;
    final conteudo = Padding(
      padding: const EdgeInsets.fromLTRB(16, 14, 12, 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  rotulo,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: GoogleFonts.figtree(
                    color: texto,
                    fontSize: 13.5,
                    fontWeight: FontWeight.w700,
                    height: 1.2,
                  ),
                ),
              ),
              if (onTap != null)
                Container(
                  width: 28,
                  height: 28,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: hero ? AppColors.ink : Colors.transparent,
                    border: hero ? null : Border.all(color: AppColors.border),
                  ),
                  child: Icon(
                    Icons.north_east_rounded,
                    size: 15,
                    color: hero ? AppColors.mint : AppColors.text,
                  ),
                )
              else if (destaque)
                Container(
                  width: 10,
                  height: 10,
                  margin: const EdgeInsets.only(top: 4, right: 4),
                  decoration: BoxDecoration(color: cor, shape: BoxShape.circle),
                ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            valor,
            style: GoogleFonts.figtree(
              fontSize: 32,
              height: 1,
              fontWeight: FontWeight.w800,
              letterSpacing: -1,
              color: hero ? AppColors.ink : (destaque ? cor : AppColors.text),
            ),
          ),
          if (progresso != null) ...[
            const SizedBox(height: 10),
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: LinearProgressIndicator(
                value: progresso!.clamp(0, 1),
                minHeight: 6,
                color: hero ? AppColors.ink : AppColors.mint,
                backgroundColor: hero
                    ? AppColors.ink.withValues(alpha: 0.15)
                    : AppColors.pathLine,
              ),
            ),
          ],
          const SizedBox(height: 8),
          Text(
            detalhe,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: GoogleFonts.figtree(
              color: apagado,
              fontSize: 12,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
    return ClipRRect(
      borderRadius: BorderRadius.circular(AppRadii.md + 2),
      child: Material(
        color: hero ? Colors.transparent : AppColors.surface,
        child: Ink(
          decoration: BoxDecoration(
            gradient: hero ? AppColors.heroGradient : null,
          ),
          child: InkWell(onTap: onTap, child: conteudo),
        ),
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
        final colunas = (c.maxWidth / 170)
            .floor()
            .clamp(2, 6)
            .clamp(1, cards.isEmpty ? 1 : cards.length);
        return Column(
          children: [
            for (var i = 0; i < cards.length; i += colunas) ...[
              if (i > 0) const SizedBox(height: 12),
              IntrinsicHeight(
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (var j = i; j < i + colunas; j++) ...[
                      if (j > i) const SizedBox(width: 12),
                      Expanded(
                        child: j < cards.length ? cards[j] : const SizedBox(),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ],
        );
      },
    );
  }
}

/// Barras em pílula: trilho suave, dias sem conclusões hachurados e o maior dia em destaque.
class GraficoBarras extends StatelessWidget {
  const GraficoBarras({super.key, required this.pontos});

  final List<({String rotulo, int valor})> pontos;

  @override
  Widget build(BuildContext context) {
    final total = pontos.fold<int>(0, (s, p) => s + p.valor);
    if (total == 0) {
      return const Vazio('Nenhum questionário concluído neste período.');
    }
    final maximo = pontos.map((p) => p.valor).reduce(math.max);
    final maior = pontos.indexWhere((p) => p.valor == maximo);
    final cadaRotulo = (pontos.length / 6).ceil().clamp(1, 1000);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SizedBox(
          height: 150,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              for (var i = 0; i < pontos.length; i++)
                Expanded(
                  child: Tooltip(
                    message: '${pontos[i].rotulo}: ${pontos[i].valor}',
                    child: CustomPaint(
                      painter: _BarraPainter(
                        fracao: pontos[i].valor / maximo,
                        vazio: pontos[i].valor == 0,
                        maior: i == maior,
                        valor: pontos[i].valor,
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 8),
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
                        style: GoogleFonts.figtree(
                          color: AppColors.muted,
                          fontSize: 10,
                          fontWeight: FontWeight.w600,
                        ),
                      )
                    : const SizedBox.shrink(),
              ),
          ],
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            Text.rich(
              TextSpan(
                text: '$total ',
                style: GoogleFonts.figtree(
                  fontWeight: FontWeight.w800,
                  color: AppColors.text,
                ),
                children: [
                  TextSpan(
                    text: 'conclusão(ões)',
                    style: GoogleFonts.figtree(
                      fontWeight: FontWeight.w500,
                      color: AppColors.muted,
                    ),
                  ),
                ],
              ),
              style: const TextStyle(fontSize: 12.5),
            ),
            const Spacer(),
            _PontoLegenda(cor: AppColors.mint, rotulo: 'Maior dia'),
            const SizedBox(width: 10),
            const _PontoLegenda(
              cor: AppColors.surfaceSoft,
              rotulo: 'Sem conclusões',
              hachurado: true,
            ),
          ],
        ),
      ],
    );
  }
}

class _PontoLegenda extends StatelessWidget {
  const _PontoLegenda({
    required this.cor,
    required this.rotulo,
    this.hachurado = false,
  });

  final Color cor;
  final String rotulo;
  final bool hachurado;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        SizedBox(
          width: 10,
          height: 10,
          child: CustomPaint(
            painter: hachurado ? _HachuraPainter(raio: 5) : null,
            child: hachurado
                ? null
                : DecoratedBox(
                    decoration: BoxDecoration(
                      color: cor,
                      shape: BoxShape.circle,
                    ),
                  ),
          ),
        ),
        const SizedBox(width: 5),
        Text(
          rotulo,
          style: GoogleFonts.figtree(color: AppColors.muted, fontSize: 11.5),
        ),
      ],
    );
  }
}

void _hachurar(
  Canvas canvas,
  RRect area, {
  Color fundo = AppColors.surfaceRaised,
  Color linha = AppColors.border,
}) {
  canvas.save();
  canvas.clipRRect(area);
  canvas.drawRRect(area, Paint()..color = fundo);
  final traco = Paint()
    ..color = linha
    ..strokeWidth = 2;
  final r = area.outerRect;
  for (var x = r.left - r.height; x < r.right; x += 6) {
    canvas.drawLine(Offset(x, r.bottom), Offset(x + r.height, r.top), traco);
  }
  canvas.restore();
}

class _HachuraPainter extends CustomPainter {
  const _HachuraPainter({required this.raio});

  final double raio;

  @override
  void paint(Canvas canvas, Size size) => _hachurar(
    canvas,
    RRect.fromRectAndRadius(Offset.zero & size, Radius.circular(raio)),
  );

  @override
  bool shouldRepaint(_HachuraPainter old) => old.raio != raio;
}

class _BarraPainter extends CustomPainter {
  const _BarraPainter({
    required this.fracao,
    required this.vazio,
    required this.maior,
    required this.valor,
  });

  final double fracao;
  final bool vazio;
  final bool maior;
  final int valor;

  static const _espacoBolha = 26.0;

  @override
  void paint(Canvas canvas, Size size) {
    final largura = math.min(size.width * 0.64, 22.0).clamp(2.0, 22.0);
    final x = (size.width - largura) / 2;
    final raio = Radius.circular(largura / 2);
    final trilho = RRect.fromLTRBR(
      x,
      _espacoBolha,
      x + largura,
      size.height,
      raio,
    );
    if (vazio) {
      _hachurar(canvas, trilho);
      return;
    }
    canvas.drawRRect(trilho, Paint()..color = AppColors.surfaceRaised);
    final altura = math.max(largura, (size.height - _espacoBolha) * fracao);
    final barra = RRect.fromLTRBR(
      x,
      size.height - altura,
      x + largura,
      size.height,
      raio,
    );
    canvas.drawRRect(
      barra,
      Paint()
        ..color = maior
            ? AppColors.mint
            : AppColors.mintDeep.withValues(alpha: 0.75),
    );
    if (!maior) return;
    final texto = TextPainter(
      text: TextSpan(
        text: '$valor',
        style: GoogleFonts.figtree(
          color: AppColors.bubbleText,
          fontSize: 11,
          fontWeight: FontWeight.w800,
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    final larguraBolha = math.max(28.0, texto.width + 14);
    final centro = size.width / 2;
    final topo = size.height - altura - 24;
    canvas.drawRRect(
      RRect.fromLTRBR(
        centro - larguraBolha / 2,
        topo,
        centro + larguraBolha / 2,
        topo + 20,
        const Radius.circular(10),
      ),
      Paint()..color = AppColors.bubble,
    );
    texto.paint(
      canvas,
      Offset(centro - texto.width / 2, topo + (20 - texto.height) / 2),
    );
  }

  @override
  bool shouldRepaint(_BarraPainter old) =>
      old.fracao != fracao ||
      old.vazio != vazio ||
      old.maior != maior ||
      old.valor != valor;
}

/// Medidor semicircular: parte cheia = concluíram; trilho hachurado = pendentes.
class MedidorParticipacao extends StatelessWidget {
  const MedidorParticipacao({
    super.key,
    required this.participacao,
    required this.concluiram,
    required this.pendentes,
  });

  final double? participacao;
  final int concluiram;
  final int pendentes;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 280),
          child: AspectRatio(
            aspectRatio: 2 / 1.12,
            child: Stack(
              children: [
                Positioned.fill(
                  child: CustomPaint(
                    painter: _MedidorPainter(
                      (participacao ?? 0).clamp(0.0, 1.0),
                    ),
                  ),
                ),
                Align(
                  alignment: Alignment.bottomCenter,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        formatarPct(participacao),
                        style: GoogleFonts.figtree(
                          fontSize: 34,
                          fontWeight: FontWeight.w800,
                          letterSpacing: -1,
                          height: 1,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'dos alunos concluíram',
                        style: GoogleFonts.figtree(
                          color: AppColors.muted,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 14),
        Wrap(
          alignment: WrapAlignment.center,
          spacing: 16,
          runSpacing: 6,
          children: [
            _LegendaValor(
              cor: AppColors.mint,
              rotulo: 'Concluíram',
              valor: concluiram,
            ),
            _LegendaValor(
              cor: AppColors.surfaceSoft,
              rotulo: 'Pendentes',
              valor: pendentes,
              hachurado: true,
            ),
          ],
        ),
      ],
    );
  }
}

class _LegendaValor extends StatelessWidget {
  const _LegendaValor({
    required this.cor,
    required this.rotulo,
    required this.valor,
    this.hachurado = false,
  });

  final Color cor;
  final String rotulo;
  final int valor;
  final bool hachurado;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        _PontoLegenda(cor: cor, rotulo: rotulo, hachurado: hachurado),
        const SizedBox(width: 4),
        Text(
          '$valor',
          style: GoogleFonts.figtree(
            fontWeight: FontWeight.w800,
            fontSize: 12.5,
          ),
        ),
      ],
    );
  }
}

class _MedidorPainter extends CustomPainter {
  const _MedidorPainter(this.valor);

  final double valor;

  @override
  void paint(Canvas canvas, Size size) {
    final espessura = size.width * 0.12;
    final raio = size.width / 2 - espessura / 2;
    final centro = Offset(size.width / 2, raio + espessura / 2);
    final externo = Rect.fromCircle(
      center: centro,
      radius: raio + espessura / 2,
    );
    final interno = Rect.fromCircle(
      center: centro,
      radius: raio - espessura / 2,
    );

    final anel = Path()
      ..arcTo(externo, math.pi, math.pi, true)
      ..arcTo(interno, 0, -math.pi, false)
      ..close();
    canvas.save();
    canvas.clipPath(anel);
    _hachurar(canvas, RRect.fromRectAndRadius(externo, Radius.zero));
    canvas.restore();

    if (valor <= 0) return;
    canvas.drawArc(
      Rect.fromCircle(center: centro, radius: raio),
      math.pi,
      math.pi * valor,
      false,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = espessura
        ..strokeCap = StrokeCap.round
        ..shader = const LinearGradient(
          colors: [AppColors.mintDeep, AppColors.mint],
        ).createShader(externo),
    );
  }

  @override
  bool shouldRepaint(_MedidorPainter old) => old.valor != valor;
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
          decoration: BoxDecoration(
            color: corDoNivel(nivel),
            shape: BoxShape.circle,
          ),
        ),
        const SizedBox(width: 5),
        Text(
          rotulo,
          style: GoogleFonts.figtree(color: AppColors.muted, fontSize: 12),
        ),
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
    final media = categoria.media == null
        ? ''
        : ' · média ${categoria.media!.toStringAsFixed(1).replaceAll('.', ',')}';
    final faixas = categoria.faixas.where((f) => f.pct > 0).toList();
    return Padding(
      padding: const EdgeInsets.only(top: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            categoria.categoria,
            style: GoogleFonts.figtree(fontWeight: FontWeight.w700),
          ),
          Text(
            '${categoria.questionario} · ${categoria.avaliacoes} avaliação(ões)$media',
            style: GoogleFonts.figtree(color: AppColors.muted, fontSize: 12),
          ),
          const SizedBox(height: 8),
          SizedBox(
            height: 24,
            child: Row(
              children: [
                for (var i = 0; i < faixas.length; i++)
                  Expanded(
                    flex: (faixas[i].pct * 1000).round().clamp(1, 1000),
                    child: Padding(
                      padding: EdgeInsets.only(left: i == 0 ? 0 : 3),
                      child: Tooltip(
                        message:
                            '${faixas[i].rotulo}: ${faixas[i].qtd} (${formatarPct(faixas[i].pct)})',
                        child: Container(
                          decoration: BoxDecoration(
                            color: corDoNivel(faixas[i].nivel),
                            borderRadius: BorderRadius.circular(99),
                          ),
                          alignment: Alignment.center,
                          child: faixas[i].pct >= 0.14
                              ? Text(
                                  formatarPct(faixas[i].pct),
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
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
