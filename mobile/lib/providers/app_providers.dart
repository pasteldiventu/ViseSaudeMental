import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../core/network/api_client.dart';
import '../data/local/app_database.dart';
import '../data/repositories/auth_repository.dart';
import '../data/repositories/cadastros_repository.dart';
import '../data/repositories/painel_repository.dart';
import '../data/repositories/quiz_repository.dart';
import '../data/repositories/staff_repository.dart';
import '../data/repositories/sync_repository.dart';

final databaseProvider = Provider<AppDatabase>((ref) {
  final database = AppDatabase();
  ref.onDispose(database.close);
  return database;
});

final secureStorageProvider = Provider<FlutterSecureStorage>(
  (ref) => const FlutterSecureStorage(
    webOptions: WebOptions(
      dbName: 'vise_sma_secure',
      publicKey: 'vise_sma_web',
    ),
  ),
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

final staffRepositoryProvider = Provider<StaffRepository>(
  (ref) => StaffRepository(
    ref.watch(apiClientProvider),
    ref.watch(secureStorageProvider),
  ),
);

final cadastrosRepositoryProvider = Provider<CadastrosRepository>(
  (ref) => CadastrosRepository(ref.watch(apiClientProvider)),
);

/// Menu de cadastros do usuário da equipe logado (varia conforme o perfil).
final cadastrosMenuProvider = FutureProvider.autoDispose<MenuCadastros>(
  (ref) => ref.watch(cadastrosRepositoryProvider).menu(),
);

final painelRepositoryProvider = Provider<PainelRepository>(
  (ref) => PainelRepository(ref.watch(apiClientProvider)),
);

final opcoesRelatorioProvider = FutureProvider.autoDispose<OpcoesRelatorio>(
  (ref) => ref.watch(painelRepositoryProvider).opcoes(),
);

/// Tipos de planilha que o perfil logado pode importar (vazio = sem importação).
final tiposImportacaoProvider = FutureProvider.autoDispose<List<TipoImportacao>>(
  (ref) => ref.watch(painelRepositoryProvider).tiposImportacao(),
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
    ref.watch(staffRepositoryProvider),
    ref.watch(quizRepositoryProvider),
    ref.watch(syncRepositoryProvider),
    ref.watch(databaseProvider),
  );
  return controller;
});

class AppController extends ChangeNotifier {
  AppController(
    this.auth,
    this.staffRepo,
    this.quiz,
    this.sync,
    this.database,
  ) {
    _connectivitySubscription = Connectivity().onConnectivityChanged.listen((
      results,
    ) {
      if (!results.contains(ConnectivityResult.none) &&
          !modoStaff &&
          aplicacaoId != null) {
        unawaited(sync.sincronizar(aplicacaoId!).catchError((_) {}));
      }
    });
  }

  final AuthRepository auth;
  final StaffRepository staffRepo;
  final QuizRepository quiz;
  final SyncRepository sync;
  final AppDatabase database;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySubscription;

  bool inicializando = true;
  bool carregando = false;
  bool autenticado = false;
  bool modoStaff = false;
  StaffUser? staffUser;
  bool termoAceito = false;
  bool avatarIntroduzido = false;
  int? aplicacaoId;
  String? codigoSalaPendente;
  String? erro;

  Future<void> inicializar() async {
    final mode = await auth.storage.read(key: ApiClient.sessionModeKey);
    if (mode == 'staff') {
      staffUser = await staffRepo.sessaoAtual();
      modoStaff = staffUser != null;
      autenticado = modoStaff;
    } else {
      autenticado = await auth.temSessaoOuCache();
      if (autenticado) {
        await _carregarTermoAceito();
        if (aplicacaoId != null) {
          await _carregarAvatarIntro();
        }
      }
    }
    if (kIsWeb) {
      final sala = Uri.base.queryParameters['sala'];
      if (sala != null && sala.trim().isNotEmpty) {
        codigoSalaPendente = sala.trim().toUpperCase();
      }
    }
    inicializando = false;
    notifyListeners();
  }

