import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/theme/app_theme.dart';

String iniciais(String nome) {
  final partes = nome
      .trim()
      .split(RegExp(r'\s+'))
      .where((p) => p.isNotEmpty)
      .toList();
  if (partes.isEmpty) return '?';
  final primeira = partes.first.characters.first;
  final ultima = partes.length > 1 ? partes.last.characters.first : '';
  return (primeira + ultima).toUpperCase();
}

class AvatarIniciais extends StatelessWidget {
  const AvatarIniciais({
    super.key,
    required this.nome,
    this.tamanho = 46,
    this.fundo,
    this.corTexto,
  });

  final String nome;
  final double tamanho;
  final Color? fundo;
  final Color? corTexto;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: tamanho,
      height: tamanho,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: fundo,
        gradient: fundo == null ? AppColors.heroGradient : null,
      ),
      child: Text(
        iniciais(nome),
        style: GoogleFonts.figtree(
          color: corTexto ?? AppColors.ink,
          fontWeight: FontWeight.w800,
          fontSize: tamanho * 0.36,
        ),
      ),
    );
  }
}

/// Botão circular discreto (sino, sair, filtros…).
class BotaoRedondo extends StatelessWidget {
  const BotaoRedondo({
    super.key,
    required this.icone,
    required this.onPressed,
    this.tooltip,
    this.destaque = false,
  });

  final IconData icone;
  final VoidCallback? onPressed;
  final String? tooltip;
  final bool destaque;

  @override
  Widget build(BuildContext context) {
    final botao = Material(
      color: destaque ? AppColors.mint : AppColors.surfaceRaised,
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onPressed,
        child: SizedBox(
          width: 44,
          height: 44,
          child: Icon(
            icone,
            size: 21,
            color: destaque ? AppColors.ink : AppColors.text,
          ),
        ),
      ),
    );
    return tooltip == null ? botao : Tooltip(message: tooltip!, child: botao);
  }
}

/// "Olá, Nome 👋" com avatar de iniciais e ações à direita.
class SaudacaoHeader extends StatelessWidget {
  const SaudacaoHeader({
    super.key,
    required this.nome,
    required this.subtitulo,
    this.acoes = const [],
    this.leading,
  });

  final String nome;
  final String subtitulo;
  final List<Widget> acoes;
  final Widget? leading;

  @override
  Widget build(BuildContext context) {
    final primeiroNome = nome.trim().split(RegExp(r'\s+')).first;
    return Row(
      children: [
        ?leading,
        if (leading != null) const SizedBox(width: 8),
        AvatarIniciais(nome: nome),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                primeiroNome.isEmpty ? 'Olá 👋' : 'Olá, $primeiroNome 👋',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: GoogleFonts.figtree(
                  fontSize: 21,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.4,
                ),
              ),
              Text(
                subtitulo,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: GoogleFonts.figtree(
                  color: AppColors.muted,
                  fontSize: 13.5,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
        for (final acao in acoes)
          Padding(padding: const EdgeInsets.only(left: 8), child: acao),
      ],
    );
  }
}

/// Título de seção em frase ("Suas salas") com ação opcional ("Ver tudo").
class SecaoTitulo extends StatelessWidget {
  const SecaoTitulo(
    this.titulo, {
    super.key,
    this.acao,
    this.onAcao,
    this.detalhe,
  });

  final String titulo;
  final String? detalhe;
  final String? acao;
  final VoidCallback? onAcao;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Expanded(
            child: Text.rich(
              TextSpan(
                text: titulo,
                children: [
                  if (detalhe != null)
                    TextSpan(
                      text: '  $detalhe',
                      style: GoogleFonts.figtree(
                        color: AppColors.muted,
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                ],
              ),
              style: GoogleFonts.figtree(
                fontSize: 18,
                fontWeight: FontWeight.w800,
                letterSpacing: -0.3,
              ),
            ),
          ),
          if (acao != null) TextButton(onPressed: onAcao, child: Text(acao!)),
        ],
      ),
    );
  }
}

/// Cartão de destaque com gradiente menta e círculos decorativos.
class HeroCard extends StatelessWidget {
  const HeroCard({
    super.key,
    required this.child,
    this.onTap,
    this.padding = const EdgeInsets.fromLTRB(20, 18, 20, 20),
  });

  final Widget child;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(AppRadii.xl),
      child: Material(
        color: Colors.transparent,
        child: Ink(
          decoration: const BoxDecoration(gradient: AppColors.heroGradient),
          child: InkWell(
            onTap: onTap,
            child: Stack(
              children: [
                Positioned(right: -46, bottom: -70, child: _Anel(tamanho: 190)),
                Positioned(right: 60, top: -60, child: _Anel(tamanho: 110)),
                Padding(
                  padding: padding,
                  child: DefaultTextStyle.merge(
                    style: GoogleFonts.figtree(color: AppColors.ink),
                    child: IconTheme.merge(
                      data: const IconThemeData(color: AppColors.ink),
                      child: child,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _Anel extends StatelessWidget {
  const _Anel({required this.tamanho});

  final double tamanho;

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Container(
        width: tamanho,
        height: tamanho,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(
            color: Colors.white.withValues(alpha: 0.14),
            width: tamanho * 0.14,
          ),
        ),
      ),
    );
  }
}

/// Selo em pílula (status, nível, contagem).
class Selo extends StatelessWidget {
  const Selo(this.texto, {super.key, required this.cor, this.solido = false});

  final String texto;
  final Color cor;
  final bool solido;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: solido ? cor : cor.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(99),
      ),
      child: Text(
        texto,
        style: GoogleFonts.figtree(
          color: solido ? AppColors.ink : cor,
          fontSize: 12,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}
