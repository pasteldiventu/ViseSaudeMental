import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/theme/app_theme.dart';
import '../../data/local/app_database.dart';
import '../../providers/app_providers.dart';
import '../common/brand_and_progress.dart';
import '../common/tactile_button.dart';
import '../finish/finish_screen.dart';
import '../questions/question_screen.dart';

class CategoriesScreen extends ConsumerWidget {
  const CategoriesScreen({super.key, required this.aplicacaoId});

  final int aplicacaoId;

  static const _icons = <IconData>[
    Icons.spa_outlined,
    Icons.nightlight_round,
    Icons.favorite_outline_rounded,
    Icons.psychology_outlined,
    Icons.wb_sunny_outlined,
    Icons.auto_awesome_outlined,
  ];

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final database = ref.watch(databaseProvider);
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          tooltip: 'Meus questionários',
          onPressed: () => ref.read(appControllerProvider).voltarParaLista(),
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        title: const Text('CAMINHO'),
        actions: [
          PopupMenuButton<String>(
            onSelected: (value) async {
              if (value == 'home') {
                ref.read(appControllerProvider).voltarParaLista();
              } else if (value == 'clear') {
                await database.limparRespostas(aplicacaoId);
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Respostas locais removidas.'),
                    ),
                  );
                }
              } else {
                await ref.read(appControllerProvider).sair();
              }
            },
            itemBuilder: (_) => const [
              PopupMenuItem(
                value: 'home',
                child: Text('Meus questionários'),
              ),
              PopupMenuItem(
                value: 'clear',
                child: Text('Limpar respostas locais'),
              ),
              PopupMenuItem(value: 'logout', child: Text('Sair')),
            ],
            icon: const Icon(Icons.menu_rounded),
          ),
        ],
      ),
      body: StreamBuilder<List<CachedCategoriaData>>(
        stream: database.observarCategorias(aplicacaoId),
        builder: (context, categorySnapshot) {
          final categories = categorySnapshot.data ?? const [];
          return StreamBuilder<List<LocalProgressData>>(
            stream: database.observarProgresso(aplicacaoId),
            builder: (context, progressSnapshot) {
              final progress = progressSnapshot.data ?? const [];
              return FutureBuilder<List<List<CachedPerguntaData>>>(
                future: Future.wait(
                  categories.map((item) => database.listarPerguntas(item.id)),
                ),
                builder: (context, questionsSnapshot) {
                  final groups = questionsSnapshot.data;
                  if (groups == null) {
                    return const Center(child: CircularProgressIndicator());
                  }

                  final doneFlags = <bool>[];
                  var totalQuestions = 0;
                  var answeredQuestions = 0;
                  for (var i = 0; i < categories.length; i++) {
                    final questions = groups[i];
                    totalQuestions += questions.length;
                    final answeredInCategory = questions
                        .where(
                          (q) => progress.any((a) => a.perguntaId == q.id),
                        )
                        .length;
                    answeredQuestions += answeredInCategory;
                    final done =
                        questions.isNotEmpty &&
                        answeredInCategory == questions.length;
                    doneFlags.add(done);
                  }
                  final doneCount = doneFlags.where((d) => d).length;
                  final allDone =
                      categories.isNotEmpty &&
                      doneFlags.every((d) => d) &&
                      groups.every((g) => g.isNotEmpty);
                  final currentIndex = doneFlags.indexWhere((d) => !d);

                  return ListView(
                    padding: const EdgeInsets.fromLTRB(20, 4, 20, 36),
                    children: [
                      ProgressBanner(
                        eyebrow: 'Questionário',
                        title: 'Cuide de você,\numa etapa de cada vez.',
                        progressLabel: totalQuestions == 0
                            ? 'Nenhuma pergunta disponível'
                            : '$answeredQuestions de $totalQuestions perguntas',
                        value: totalQuestions == 0
                            ? 0
                            : answeredQuestions / totalQuestions,
                      ),
                      if (currentIndex >= 0 && !allDone) ...[
                        const SizedBox(height: 14),
                        SoftPromptCard(
                          title: 'Continuar: ${categories[currentIndex].titulo}',
                          actionLabel: 'Abrir →',
                          onTap: () => Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => QuestionScreen(
                                aplicacaoId: aplicacaoId,
                                categoria: categories[currentIndex],
                              ),
                            ),
                          ),
                        ),
                      ],
                      const SizedBox(height: 22),
                      Text(
                        'Etapas · $doneCount/${categories.length}',
                        style: GoogleFonts.figtree(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 1.2,
                          color: AppColors.muted,
                        ),
                      ),
                      const SizedBox(height: 12),
                      if (categories.isEmpty)
                        Padding(
                          padding: const EdgeInsets.only(top: 40),
                          child: Text(
                            'Nenhuma categoria disponível.',
                            textAlign: TextAlign.center,
                            style: GoogleFonts.figtree(color: AppColors.muted),
                          ),
                        )
                      else
                        GridView.builder(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          itemCount: categories.length,
                          gridDelegate:
                              const SliverGridDelegateWithFixedCrossAxisCount(
                                crossAxisCount: 2,
                                crossAxisSpacing: 12,
                                mainAxisSpacing: 12,
                                childAspectRatio: 0.92,
                              ),
                          itemBuilder: (context, i) {
                            final answered = progress
                                .where(
                                  (item) =>
                                      item.categoriaId == categories[i].id,
                                )
                                .length;
                            return _PastelCategoryCard(
                              title: categories[i].titulo,
                              subtitle: groups[i].isEmpty
                                  ? 'Sem perguntas'
                                  : doneFlags[i]
                                  ? 'Concluído'
                                  : '$answered de ${groups[i].length}',
                              color: AppColors.pastelFor(i),
                              icon: _icons[i % _icons.length],
                              done: doneFlags[i],
                              highlight: i == currentIndex && !allDone,
                              onTap: () => Navigator.of(context).push(
                                MaterialPageRoute<void>(
                                  builder: (_) => QuestionScreen(
                                    aplicacaoId: aplicacaoId,
                                    categoria: categories[i],
                                  ),
                                ),
                              ),
                            );
                          },
                        ),
                      if (allDone) ...[
                        const SizedBox(height: 18),
                        TactileButton(
                          onPressed: () => Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) =>
                                  FinishScreen(aplicacaoId: aplicacaoId),
                            ),
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.check_circle_rounded),
                              SizedBox(width: 10),
                              Text('Concluir questionário'),
                            ],
                          ),
                        ),
                      ],
                    ],
                  );
                },
              );
            },
          );
        },
      ),
    );
  }
}

class _PastelCategoryCard extends StatelessWidget {
  const _PastelCategoryCard({
    required this.title,
    required this.subtitle,
    required this.color,
    required this.icon,
    required this.done,
    required this.highlight,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final Color color;
  final IconData icon;
  final bool done;
  final bool highlight;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: color,
      borderRadius: BorderRadius.circular(AppRadii.lg),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadii.lg),
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadii.lg),
            border: highlight
                ? Border.all(color: AppColors.mintDeep, width: 2.5)
                : null,
          ),
          padding: const EdgeInsets.fromLTRB(14, 16, 14, 14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(icon, color: AppColors.ink.withValues(alpha: 0.55), size: 26),
                  const Spacer(),
                  if (done)
                    const Icon(
                      Icons.check_circle_rounded,
                      color: AppColors.mintDeep,
                      size: 22,
                    ),
                ],
              ),
              const Spacer(),
              Text(
                title,
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
                style: GoogleFonts.figtree(
                  color: AppColors.ink,
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                  height: 1.15,
                  letterSpacing: -0.3,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                subtitle,
                style: GoogleFonts.figtree(
                  color: AppColors.ink.withValues(alpha: 0.55),
                  fontSize: 12.5,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
