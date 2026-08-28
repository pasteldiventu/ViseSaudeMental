import 'dart:async';
import 'dart:convert';

import 'package:connectivity_plus/connectivity_plus.dart';

import '../../core/network/api_client.dart';
import '../local/app_database.dart';

enum SyncState { local, syncing, synced, error }

class SyncRepository {
  SyncRepository(this.api, this.database, this.connectivity);

  final ApiClient api;
  final AppDatabase database;
  final Connectivity connectivity;
  final _state = StreamController<SyncState>.broadcast();

  Stream<SyncState> get state => _state.stream;

  Future<bool> estaOnline() async {
    final results = await connectivity.checkConnectivity();
    return !results.contains(ConnectivityResult.none);
  }

  Future<void> sincronizar(int aplicacaoId) async {
    if (!await estaOnline()) {
      _state.add(SyncState.local);
      return;
    }
    final pending = await database.respostasPendentes(aplicacaoId);
    final ids = pending.map((item) => item.clientUuid).toList();
    _state.add(SyncState.syncing);
    try {
      if (pending.isNotEmpty) {
        await database.atualizarStatus(ids, 'enviando');
        await api.dio.post<void>(
          '/aplicacoes/$aplicacaoId/respostas/lote',
          data: {
            'respostas': pending
                .map(
                  (item) => Map<String, dynamic>.from(
                    jsonDecode(item.payload) as Map,
                  ),
                )
                .toList(),
          },
        );
        await database.atualizarStatus(ids, 'sincronizada');
      }
      final aplicacao = (await database.listarAplicacoes())
          .where((item) => item.id == aplicacaoId)
          .firstOrNull;
      if (aplicacao?.concluidaLocalmente == true &&
          aplicacao?.sincronizadaServidor == false) {
        await api.dio.post<void>('/aplicacoes/$aplicacaoId/finalizar');
        await database.marcarSincronizada(aplicacaoId);
      }
      _state.add(SyncState.synced);
    } catch (_) {
      await database.atualizarStatus(ids, 'erro');
      _state.add(SyncState.error);
      rethrow;
    }
  }

  void dispose() => _state.close();
}
