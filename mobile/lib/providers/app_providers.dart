import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../core/network/api_client.dart';
import '../data/local/app_database.dart';
import '../data/repositories/auth_repository.dart';
import '../data/repositories/quiz_repository.dart';
import '../data/repositories/sync_repository.dart';

final databaseProvider = Provider<AppDatabase>((ref) {
  final database = AppDatabase();
  ref.onDispose(database.close);
  return database;
});

final secureStorageProvider = Provider<FlutterSecureStorage>(
  (ref) => const FlutterSecureStorage(),
);

final apiClientProvider = Provider<ApiClient>(
  (ref) => ApiClient(ref.watch(secureStorageProvider)),
);

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) => AuthRepository(
    ref.watch(apiClientProvider),
    ref.watch(secureStorageProvider),
    ref.watch(databaseProvider),
  ),
);

final quizRepositoryProvider = Provider<QuizRepository>(
  (ref) =>
      QuizRepository(ref.watch(apiClientProvider), ref.watch(databaseProvider)),
);

final syncRepositoryProvider = Provider<SyncRepository>((ref) {
  final repository = SyncRepository(
    ref.watch(apiClientProvider),
    ref.watch(databaseProvider),
    Connectivity(),
  );
  ref.onDispose(repository.dispose);
  return repository;
});

final appControllerProvider = ChangeNotifierProvider<AppController>((ref) {
  final controller = AppController(
    ref.watch(authRepositoryProvider),
    ref.watch(quizRepositoryProvider),
    ref.watch(syncRepositoryProvider),
    ref.watch(databaseProvider),
  );
  ref.onDispose(controller.dispose);
  return controller;
});

class AppController extends ChangeNotifier {
  AppController(this.auth, this.quiz, this.sync, this.database) {
    _connectivitySubscription = Connectivity().onConnectivityChanged.listen((
      results,
    ) {
      if (!results.contains(ConnectivityResult.none) && aplicacaoId != null) {
        unawaited(sync.sincronizar(aplicacaoId!).catchError((_) {}));
      }
    });
  }

  final AuthRepository auth;
  final QuizRepository quiz;
  final SyncRepository sync;
  final AppDatabase database;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySubscription;

  bool carregando = true;
  bool autenticado = false;
  bool termoAceito = false;
  bool avatarIntroduzido = false;
  int? aplicacaoId;
  String? erro;

  Future<void> inicializar() async {
    autenticado = await auth.temSessaoOuCache();
    if (autenticado) {
      await _selecionarAplicacao();
      await _carregarTermoAceito();
    }
    carregando = false;
    notifyListeners();
  }

  Future<bool> entrar(String cpf, String nascimento) async {
    carregando = true;
    erro = null;
    notifyListeners();
    try {
      final result = await auth.login(cpf, nascimento);
      if (!result.offline) {
        await quiz.atualizarCache();
      }
      await _selecionarAplicacao();
      autenticado = aplicacaoId != null;
      if (autenticado) await _carregarTermoAceito();
      avatarIntroduzido = false;
      if (!autenticado) erro = 'Nenhum questionário disponível.';
      return autenticado;
    } catch (_) {
      erro = 'Não foi possível entrar. Verifique os dados e a conexão.';
      return false;
    } finally {
      carregando = false;
      notifyListeners();
    }
  }

  Future<void> sair() async {
    await auth.logout();
    autenticado = false;
    termoAceito = false;
    avatarIntroduzido = false;
    aplicacaoId = null;
    notifyListeners();
  }

  Future<void> registrarTermoAceito() async {
    final alunoId = await auth.alunoId;
    if (alunoId != null) {
      await auth.storage.write(key: 'termo_aceito_$alunoId', value: 'true');
    }
    termoAceito = true;
    notifyListeners();
  }

  void concluirAvatarIntro() {
    avatarIntroduzido = true;
    notifyListeners();
  }

  Future<void> _carregarTermoAceito() async {
    final alunoId = await auth.alunoId;
    termoAceito =
        alunoId != null &&
        await auth.storage.read(key: 'termo_aceito_$alunoId') == 'true';
  }

  Future<void> _selecionarAplicacao() async {
    final aplicacoes = await database.listarAplicacoes();
    aplicacaoId = aplicacoes.isEmpty ? null : aplicacoes.first.id;
  }

  @override
  void dispose() {
    _connectivitySubscription?.cancel();
    super.dispose();
  }
}
