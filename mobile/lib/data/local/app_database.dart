import 'package:drift/drift.dart';
import 'package:drift_flutter/drift_flutter.dart';

import 'tables.dart';

part 'app_database.g.dart';

@DriftDatabase(
  tables: [
    CachedAplicacao,
    CachedCategoria,
    CachedPergunta,
    CachedOpcao,
    OutboxResposta,
    LocalProgress,
  ],
)
class AppDatabase extends _$AppDatabase {
  AppDatabase()
    : super(
        driftDatabase(
          name: 'vise_sma',
          web: DriftWebOptions(
            sqlite3Wasm: Uri.parse('sqlite3.wasm'),
            // Regenerar: dart compile js -O2 -o web/drift_worker.js tool/drift_worker.dart
            driftWorker: Uri.parse('drift_worker.js'),
          ),
        ),
      );

  @override
  int get schemaVersion => 1;

  Future<List<CachedAplicacaoData>> listarAplicacoes() =>
      (select(cachedAplicacao)..orderBy([(t) => OrderingTerm.asc(t.id)])).get();

  Stream<List<CachedAplicacaoData>> observarAplicacoes() => (select(
    cachedAplicacao,
  )..orderBy([(t) => OrderingTerm.asc(t.id)])).watch();

  Stream<List<CachedCategoriaData>> observarCategorias(int aplicacaoId) =>
      (select(cachedCategoria)
            ..where((t) => t.aplicacaoId.equals(aplicacaoId))
            ..orderBy([(t) => OrderingTerm.asc(t.ordem)]))
          .watch();

  Future<List<CachedPerguntaData>> listarPerguntas(int categoriaId) =>
      (select(cachedPergunta)
            ..where((t) => t.categoriaId.equals(categoriaId))
            ..orderBy([(t) => OrderingTerm.asc(t.ordem)]))
          .get();

  Future<List<CachedOpcaoData>> listarOpcoes(int perguntaId) =>
      (select(cachedOpcao)
            ..where((t) => t.perguntaId.equals(perguntaId))
            ..orderBy([(t) => OrderingTerm.asc(t.ordem)]))
          .get();

  Stream<List<LocalProgressData>> observarProgresso(int aplicacaoId) => (select(
    localProgress,
  )..where((t) => t.aplicacaoId.equals(aplicacaoId))).watch();

  Future<({int total, int respondidas})> contagemProgresso(
    int aplicacaoId,
  ) async {
    final categorias =
        await (select(cachedCategoria)
              ..where((t) => t.aplicacaoId.equals(aplicacaoId)))
            .get();
    var total = 0;
    for (final categoria in categorias) {
      final perguntas =
          await (select(cachedPergunta)
                ..where((t) => t.categoriaId.equals(categoria.id)))
              .get();
      total += perguntas.length;
    }
    final respondidas =
        await (select(localProgress)
              ..where((t) => t.aplicacaoId.equals(aplicacaoId)))
            .get();
    return (total: total, respondidas: respondidas.length);
  }

  Stream<List<OutboxRespostaData>> observarOutbox(int aplicacaoId) => (select(
    outboxResposta,
  )..where((t) => t.aplicacaoId.equals(aplicacaoId))).watch();

  Future<void> salvarInstrumento({
    required CachedAplicacaoCompanion aplicacao,
    required List<CachedCategoriaCompanion> categorias,
    required List<CachedPerguntaCompanion> perguntas,
    required List<CachedOpcaoCompanion> opcoes,
  }) => transaction(() async {
    final appId = aplicacao.id.value;
    final categoriaIds = categorias.map((e) => e.id.value).toList();
    if (categoriaIds.isNotEmpty) {
      await (delete(cachedOpcao)..where(
            (o) => o.perguntaId.isInQuery(
              selectOnly(cachedPergunta)
                ..addColumns([cachedPergunta.id])
                ..where(cachedPergunta.categoriaId.isIn(categoriaIds)),
            ),
          ))
          .go();
      await (delete(
        cachedPergunta,
      )..where((p) => p.categoriaId.isIn(categoriaIds))).go();
    }
    await (delete(
      cachedCategoria,
    )..where((c) => c.aplicacaoId.equals(appId))).go();
    await into(cachedAplicacao).insertOnConflictUpdate(aplicacao);
    await batch((batch) {
      batch.insertAll(cachedCategoria, categorias);
      batch.insertAll(cachedPergunta, perguntas);
      batch.insertAll(cachedOpcao, opcoes);
    });
  });

  Future<void> salvarResposta({
    required OutboxRespostaCompanion outbox,
    required LocalProgressCompanion progresso,
  }) => transaction(() async {
    await (delete(outboxResposta)..where(
          (t) =>
              t.aplicacaoId.equals(outbox.aplicacaoId.value) &
              t.perguntaId.equals(outbox.perguntaId.value) &
              t.status.isNotValue('sincronizada'),
        ))
        .go();
    await into(outboxResposta).insert(outbox);
    await into(localProgress).insertOnConflictUpdate(progresso);
  });

  Future<List<OutboxRespostaData>> respostasPendentes(int aplicacaoId) =>
      (select(outboxResposta)..where(
            (t) =>
                t.aplicacaoId.equals(aplicacaoId) &
                t.status.isNotValue('sincronizada'),
          ))
          .get();

  Future<void> atualizarStatus(List<String> ids, String status) async {
    if (ids.isEmpty) return;
    await (update(outboxResposta)..where((t) => t.clientUuid.isIn(ids))).write(
      OutboxRespostaCompanion(status: Value(status)),
    );
  }

  Future<void> concluirLocalmente(int aplicacaoId) =>
      (update(cachedAplicacao)..where((t) => t.id.equals(aplicacaoId))).write(
        const CachedAplicacaoCompanion(concluidaLocalmente: Value(true)),
      );

  Future<void> marcarSincronizada(int aplicacaoId) =>
      (update(cachedAplicacao)..where((t) => t.id.equals(aplicacaoId))).write(
        const CachedAplicacaoCompanion(sincronizadaServidor: Value(true)),
      );

  Future<void> limparRespostas(int aplicacaoId) => transaction(() async {
    await (delete(
      outboxResposta,
    )..where((t) => t.aplicacaoId.equals(aplicacaoId))).go();
    await (delete(
      localProgress,
    )..where((t) => t.aplicacaoId.equals(aplicacaoId))).go();
    await (update(
      cachedAplicacao,
    )..where((t) => t.id.equals(aplicacaoId))).write(
      const CachedAplicacaoCompanion(
        concluidaLocalmente: Value(false),
        sincronizadaServidor: Value(false),
      ),
    );
  });
}
