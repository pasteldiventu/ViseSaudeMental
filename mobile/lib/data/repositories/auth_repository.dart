import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../core/network/api_client.dart';
import '../local/app_database.dart';

class LoginResult {
  const LoginResult({required this.offline, this.alunoNome});

  final bool offline;
  final String? alunoNome;
}

class AuthRepository {
  AuthRepository(this.api, this.storage, this.database);

  static const alunoIdKey = 'aluno_id';

  final ApiClient api;
  final FlutterSecureStorage storage;
  final AppDatabase database;

  Future<String?> get alunoId => storage.read(key: alunoIdKey);

  Future<LoginResult> login(String cpf, String dataNascimento) async {
    try {
      final response = await api.dio.post<Map<String, dynamic>>(
        '/login',
        data: {
          'cpf': cpf.replaceAll(RegExp(r'\D'), ''),
          'data_nascimento': dataNascimento,
        },
      );
      final data = response.data ?? const {};
      final token = data['token']?.toString();
      if (token == null || token.isEmpty) {
        throw const FormatException('Token ausente na resposta da API.');
      }
      await storage.write(key: ApiClient.tokenKey, value: token);
      final aluno = data['aluno'];
      await _definirAluno(aluno is Map ? aluno['id']?.toString() : null);
      return LoginResult(
        offline: false,
        alunoNome: aluno is Map ? aluno['nome']?.toString() : null,
      );
    } on DioException catch (error) {
      // Offline só sem resposta do servidor e com a sessão anterior (token) ainda guardada.
      final semConexao = error.response == null;
      if (semConexao &&
          await storage.containsKey(key: ApiClient.tokenKey) &&
          (await database.listarAplicacoes()).isNotEmpty) {
        return const LoginResult(offline: true);
      }
      rethrow;
    }
  }

  /// Ao entrar numa sala, o servidor pode trocar para outro cadastro do mesmo
  /// aluno (mesmo CPF e nascimento, em outra escola) e devolver a nova sessão.
  Future<void> trocarSessao(String token, Object? aluno) async {
    await storage.write(key: ApiClient.tokenKey, value: token);
    await _definirAluno(aluno is Map ? aluno['id']?.toString() : null);
  }

  Future<void> _definirAluno(String? novoId) async {
    if (novoId == null) return;
    if (await alunoId != novoId) {
      // Questionários e respostas em cache pertencem ao aluno anterior.
      await database.limparDadosLocais();
    }
    await storage.write(key: alunoIdKey, value: novoId);
  }

  Future<bool> temSessaoOuCache() async {
    final mode = await storage.read(key: ApiClient.sessionModeKey);
    if (mode == 'staff') return false;
    return await storage.containsKey(key: ApiClient.tokenKey) &&
        (await database.listarAplicacoes()).isNotEmpty;
  }

  Future<void> logout() async {
    try {
      await api.dio.post<void>('/logout');
    } catch (_) {
      // A sessão local ainda deve ser encerrada se o dispositivo estiver offline.
    }
    await storage.delete(key: ApiClient.tokenKey);
  }
}
