import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/theme/app_theme.dart';
import '../../data/local/app_database.dart';
import '../../providers/app_providers.dart';
import '../common/avatar_bubble.dart';

class QuestionScreen extends ConsumerStatefulWidget {
  const QuestionScreen({
    super.key,
    required this.aplicacaoId,
    required this.categoria,
  });

  final int aplicacaoId;
  final CachedCategoriaData categoria;

  @override
  ConsumerState<QuestionScreen> createState() => _QuestionScreenState();
}

class _QuestionScreenState extends ConsumerState<QuestionScreen> {
  final _text = TextEditingController();
  List<CachedPerguntaData> _questions = const [];
  List<CachedOpcaoData> _options = const [];
  int _index = 0;
  int? _selected;
  bool _loading = true;
  bool _saving = false;

  CachedPerguntaData get _question => _questions[_index];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final database = ref.read(databaseProvider);
    final questions = await database.listarPerguntas(widget.categoria.id);
    final progress = await database.observarProgresso(widget.aplicacaoId).first;
    final firstPending = questions.indexWhere(
      (question) => !progress.any((answer) => answer.perguntaId == question.id),
    );
    _questions = questions;
    _index = firstPending < 0 ? 0 : firstPending;
    if (questions.isNotEmpty) {
      _options = await database.listarOpcoes(_question.id);
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _answer({int? optionId, String? text}) async {
    if (_saving || (text != null && text.trim().isEmpty)) return;
    setState(() {
      _saving = true;
      _selected = optionId;
    });
    await ref
        .read(quizRepositoryProvider)
        .responder(
          aplicacaoId: widget.aplicacaoId,
          categoriaId: widget.categoria.id,
          perguntaId: _question.id,
          opcaoId: optionId,
          texto: text?.trim(),
        );
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Resposta salva no aparelho.'),
        duration: Duration(milliseconds: 700),
      ),
    );
    await Future<void>.delayed(const Duration(milliseconds: 180));
    if (!mounted) return;
    if (_index == _questions.length - 1) {
      Navigator.of(context).pop();
      return;
    }
    _index++;
    _selected = null;
    _saving = false;
    _text.clear();
    _options = await ref.read(databaseProvider).listarOpcoes(_question.id);
    if (mounted) setState(() {});
  }

  void _previous() {
    if (_index == 0) {
      Navigator.of(context).pop();
      return;
    }
    setState(() {
      _index--;
      _selected = null;
      _text.clear();
      _options = const [];
    });
    ref.read(databaseProvider).listarOpcoes(_question.id).then((items) {
      if (mounted) setState(() => _options = items);
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (_questions.isEmpty) {
      return Scaffold(
        appBar: AppBar(title: Text(widget.categoria.titulo)),
        body: const Center(child: Text('Nenhuma pergunta nesta categoria.')),
      );
    }
    final isText =
        _options.isEmpty ||
        _question.tipo.toLowerCase().contains('text') ||
        _question.tipo.toLowerCase().contains('aberta');
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          onPressed: _previous,
          icon: const Icon(Icons.arrow_back),
        ),
        title: Text(widget.categoria.titulo),
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
          children: [
            Row(
              children: [
                Expanded(
                  child: LinearProgressIndicator(
                    value: (_index + 1) / _questions.length,
                    minHeight: 8,
                    borderRadius: BorderRadius.circular(8),
                    backgroundColor: Colors.white12,
                  ),
                ),
                const SizedBox(width: 12),
                Text(
                  '${_index + 1}/${_questions.length}',
                  style: const TextStyle(color: AppColors.muted),
                ),
              ],
            ),
            const SizedBox(height: 22),
            if (_imagePath != null) ...[
              ClipRRect(
                borderRadius: BorderRadius.circular(18),
                child: _buildImage(_imagePath!),
              ),
              const SizedBox(height: 20),
            ],
            AvatarBubble(message: _question.texto),
            const SizedBox(height: 28),
            if (isText) ...[
              TextField(
                controller: _text,
                maxLines: 5,
                minLines: 3,
                textCapitalization: TextCapitalization.sentences,
                decoration: const InputDecoration(
                  labelText: 'Sua resposta',
                  alignLabelWithHint: true,
                ),
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: _saving ? null : () => _answer(text: _text.text),
                child: const Text('Enviar resposta'),
              ),
            ] else
              for (var i = 0; i < _options.length; i++)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: OutlinedButton(
                    onPressed: _saving
                        ? null
                        : () => _answer(optionId: _options[i].id),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: Colors.white,
                      alignment: Alignment.centerLeft,
                      padding: const EdgeInsets.all(15),
                      side: BorderSide(
                        color: _selected == _options[i].id
                            ? AppColors.mint
                            : const Color(0xFF42606B),
                        width: _selected == _options[i].id ? 2 : 1,
                      ),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                    ),
                    child: Row(
                      children: [
                        CircleAvatar(
                          radius: 17,
                          backgroundColor: _selected == _options[i].id
                              ? AppColors.mint
                              : Colors.white10,
                          foregroundColor: _selected == _options[i].id
                              ? const Color(0xFF07150D)
                              : Colors.white,
                          child: Text(String.fromCharCode(65 + i)),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Text(
                            '${_options[i].emoji ?? ''} ${_options[i].descricao}'
                                .trim(),
                            style: const TextStyle(fontSize: 16),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
          ],
        ),
      ),
    );
  }

  String? get _imagePath =>
      _question.imagemLocal ??
      _question.imagemUrl ??
      widget.categoria.imagemLocal ??
      widget.categoria.imagemUrl;

  Widget _buildImage(String path) {
    final provider = path.startsWith('http')
        ? NetworkImage(path) as ImageProvider
        : FileImage(File(path));
    return Image(
      image: provider,
      height: 180,
      width: double.infinity,
      fit: BoxFit.cover,
      errorBuilder: (_, _, _) => const SizedBox.shrink(),
    );
  }
}
