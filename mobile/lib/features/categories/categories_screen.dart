import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/theme/app_theme.dart';
import '../../data/local/app_database.dart';
import '../../providers/app_providers.dart';
import '../finish/finish_screen.dart';
import '../questions/question_screen.dart';

class CategoriesScreen extends ConsumerWidget {
  const CategoriesScreen({super.key, required this.aplicacaoId});

  final int aplicacaoId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final database = ref.watch(databaseProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('CATEGORIAS'),
        actions: [
          PopupMenuButton<String>(
            onSelected: (value) async {
              if (value == 'clear') {
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
                value: 'clear',
                child: Text('Limpar respostas locais'),
              ),
              PopupMenuItem(value: 'logout', child: Text('Sair')),
            ],
            icon: const Icon(Icons.more_vert),
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
                  final allDone =
                      groups.isNotEmpty &&
                      groups.every(
                        (questions) =>
                            questions.isNotEmpty &&
                            questions.every(
                              (question) => progress.any(
                                (answer) => answer.perguntaId == question.id,
                              ),
                            ),
                      );
                  return ListView(
                    padding: const EdgeInsets.fromLTRB(18, 12, 18, 28),
                    children: [
                      const Text(
                        'Escolha uma categoria para continuar.',
                        style: TextStyle(color: AppColors.muted, fontSize: 16),
                      ),
                      const SizedBox(height: 14),
                      for (var i = 0; i < categories.length; i++)
                        _CategoryCard(
                          letter: String.fromCharCode(65 + i),
                          category: categories[i],
                          questions: groups[i],
                          answered: progress
                              .where(
                                (item) => item.categoriaId == categories[i].id,
                              )
                              .length,
                          onTap: () => Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => QuestionScreen(
                                aplicacaoId: aplicacaoId,
                                categoria: categories[i],
                              ),
                            ),
                          ),
                        ),
                      if (categories.isEmpty)
                        const Padding(
                          padding: EdgeInsets.only(top: 80),
                          child: Center(
                            child: Text('Nenhuma categoria disponível.'),
                          ),
                        ),
                      if (allDone) ...[
                        const SizedBox(height: 12),
                        FilledButton.icon(
                          onPressed: () => Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) =>
                                  FinishScreen(aplicacaoId: aplicacaoId),
                            ),
                          ),
                          icon: const Icon(Icons.check_circle_outline),
                          label: const Text('Finalizar questionário'),
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

class _CategoryCard extends StatelessWidget {
  const _CategoryCard({
    required this.letter,
    required this.category,
    required this.questions,
    required this.answered,
    required this.onTap,
  });

  final String letter;
  final CachedCategoriaData category;
  final List<CachedPerguntaData> questions;
  final int answered;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final total = questions.length;
    final value = total == 0 ? 0.0 : (answered / total).clamp(0.0, 1.0);
    final done = total > 0 && answered >= total;
    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            children: [
              Row(
                children: [
                  CircleAvatar(
                    backgroundColor: AppColors.mint,
                    foregroundColor: const Color(0xFF07150D),
                    child: Text(
                      letter,
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Text(
                      category.titulo,
                      style: const TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  const Icon(Icons.chevron_right, color: AppColors.mint),
                ],
              ),
              const SizedBox(height: 14),
              LinearProgressIndicator(
                value: value,
                minHeight: 7,
                borderRadius: BorderRadius.circular(8),
                backgroundColor: Colors.white12,
              ),
              const SizedBox(height: 7),
              Align(
                alignment: Alignment.centerRight,
                child: Text(
                  done ? 'Concluído' : '$answered de $total',
                  style: TextStyle(
                    color: done ? AppColors.mint : AppColors.muted,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
