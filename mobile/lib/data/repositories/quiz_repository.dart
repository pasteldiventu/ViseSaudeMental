import 'dart:convert';
import 'dart:io';

import 'package:drift/drift.dart';
import 'package:flutter/foundation.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:uuid/uuid.dart';

import '../../core/network/api_client.dart';
import '../local/app_database.dart';

class QuizRepository {
  QuizRepository(this.api, this.database);

  final ApiClient api;
  final AppDatabase database;
  final _uuid = const Uuid();

  Future<void> atualizarCache() async {
    final response = await api.dio.get<dynamic>('/aplicacoes');
    final aplicacoes = ApiClient.listFrom(response.data, 'aplicacoes');
    await database.removerAplicacoesAusentes({
      for (final aplicacao in aplicacoes) _int(aplicacao['id']),
    });
    for (final aplicacao in aplicacoes) {
      final appId = _int(aplicacao['id']);
      final categoriasResponse = await api.dio.get<dynamic>(
        '/aplicacoes/$appId/categorias',
      );
      final categoriasJson = ApiClient.listFrom(
        categoriasResponse.data,
        'categorias',
      );
      final categorias = <CachedCategoriaCompanion>[];
      final perguntas = <CachedPerguntaCompanion>[];
      final opcoes = <CachedOpcaoCompanion>[];

      for (
        var categoryIndex = 0;
        categoryIndex < categoriasJson.length;
        categoryIndex++
      ) {
        final categoria = categoriasJson[categoryIndex];
        final categoryId = _int(categoria['id']);
        final categoryImage = _nullable(
          categoria['imagem'] ??
              categoria['imagem_apoio'] ??
              categoria['imagem_url'],
        );
        categorias.add(
          CachedCategoriaCompanion.insert(
            id: categoryId,
            aplicacaoId: appId,
            titulo: _text(
              categoria['titulo'] ?? categoria['nome'],
              'Categoria',
            ),
            mensagemAvatar: Value(_nullable(categoria['mensagem_avatar'])),
            imagemUrl: Value(categoryImage),
            imagemLocal: Value(await _downloadImage(categoryImage)),
            ordem: Value(_int(categoria['ordem'], categoryIndex)),
          ),
        );

        final questionsResponse = await api.dio.get<dynamic>(
          '/aplicacoes/$appId/categorias/$categoryId/perguntas',
        );
        final questionsJson = ApiClient.listFrom(
          questionsResponse.data,
          'perguntas',
        );
        for (
          var questionIndex = 0;
          questionIndex < questionsJson.length;
          questionIndex++
        ) {
          final question = questionsJson[questionIndex];
          final questionId = _int(question['id']);
          final questionImage = _nullable(
            question['imagem'] ?? question['imagem_url'],
          );
          final optionsJson = ApiClient.listFrom(
            question['opcoes'] ?? question['opcoes_resposta'],
          );
          perguntas.add(
            CachedPerguntaCompanion.insert(
              id: Value(questionId),
              categoriaId: categoryId,
              texto: _text(question['texto'] ?? question['pergunta'], ''),
              tipo: Value(
                _text(
                  question['tipo'],
                  optionsJson.isEmpty ? 'texto' : 'objetiva',
                ),
              ),
              obrigatoria: Value(question['obrigatoria'] != false),
              imagemUrl: Value(questionImage),
              imagemLocal: Value(await _downloadImage(questionImage)),
              ordem: Value(_int(question['ordem'], questionIndex)),
            ),
          );
          for (
            var optionIndex = 0;
            optionIndex < optionsJson.length;
            optionIndex++
          ) {
            final option = optionsJson[optionIndex];
            opcoes.add(
              CachedOpcaoCompanion.insert(
                id: Value(_int(option['id'])),
                perguntaId: questionId,
                descricao: _text(
                  option['descricao'] ?? option['texto'],
                  'Opção',
                ),
                emoji: Value(_nullable(option['emoji'])),
                ordem: Value(_int(option['ordem'], optionIndex)),
              ),
            );
          }
        }
      }

      final antigo = (await database.listarAplicacoes())
          .where((item) => item.id == appId)
          .firstOrNull;
      final concluidoApi = aplicacao['concluido'] == true;
      await database.salvarInstrumento(
        aplicacao: CachedAplicacaoCompanion.insert(
          id: Value(appId),
          titulo: _text(
            aplicacao['titulo'] ??
                aplicacao['nome'] ??
                aplicacao['questionario']?['nome'],
            'Questionário',
          ),
          concluidaLocalmente: Value(
            concluidoApi || (antigo?.concluidaLocalmente ?? false),
          ),
          sincronizadaServidor: Value(
            concluidoApi || (antigo?.sincronizadaServidor ?? false),
          ),
        ),
        categorias: categorias,
        perguntas: perguntas,
        opcoes: opcoes,
      );
    }
  }

  Future<void> responder({
    required int aplicacaoId,
    required int categoriaId,
    required int perguntaId,
    int? opcaoId,
    String? texto,
  }) async {
    final now = DateTime.now();
    final clientUuid = _uuid.v4();
    final payload = {
      'client_uuid': clientUuid,
      'pergunta_id': perguntaId,
      'opcao_id': ?opcaoId,
      'texto': ?texto,
      'responded_at': now.toUtc().toIso8601String(),
    };
    await database.salvarResposta(
      outbox: OutboxRespostaCompanion.insert(
        clientUuid: clientUuid,
        aplicacaoId: aplicacaoId,
        perguntaId: perguntaId,
        payload: jsonEncode(payload),
        criadoEm: now,
      ),
      progresso: LocalProgressCompanion.insert(
        aplicacaoId: aplicacaoId,
        perguntaId: perguntaId,
        categoriaId: categoriaId,
        respostaJson: jsonEncode(payload),
        respondidoEm: now,
      ),
    );
  }

  Future<String?> _downloadImage(String? url) async {
    if (kIsWeb || url == null || !url.startsWith('http')) return null;
    try {
      final directory = await getApplicationSupportDirectory();
      final imageDirectory = Directory(p.join(directory.path, 'instrumentos'));
      await imageDirectory.create(recursive: true);
      final extension = p.extension(Uri.parse(url).path);
      final file = File(
        p.join(
          imageDirectory.path,
          '${_uuid.v5(Namespace.url.value, url)}${extension.isEmpty ? '.img' : extension}',
        ),
      );
      if (!await file.exists()) {
        await api.dio.download(url, file.path);
      }
      return file.path;
    } catch (_) {
      return null;
    }
  }

  static int _int(dynamic value, [int fallback = 0]) =>
      int.tryParse(value?.toString() ?? '') ?? fallback;

  static String _text(dynamic value, String fallback) {
    final result = value?.toString().trim();
    return result == null || result.isEmpty ? fallback : result;
  }

  static String? _nullable(dynamic value) {
    final result = value?.toString().trim();
    return result == null || result.isEmpty ? null : result;
  }
}
