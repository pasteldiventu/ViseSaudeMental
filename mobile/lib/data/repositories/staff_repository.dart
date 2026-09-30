import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../core/network/api_client.dart';

class StaffUser {
  StaffUser({
    required this.id,
    required this.name,
    required this.email,
    required this.isSuperuser,
    required this.escolas,
  });

  factory StaffUser.fromJson(Map<String, dynamic> json) {
    final escolasRaw = json['escolas'];
    final escolas = <StaffEscola>[];
    if (escolasRaw is Iterable) {
      for (final item in escolasRaw) {
        if (item is Map) {
          escolas.add(StaffEscola.fromJson(Map<String, dynamic>.from(item)));
        }
      }
    }
    return StaffUser(
      id: json['id'] as int,
      name: json['name']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      isSuperuser: json['is_superuser'] == true,
      escolas: escolas,
    );
  }

  final int id;
  final String name;
  final String email;
  final bool isSuperuser;
  final List<StaffEscola> escolas;
}

class StaffEscola {
  StaffEscola({
    required this.id,
    required this.nome,
    required this.role,
    required this.roleLabel,
  });

  factory StaffEscola.fromJson(Map<String, dynamic> json) {
    return StaffEscola(
      id: json['id'] as int,
      nome: json['nome']?.toString() ?? '',
      role: json['role']?.toString() ?? '',
      roleLabel: json['role_label']?.toString() ?? '',
    );
  }

  final int id;
  final String nome;
  final String role;
  final String roleLabel;
}

class SalaResumo {
  SalaResumo({
    required this.id,
    required this.codigo,
    required this.link,
    required this.status,
    required this.alvoTipo,
    required this.questionarioNome,
    required this.escolaNome,
    this.turmaNome,
    required this.respondentes,
    required this.concluidos,
  });

  factory SalaResumo.fromJson(Map<String, dynamic> json) {
    return SalaResumo(
      id: json['id'] as int,
      codigo: json['codigo']?.toString() ?? '',
      link: json['link']?.toString() ?? '',
      status: json['status']?.toString() ?? '',
      alvoTipo: json['alvo_tipo']?.toString() ?? '',
      questionarioNome: json['questionario_nome']?.toString() ?? '',
      escolaNome: json['escola_nome']?.toString() ?? '',
      turmaNome: json['turma_nome']?.toString(),
      respondentes: json['respondentes'] as int? ?? 0,
      concluidos: json['concluidos'] as int? ?? 0,
    );
  }

  final int id;
  final String codigo;
  final String link;
  final String status;
  final String alvoTipo;
  final String questionarioNome;
  final String escolaNome;
  final String? turmaNome;
  final int respondentes;
  final int concluidos;
}

class QuestionarioStaff {
  QuestionarioStaff({
    required this.id,
    required this.nome,
    required this.versao,
    required this.escolaId,
  });

  factory QuestionarioStaff.fromJson(Map<String, dynamic> json) {
    return QuestionarioStaff(
      id: json['id'] as int,
      nome: json['nome']?.toString() ?? '',
      versao: json['versao'] as int? ?? 1,
      escolaId: json['escola_id'] as int,
    );
  }

  final int id;
  final String nome;
  final int versao;
  final int escolaId;
}

class TurmaStaff {
  TurmaStaff({
    required this.id,
    required this.nome,
    required this.turno,
    required this.escolaId,
    this.serie,
  });

  factory TurmaStaff.fromJson(Map<String, dynamic> json) {
    return TurmaStaff(
      id: json['id'] as int,
      nome: json['nome']?.toString() ?? '',
      turno: json['turno']?.toString() ?? '',
      escolaId: json['escola_id'] as int,
      serie: json['serie']?.toString(),
    );
  }

  final int id;
  final String nome;
  final String turno;
  final int escolaId;
  final String? serie;
}

class StaffRepository {
  StaffRepository(this.api, this.storage);

  final ApiClient api;
  final FlutterSecureStorage storage;

  Future<StaffUser> login(String email, String password) async {
    final response = await api.dio.post<Map<String, dynamic>>(
      '/staff/login',
      data: {'email': email.trim(), 'password': password},
    );
    final data = response.data ?? const {};
    final token = data['token']?.toString();
    if (token == null || token.isEmpty) {
      throw const FormatException('Token staff ausente.');
    }
    await storage.write(key: ApiClient.staffTokenKey, value: token);
    await storage.write(key: ApiClient.sessionModeKey, value: 'staff');
    final user = data['user'];
    if (user is! Map) {
      throw const FormatException('Usuário staff ausente.');
    }
    return StaffUser.fromJson(Map<String, dynamic>.from(user));
  }

  Future<StaffUser?> sessaoAtual() async {
    final token = await storage.read(key: ApiClient.staffTokenKey);
    final mode = await storage.read(key: ApiClient.sessionModeKey);
    if (token == null || mode != 'staff') return null;
    try {
      final response = await api.dio.get<Map<String, dynamic>>('/staff/me');
      final data = response.data;
      if (data == null) return null;
      return StaffUser.fromJson(data);
    } on DioException {
      return null;
    }
  }

  Future<void> logout() async {
    await storage.delete(key: ApiClient.staffTokenKey);
    final mode = await storage.read(key: ApiClient.sessionModeKey);
    if (mode == 'staff') {
      await storage.delete(key: ApiClient.sessionModeKey);
    }
  }

  Future<List<SalaResumo>> listarSalas({int? escolaId}) async {
    final response = await api.dio.get<Map<String, dynamic>>(
      '/staff/salas',
      queryParameters: {'escola_id': ?escolaId},
    );
    return ApiClient.listFrom(response.data).map(SalaResumo.fromJson).toList();
  }

  Future<List<QuestionarioStaff>> listarQuestionarios({int? escolaId}) async {
    final response = await api.dio.get<dynamic>(
      '/staff/questionarios',
      queryParameters: {'escola_id': ?escolaId},
    );
    final list = response.data;
    if (list is! Iterable) return const [];
    return list
        .whereType<Map>()
        .map((e) => QuestionarioStaff.fromJson(Map<String, dynamic>.from(e)))
        .toList();
  }

  Future<List<TurmaStaff>> listarTurmas({int? escolaId}) async {
    final response = await api.dio.get<dynamic>(
      '/staff/turmas',
      queryParameters: {'escola_id': ?escolaId},
    );
    final list = response.data;
    if (list is! Iterable) return const [];
    return list
        .whereType<Map>()
        .map((e) => TurmaStaff.fromJson(Map<String, dynamic>.from(e)))
        .toList();
  }

  Future<SalaResumo> criarSala({
    required int questionarioId,
    required int escolaId,
    required String alvoTipo,
    int? turmaId,
  }) async {
    final response = await api.dio.post<Map<String, dynamic>>(
      '/staff/salas',
      data: {
        'questionario_id': questionarioId,
        'escola_id': escolaId,
        'alvo_tipo': alvoTipo,
        'turma_id': ?turmaId,
      },
    );
    return SalaResumo.fromJson(response.data ?? const {});
  }

  Future<SalaResumo> encerrarSala(int salaId) async {
    final response = await api.dio.post<Map<String, dynamic>>(
      '/staff/salas/$salaId/encerrar',
    );
    return SalaResumo.fromJson(response.data ?? const {});
  }
}