  Future<bool> entrar(String cpf, String nascimento) async {
    carregando = true;
    erro = null;
    notifyListeners();
    try {
      await staffRepo.logout();
      final result = await auth.login(cpf, nascimento);
      await auth.storage.write(key: ApiClient.sessionModeKey, value: 'aluno');
      if (!result.offline) {
        await quiz.atualizarCache();
      }
      final apps = await database.listarAplicacoes();
      autenticado = true;
      modoStaff = false;
      staffUser = null;
      aplicacaoId = null;
      avatarIntroduzido = false;
      await _carregarTermoAceito();
      if (apps.isEmpty) {
        erro = 'Nenhum questionário disponível.';
      }
      final codigo = codigoSalaPendente;
      if (codigo != null && codigo.isNotEmpty) {
        await entrarComCodigo(codigo);
      }
      return true;
    } on DioException catch (error, stack) {
      debugPrint('Falha no login: $error\n$stack');
      erro = error.response?.statusCode == 422
          ? 'CPF ou data de nascimento não conferem.'
          : 'Não foi possível entrar. Verifique os dados e a conexão.';
      return false;
    } catch (error, stack) {
      debugPrint('Falha no login: $error\n$stack');
      erro = 'Não foi possível entrar. Verifique os dados e a conexão.';
      return false;
    } finally {
      carregando = false;
      notifyListeners();
    }
  }

  Future<bool> entrarStaff(String email, String password) async {
    carregando = true;
    erro = null;
    notifyListeners();
    try {
      await auth.logout();
      staffUser = await staffRepo.login(email, password);
      autenticado = true;
      modoStaff = true;
      termoAceito = true;
      avatarIntroduzido = false;
      aplicacaoId = null;
      return true;
    } on DioException catch (error, stack) {
      debugPrint('Falha login staff: $error\n$stack');
      final status = error.response?.statusCode;
      erro = status == 422
          ? 'E-mail ou senha inválidos.'
          : status == 403
              ? 'Usuário sem perfil de equipe ativo.'
              : 'Não foi possível entrar. Verifique a conexão.';
      return false;
    } catch (error, stack) {
      debugPrint('Falha login staff: $error\n$stack');
      erro = 'Não foi possível entrar.';
      return false;
    } finally {
      carregando = false;
      notifyListeners();
    }
  }

  Future<bool> entrarComCodigo(String codigo) async {
    final limpo = codigo.replaceAll(RegExp(r'[^A-Za-z0-9]'), '').toUpperCase();
    if (limpo.length < 4) {
      erro = 'Informe o código da sala.';
      notifyListeners();
      return false;
    }
    carregando = true;
    erro = null;
    notifyListeners();
    try {
      final response = await auth.api.dio.post<Map<String, dynamic>>(
        '/aplicacoes/entrar-com-codigo',
        data: {'codigo': limpo},
      );
      final id = response.data?['aplicacao_id'] as int?;
      if (id == null) {
        erro = 'Sala inválida.';
        return false;
      }
      await quiz.atualizarCache();
      codigoSalaPendente = null;
      await abrirAplicacao(id);
      return true;
    } on DioException catch (error) {
      erro = error.response?.statusCode == 404
          ? 'Sala não encontrada ou você não faz parte do público.'
          : 'Não foi possível entrar na sala.';
      return false;
    } catch (_) {
      erro = 'Não foi possível entrar na sala.';
      return false;
    } finally {
      carregando = false;
      notifyListeners();
    }
  }

  Future<void> sair() async {
    if (modoStaff) {
      await staffRepo.logout();
    } else {
      await auth.logout();
    }
    await auth.storage.delete(key: ApiClient.sessionModeKey);
    autenticado = false;
    modoStaff = false;
    staffUser = null;
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

  Future<void> abrirAplicacao(int id) async {
    aplicacaoId = id;
    await _carregarAvatarIntro();
    notifyListeners();
    unawaited(sync.sincronizar(id).catchError((_) {}));
  }

  void voltarParaLista() {
    aplicacaoId = null;
    avatarIntroduzido = false;
    notifyListeners();
  }

  Future<void> concluirAvatarIntro() async {
    final alunoId = await auth.alunoId;
    final appId = aplicacaoId;
    if (alunoId != null && appId != null) {
      await auth.storage.write(
        key: 'avatar_intro_${alunoId}_$appId',
        value: 'true',
      );
    }
    avatarIntroduzido = true;
    notifyListeners();
  }

  Future<void> _carregarTermoAceito() async {
    final alunoId = await auth.alunoId;
    termoAceito =
        alunoId != null &&
        await auth.storage.read(key: 'termo_aceito_$alunoId') == 'true';
  }

  Future<void> _carregarAvatarIntro() async {
    final alunoId = await auth.alunoId;
    final appId = aplicacaoId;
    if (alunoId == null || appId == null) {
      avatarIntroduzido = false;
      return;
    }
    avatarIntroduzido =
        await auth.storage.read(key: 'avatar_intro_${alunoId}_$appId') ==
        'true';
  }

  @override
  void dispose() {
    _connectivitySubscription?.cancel();
    super.dispose();
  }
}
