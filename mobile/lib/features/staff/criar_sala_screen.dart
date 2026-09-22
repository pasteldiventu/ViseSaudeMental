import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/theme/app_theme.dart';
import '../../data/repositories/staff_repository.dart';
import '../../providers/app_providers.dart';

class CriarSalaScreen extends ConsumerStatefulWidget {
  const CriarSalaScreen({super.key});

  @override
  ConsumerState<CriarSalaScreen> createState() => _CriarSalaScreenState();
}

class _CriarSalaScreenState extends ConsumerState<CriarSalaScreen> {
  bool _loading = true;
  bool _salvando = false;
  String? _erro;
  List<QuestionarioStaff> _questionarios = const [];
  List<TurmaStaff> _turmas = const [];
  int? _escolaId;
  int? _questionarioId;
  int? _turmaId;
  String _alvo = 'turma';

  @override
  void initState() {
    super.initState();
    Future.microtask(_carregar);
  }

  Future<void> _carregar() async {
    final user = ref.read(appControllerProvider).staffUser;
    final escolaId = user?.escolas.isNotEmpty == true
        ? user!.escolas.first.id
        : null;
    try {
      final repo = ref.read(staffRepositoryProvider);
      final qs = await repo.listarQuestionarios(escolaId: escolaId);
      final turmas = await repo.listarTurmas(escolaId: escolaId);
      if (!mounted) return;
      setState(() {
        _escolaId = escolaId ?? (qs.isNotEmpty ? qs.first.escolaId : null);
        _questionarios = qs;
        _turmas = turmas;
        _questionarioId = qs.isNotEmpty ? qs.first.id : null;
        _turmaId = turmas.isNotEmpty ? turmas.first.id : null;
        if (turmas.isEmpty) _alvo = 'escola';
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _erro = 'Não foi possível carregar questionários/turmas.';
        _loading = false;
      });
    }
  }

  Future<void> _criar() async {
    final escolaId = _escolaId;
    final questionarioId = _questionarioId;
    if (escolaId == null || questionarioId == null) return;
    if (_alvo == 'turma' && _turmaId == null) {
      setState(() => _erro = 'Selecione uma turma.');
      return;
    }
    setState(() {
      _salvando = true;
      _erro = null;
    });
    try {
      final sala = await ref.read(staffRepositoryProvider).criarSala(
            questionarioId: questionarioId,
            escolaId: escolaId,
            alvoTipo: _alvo,
            turmaId: _alvo == 'turma' ? _turmaId : null,
          );
      if (mounted) Navigator.of(context).pop(sala);
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _erro = 'Não foi possível criar a sala.';
        _salvando = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Nova sala')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
              children: [
                Text(
                  'Liberar questionário',
                  style: GoogleFonts.figtree(
                    fontSize: 24,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'A sala gera um código e um link para os alunos entrarem.',
                  style: GoogleFonts.figtree(color: AppColors.muted),
                ),
                const SizedBox(height: 22),
                DropdownButtonFormField<int>(
                  initialValue: _questionarioId,
                  decoration: const InputDecoration(labelText: 'Questionário'),
                  items: _questionarios
                      .map(
                        (q) => DropdownMenuItem(
                          value: q.id,
                          child: Text('${q.nome} (v${q.versao})'),
                        ),
                      )
                      .toList(),
                  onChanged: (v) => setState(() => _questionarioId = v),
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue: _alvo,
                  decoration: const InputDecoration(labelText: 'Público'),
                  items: [
                    if (_turmas.isNotEmpty)
                      const DropdownMenuItem(
                        value: 'turma',
                        child: Text('Uma turma'),
                      ),
                    const DropdownMenuItem(
                      value: 'escola',
                      child: Text('Toda a escola'),
                    ),
                  ],
                  onChanged: (v) => setState(() => _alvo = v ?? 'turma'),
                ),
                if (_alvo == 'turma') ...[
                  const SizedBox(height: 14),
                  DropdownButtonFormField<int>(
                    initialValue: _turmaId,
                    decoration: const InputDecoration(labelText: 'Turma'),
                    items: _turmas
                        .map(
                          (t) => DropdownMenuItem(
                            value: t.id,
                            child: Text(
                              [
                                t.nome,
                                if (t.serie != null) t.serie!,
                                t.turno,
                              ].join(' · '),
                            ),
                          ),
                        )
                        .toList(),
                    onChanged: (v) => setState(() => _turmaId = v),
                  ),
                ],
                if (_erro != null) ...[
                  const SizedBox(height: 16),
                  Text(
                    _erro!,
                    style: GoogleFonts.figtree(color: const Color(0xFFFFB4B4)),
                  ),
                ],
                const SizedBox(height: 24),
                SizedBox(
                  height: 52,
                  child: FilledButton(
                    onPressed: _salvando || _questionarios.isEmpty ? null : _criar,
                    child: _salvando
                        ? const SizedBox.square(
                            dimension: 22,
                            child: CircularProgressIndicator(strokeWidth: 2.4),
                          )
                        : const Text('Criar e gerar link'),
                  ),
                ),
              ],
            ),
    );
  }
}
