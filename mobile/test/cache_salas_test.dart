import 'package:drift/drift.dart' hide isNotNull;
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:vise_sma_app/data/local/app_database.dart';

Future<void> _salvar(AppDatabase db, int appId) => db.salvarInstrumento(
  aplicacao: CachedAplicacaoCompanion.insert(id: Value(appId), titulo: 'Bem-estar'),
  categorias: [
    CachedCategoriaCompanion.insert(id: 10, aplicacaoId: appId, titulo: 'Humor'),
  ],
  perguntas: [
    CachedPerguntaCompanion.insert(id: const Value(100), categoriaId: 10, texto: 'Como você está?'),
  ],
  opcoes: [
    CachedOpcaoCompanion.insert(id: const Value(1000), perguntaId: 100, descricao: 'Bem'),
  ],
);

void main() {
  driftRuntimeOptions.dontWarnAboutMultipleDatabases = true;

  test('duas salas do mesmo questionário convivem no cache', () async {
    final db = AppDatabase.conectar(NativeDatabase.memory());
    addTearDown(db.close);

    await _salvar(db, 1);
    await _salvar(db, 2);
    await _salvar(db, 1);

    expect(await db.observarCategorias(1).first, hasLength(1));
    expect(await db.observarCategorias(2).first, hasLength(1));
    expect(await db.listarPerguntas(10), hasLength(1));
    expect(await db.listarOpcoes(100), hasLength(1));
    expect((await db.contagemProgresso(2)).total, 1);
  });

  test('migração da versão 1 preserva as categorias em cache', () async {
    final db = AppDatabase.conectar(
      NativeDatabase.memory(
        setup: (raw) {
          raw.execute(
            'CREATE TABLE cached_categoria (id INTEGER NOT NULL, aplicacao_id INTEGER NOT NULL, '
            'titulo TEXT NOT NULL, mensagem_avatar TEXT NULL, imagem_url TEXT NULL, imagem_local TEXT NULL, '
            'ordem INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (id))',
          );
          raw.execute("INSERT INTO cached_categoria (id, aplicacao_id, titulo) VALUES (10, 1, 'Humor')");
          raw.execute('PRAGMA user_version = 1');
        },
      ),
    );
    addTearDown(db.close);

    expect((await db.observarCategorias(1).first).single.titulo, 'Humor');
    await db.into(db.cachedCategoria).insert(
      CachedCategoriaCompanion.insert(id: 10, aplicacaoId: 2, titulo: 'Humor'),
    );
    expect(await db.observarCategorias(2).first, hasLength(1));
  });
}
