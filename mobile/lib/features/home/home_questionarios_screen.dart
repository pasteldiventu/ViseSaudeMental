import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/theme/app_theme.dart';
import '../../data/local/app_database.dart';
import '../../providers/app_providers.dart';
import '../common/brand_and_progress.dart';
import '../common/tactile_button.dart';

class HomeQuestionariosScreen extends ConsumerWidget {
  const HomeQuestionariosScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final database = ref.watch(databaseProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('MEUS QUESTIONÁRIOS'),
        actions: [
          IconButton(
            tooltip: 'Sair',
            onPressed: () => ref.read(appControllerProvider).sair(),
            icon: const Icon(Icons.logout_rounded),
          ),
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
          return ListView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 36),
            children: [
              const ViseBrandMark(compact: true),
              const SizedBox(height: 18),
              Text(
                'Escolha um questionário',
                style: GoogleFonts.figtree(
                  fontSize: 28,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.8,
                  height: 1.15,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Você pode ter mais de um. Acompanhe o progresso em cada um.',
                style: GoogleFonts.figtree(
                  color: AppColors.muted,
                  fontSize: 15,
                  height: 1.4,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 22),
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
                ...apps.map(
                  (app) => Padding(
                    padding: const EdgeInsets.only(bottom: 14),
                    child: _AplicacaoCard(aplicacao: app),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

class _AplicacaoCard extends ConsumerWidget {
  const _AplicacaoCard({required this.aplicacao});

  final CachedAplicacaoData aplicacao;

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
                aplicacao.sincronizadaServidor ||
                aplicacao.concluidaLocalmente;
            final value = total == 0 ? 0.0 : (localAnswered / total).clamp(0.0, 1.0);
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
              color: AppColors.surfaceSoft,
              borderRadius: BorderRadius.circular(AppRadii.lg),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(18, 18, 18, 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            aplicacao.titulo,
                            style: GoogleFonts.figtree(
                              fontSize: 20,
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
                        backgroundColor: Colors.white12,
                        color: AppColors.mint,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      label,
                      style: GoogleFonts.figtree(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: AppColors.muted,
                      ),
                    ),
                    const SizedBox(height: 14),
                    Align(
                      alignment: Alignment.centerRight,
                      child: SizedBox(
                        width: 148,
                        child: TactileButton(
                          height: 44,
                          onPressed: () => ref
                              .read(appControllerProvider)
                              .abrirAplicacao(aplicacao.id),
                          child: Text(action),
                        ),
                      ),
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
