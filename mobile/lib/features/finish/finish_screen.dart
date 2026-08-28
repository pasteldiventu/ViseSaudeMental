import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/theme/app_theme.dart';
import '../../data/repositories/sync_repository.dart';
import '../../providers/app_providers.dart';
import '../common/avatar_bubble.dart';

class FinishScreen extends ConsumerStatefulWidget {
  const FinishScreen({super.key, required this.aplicacaoId});

  final int aplicacaoId;

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
      if (mounted) setState(() => _syncState = value);
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('CONCLUÍDO')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const AvatarBubble(
                message:
                    'Muito obrigado por responder! Suas respostas foram guardadas com segurança.',
              ),
              const SizedBox(height: 38),
              Icon(_icon, color: _color, size: 38),
              const SizedBox(height: 10),
              Text(
                _label,
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: _color,
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 28),
              FilledButton.icon(
                onPressed: _syncState == SyncState.syncing ? null : _sync,
                icon: _syncState == SyncState.syncing
                    ? const SizedBox.square(
                        dimension: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.cloud_upload_outlined),
                label: const Text('Enviar ao servidor'),
              ),
              const SizedBox(height: 12),
              TextButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Voltar às categorias'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String get _label => switch (_syncState) {
    SyncState.syncing => 'Enviando respostas…',
    SyncState.synced => 'Respostas sincronizadas',
    SyncState.error => 'Salvo no aparelho — envio pendente',
    SyncState.local => 'Salvo no aparelho',
  };

  IconData get _icon => switch (_syncState) {
    SyncState.syncing => Icons.sync,
    SyncState.synced => Icons.cloud_done_outlined,
    SyncState.error => Icons.cloud_off_outlined,
    SyncState.local => Icons.phone_android,
  };

  Color get _color => switch (_syncState) {
    SyncState.error => Colors.orangeAccent,
    SyncState.local => AppColors.muted,
    _ => AppColors.mint,
  };
}
