import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/theme/app_theme.dart';
import '../../providers/app_providers.dart';

/// Só faz sentido escolher escola para quem enxerga mais de uma.
bool podeFocarEscola(WidgetRef ref) {
  final user = ref.read(appControllerProvider).staffUser;
  if (user == null) return false;
  return user.isSuperuser || user.escolas.map((e) => e.id).toSet().length > 1;
}

void focarEscola(WidgetRef ref, int id, String nome) {
  if (!podeFocarEscola(ref)) return;
  final limpo = nome.split(' · INEP').first.trim();
  final atual = ref.read(escolaFocoProvider);
  if (atual?.id == id && atual?.nome == limpo) return;
  ref.read(escolaFocoProvider.notifier).state = EscolaFoco(id, limpo);
}

void limparFocoEscola(WidgetRef ref) {
  if (ref.read(escolaFocoProvider) != null) {
    ref.read(escolaFocoProvider.notifier).state = null;
  }
}

/// Cartão do menu: mostra a escola em foco e permite trocar ou voltar a todas.
class SeletorEscolaFoco extends ConsumerWidget {
  const SeletorEscolaFoco({super.key});

  Future<void> _escolher(BuildContext context, WidgetRef ref) async {
    final opcoes = await ref.read(opcoesRelatorioProvider.future);
    if (!context.mounted) return;
    final atual = ref.read(escolaFocoProvider);
    final escolhida = await showModalBottomSheet<int>(
      context: context,
      backgroundColor: AppColors.surface,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (ctx) => SafeArea(
        child: ConstrainedBox(
          constraints: BoxConstraints(
            maxHeight: MediaQuery.sizeOf(ctx).height * 0.7,
          ),
          child: ListView(
            shrinkWrap: true,
            children: [
              ListTile(
                leading: const Icon(Icons.public_rounded),
                title: const Text('Todas as escolas'),
                selected: atual == null,
                onTap: () => Navigator.pop(ctx, 0),
              ),
              for (final escola in opcoes.escolas)
                ListTile(
                  leading: const Icon(Icons.school_outlined),
                  title: Text(escola.rotulo),
                  selected: atual?.id == int.tryParse(escola.valor),
                  onTap: () => Navigator.pop(ctx, int.tryParse(escola.valor)),
                ),
            ],
          ),
        ),
      ),
    );
    if (escolhida == null) return;
    if (escolhida == 0) {
      limparFocoEscola(ref);
      return;
    }
    final nome = opcoes.escolas
        .firstWhere((e) => int.tryParse(e.valor) == escolhida)
        .rotulo;
    focarEscola(ref, escolhida, nome);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (!podeFocarEscola(ref)) return const SizedBox.shrink();
    final foco = ref.watch(escolaFocoProvider);
    return Material(
      color: foco == null ? AppColors.surfaceRaised : AppColors.mintSoft,
      borderRadius: BorderRadius.circular(AppRadii.md),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppRadii.md),
        onTap: () => _escolher(context, ref),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 10, 6, 10),
          child: Row(
            children: [
              Icon(
                Icons.school_outlined,
                size: 20,
                color: foco == null ? AppColors.muted : AppColors.mint,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Escola em foco',
                      style: GoogleFonts.figtree(
                        color: AppColors.muted,
                        fontSize: 11.5,
                      ),
                    ),
                    Text(
                      foco?.nome ?? 'Todas as escolas',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.figtree(
                        fontWeight: FontWeight.w700,
                        fontSize: 13.5,
                      ),
                    ),
                  ],
                ),
              ),
              if (foco != null)
                IconButton(
                  tooltip: 'Ver todas as escolas',
                  icon: const Icon(Icons.close_rounded, size: 18),
                  onPressed: () => limparFocoEscola(ref),
                )
              else
                const Padding(
                  padding: EdgeInsets.only(right: 8),
                  child: Icon(
                    Icons.expand_more_rounded,
                    color: AppColors.muted,
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
