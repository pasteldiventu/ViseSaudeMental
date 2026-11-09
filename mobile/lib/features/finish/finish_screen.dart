import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/theme/app_theme.dart';
import '../../data/repositories/sync_repository.dart';
import '../../providers/app_providers.dart';
import '../common/avatar_bubble.dart';
import '../common/tactile_button.dart';
import 'retorno_aluno_view.dart';

class FinishScreen extends ConsumerStatefulWidget {
  const FinishScreen({
    super.key,
    required this.aplicacaoId,
    this.revisao = false,
  });

  final int aplicacaoId;

  /// Aberta pela lista de questionários, depois de concluído.
  final bool revisao;

  @override
  ConsumerState<FinishScreen> createState() => _FinishScreenState();
}

class _FinishScreenState extends ConsumerState<FinishScreen> {
  SyncState _syncState = SyncState.local;
  StreamSubscription<SyncState>? _subscription;

  @override
  void initState() {
    super.initState();
    final database = ref.read(databaseProvider);
    final sync = ref.read(syncRepositoryProvider);
    _subscription = sync.state.listen((value) {
      if (!mounted) return;
      if (value == SyncState.synced && _syncState != SyncState.synced) {
        ref.invalidate(retornoProvider(widget.aplicacaoId));
      }
      setState(() => _syncState = value);
    });
    database.concluirLocalmente(widget.aplicacaoId).then((_) {
      sync.sincronizar(widget.aplicacaoId).catchError((_) {});
    });
  }

  @override
  void dispose() {
    _subscription?.cancel();
    super.dispose();
  }

  Future<void> _sync() async {
    try {
      await ref.read(syncRepositoryProvider).sincronizar(widget.aplicacaoId);
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Não foi possível enviar agora. As respostas continuam salvas.',
            ),
          ),
        );
      }
    }
  }

  void _voltarAosQuestionarios() {
    Navigator.of(context).popUntil((route) => route.isFirst);
    ref.read(appControllerProvider).voltarParaLista();
  }

  void _reverRespostas() {
    Navigator.of(context).pop();
    ref.read(appControllerProvider).abrirAplicacao(widget.aplicacaoId);
  }

  @override
  Widget build(BuildContext context) {
    final enviado = _syncState == SyncState.synced;
    final mensagem = enviado
        ? ref.watch(retornoProvider(widget.aplicacaoId)).valueOrNull?.mensagem
        : null;
    return Scaffold(
      appBar: AppBar(title: Text(widget.revisao ? 'Seu retorno' : 'Pronto')),
      body: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(24, 16, 24, 16),
                children: [
                  Text(
                    'Obrigado por\nparticipar.',
                    style: GoogleFonts.figtree(
                      fontSize: 36,
                      fontWeight: FontWeight.w800,
                      height: 1.05,
                      letterSpacing: -1.1,
                    ),
                  ),
                  const SizedBox(height: 18),
                  AvatarBubble(
                    message:
                        mensagem ??
                        'Suas respostas foram guardadas com cuidado. '
                            'Elas ajudam a equipe a cuidar melhor da sua escola.',
                  ),
                  const SizedBox(height: 22),
                  _StatusEnvio(icone: _icon, texto: _label),
                  const SizedBox(height: 22),
                  if (enviado)
                    RetornoAlunoView(aplicacaoId: widget.aplicacaoId)
                  else if (_syncState != SyncState.syncing)
                    Text(
                      'Seu retorno aparece aqui assim que as respostas '
                      'forem enviadas.',
                      style: GoogleFonts.figtree(
                        color: AppColors.muted,
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 0, 24, 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (!enviado)
                    TactileButton(
                      enabled: _syncState != SyncState.syncing,
                      onPressed: _syncState == SyncState.syncing ? null : _sync,
                      child: _syncState == SyncState.syncing
                          ? const SizedBox.square(
                              dimension: 22,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.4,
                                color: Color(0xFF062016),
                              ),
                            )
                          : const Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(Icons.cloud_upload_rounded),
                                SizedBox(width: 10),
                                Text('Enviar ao servidor'),
                              ],
                            ),
                    ),
                  const SizedBox(height: 6),
                  TextButton(
                    onPressed: _voltarAosQuestionarios,
                    child: const Text('Voltar aos questionários'),
                  ),
                  TextButton(
                    onPressed: widget.revisao
                        ? _reverRespostas
                        : () => Navigator.of(context).pop(),
                    child: Text(
                      widget.revisao
                          ? 'Rever minhas respostas'
                          : 'Voltar às etapas',
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  String get _label => switch (_syncState) {
    SyncState.syncing => 'Enviando respostas…',
    SyncState.synced => 'Respostas sincronizadas',
    SyncState.error => 'Salvo neste dispositivo — envio pendente',
    SyncState.local => 'Salvo neste dispositivo',
  };

  IconData get _icon => switch (_syncState) {
    SyncState.syncing => Icons.sync_rounded,
    SyncState.synced => Icons.cloud_done_rounded,
    SyncState.error => Icons.cloud_off_rounded,
    SyncState.local => Icons.bookmark_added_rounded,
  };
}

class _StatusEnvio extends StatelessWidget {
  const _StatusEnvio({required this.icone, required this.texto});

  final IconData icone;
  final String texto;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: AppColors.pastelMint,
        borderRadius: BorderRadius.circular(99),
      ),
      child: Row(
        children: [
          Icon(icone, color: AppColors.ink, size: 22),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              texto,
              style: GoogleFonts.figtree(
                color: AppColors.ink,
                fontSize: 14,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
