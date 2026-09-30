import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/theme/app_theme.dart';
import '../../data/local/app_database.dart';
import '../../providers/app_providers.dart';
import '../common/brand_and_progress.dart';
import '../common/tactile_button.dart';
import '../common/ui_kit.dart';

class HomeQuestionariosScreen extends ConsumerWidget {
  const HomeQuestionariosScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final database = ref.watch(databaseProvider);
    final controller = ref.watch(appControllerProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Meus questionários'),
        actions: [
          BotaoRedondo(
            icone: Icons.logout_rounded,
            tooltip: 'Sair',
            onPressed: () => ref.read(appControllerProvider).sair(),
          ),
          const SizedBox(width: 12),
        ],
      ),
      body: StreamBuilder<List<CachedAplicacaoData>>(
        stream: database.observarAplicacoes(),
        builder: (context, snapshot) {
          final apps = snapshot.data ?? const [];
          if (snapshot.connectionState == ConnectionState.waiting &&
              apps.isEmpty) {
            return const Center(child: CircularProgressIndicator());
          }
          final finalizados = apps
              .where((a) => a.sincronizadaServidor || a.concluidaLocalmente)
              .length;
          return ListView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 36),
            children: [
              HeroCard(
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Olá! 👋',
                            style: GoogleFonts.figtree(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'Cuide de você, um questionário de cada vez.',
                            style: GoogleFonts.figtree(
                              fontSize: 22,
                              fontWeight: FontWeight.w800,
                              height: 1.15,
                              letterSpacing: -0.5,
                            ),
                          ),
                          const SizedBox(height: 12),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 6,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.ink,
                              borderRadius: BorderRadius.circular(99),
                            ),
                            child: Text(
                              apps.isEmpty
                                  ? 'Nenhum questionário ainda'
                                  : '$finalizados de ${apps.length} finalizado(s)',
                              style: GoogleFonts.figtree(
                                color: AppColors.text,
                                fontWeight: FontWeight.w700,
                                fontSize: 12.5,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    const ViseBrandMark(compact: true),
                  ],
                ),
              ),
              const SizedBox(height: 18),
              _CodigoSalaField(
                codigoInicial: controller.codigoSalaPendente,
                onEntrar: (codigo) async {
                  final ok = await ref
                      .read(appControllerProvider)
                      .entrarComCodigo(codigo);
                  if (!ok && context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text(
                          ref.read(appControllerProvider).erro ??
                              'Não foi possível entrar na sala.',
                        ),
                      ),
                    );
                  }
                },
              ),
              const SizedBox(height: 24),
              SecaoTitulo(
                'Escolha um questionário',
                detalhe: apps.isEmpty ? null : '${apps.length}',
              ),
              if (apps.isEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 48),
                  child: Text(
                    'Nenhum questionário disponível no momento.',
                    textAlign: TextAlign.center,
                    style: GoogleFonts.figtree(color: AppColors.muted),
                  ),
                )
              else
                for (final (i, app) in apps.indexed)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 14),
                    child: _AplicacaoCard(
                      aplicacao: app,
                      cor: AppColors.pastelFor(i),
                    ),
                  ),
            ],
          );
        },
      ),
    );
  }
}

class _CodigoSalaField extends StatefulWidget {
  const _CodigoSalaField({this.codigoInicial, required this.onEntrar});

  final String? codigoInicial;
  final Future<void> Function(String codigo) onEntrar;

  @override
  State<_CodigoSalaField> createState() => _CodigoSalaFieldState();
}

class _CodigoSalaFieldState extends State<_CodigoSalaField> {
  late final TextEditingController _codigo;

  @override
  void initState() {
    super.initState();
    _codigo = TextEditingController(text: widget.codigoInicial ?? '');
  }

  @override
  void dispose() {
    _codigo.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadii.lg),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: const BoxDecoration(
                  color: AppColors.mintSoft,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.qr_code_2_rounded,
                  color: AppColors.mint,
                  size: 20,
                ),
              ),
              const SizedBox(width: 10),
              Text(
                'Tem um código de sala?',
                style: GoogleFonts.figtree(
                  fontWeight: FontWeight.w800,
                  fontSize: 15,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _codigo,
                  textCapitalization: TextCapitalization.characters,
                  decoration: const InputDecoration(
                    hintText: 'Ex.: A1B2C3',
                    isDense: true,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              FilledButton(
                style: FilledButton.styleFrom(minimumSize: const Size(0, 48)),
                onPressed: () => widget.onEntrar(_codigo.text),
                child: const Text('Entrar'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _AplicacaoCard extends ConsumerWidget {
  const _AplicacaoCard({required this.aplicacao, required this.cor});

  final CachedAplicacaoData aplicacao;
  final Color cor;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final database = ref.watch(databaseProvider);
    return StreamBuilder<List<LocalProgressData>>(
      stream: database.observarProgresso(aplicacao.id),
      builder: (context, progressSnapshot) {
        return FutureBuilder<({int total, int respondidas})>(
          future: database.contagemProgresso(aplicacao.id),
          builder: (context, countSnapshot) {
            final total = countSnapshot.data?.total ?? 0;
            final localAnswered =
                progressSnapshot.data?.length ??
                countSnapshot.data?.respondidas ??
                0;
            final finalizado =
                aplicacao.sincronizadaServidor || aplicacao.concluidaLocalmente;
            final value = total == 0
                ? 0.0
                : (localAnswered / total).clamp(0.0, 1.0);
            final label = finalizado
                ? 'Finalizado'
                : total == 0
                ? 'Sem perguntas'
                : '$localAnswered de $total perguntas';
            final action = finalizado
                ? 'Ver'
                : localAnswered > 0
                ? 'Continuar'
                : 'Começar';

            return Material(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(AppRadii.lg),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 48,
                          height: 48,
                          decoration: BoxDecoration(
                            color: cor,
                            borderRadius: BorderRadius.circular(16),
                          ),
                          child: const Icon(
                            Icons.spa_outlined,
                            color: AppColors.ink,
                          ),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Text(
                            aplicacao.titulo,
                            style: GoogleFonts.figtree(
                              fontSize: 18,
                              fontWeight: FontWeight.w800,
                              letterSpacing: -0.4,
                              height: 1.2,
                            ),
                          ),
                        ),
                        if (finalizado)
                          const Icon(
                            Icons.check_circle_rounded,
                            color: AppColors.mint,
                            size: 22,
                          ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(99),
                      child: LinearProgressIndicator(
                        value: finalizado ? 1 : value,
                        minHeight: 8,
                        backgroundColor: AppColors.surfaceSoft,
                        color: AppColors.mint,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            label,
                            style: GoogleFonts.figtree(
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                              color: AppColors.muted,
                            ),
                          ),
                        ),
                        SizedBox(
                          width: 140,
                          child: TactileButton(
                            height: 44,
                            onPressed: () => ref
                                .read(appControllerProvider)
                                .abrirAplicacao(aplicacao.id),
                            child: Text(action),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }
}
