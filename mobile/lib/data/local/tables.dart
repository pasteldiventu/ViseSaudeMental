import 'package:drift/drift.dart';

class CachedAplicacao extends Table {
  IntColumn get id => integer()();
  TextColumn get titulo => text()();
  BoolColumn get concluidaLocalmente =>
      boolean().withDefault(const Constant(false))();
  BoolColumn get sincronizadaServidor =>
      boolean().withDefault(const Constant(false))();

  @override
  Set<Column> get primaryKey => {id};
}

class CachedCategoria extends Table {
  IntColumn get id => integer()();
  IntColumn get aplicacaoId => integer()();
  TextColumn get titulo => text()();
  TextColumn get mensagemAvatar => text().nullable()();
  TextColumn get imagemUrl => text().nullable()();
  TextColumn get imagemLocal => text().nullable()();
  IntColumn get ordem => integer().withDefault(const Constant(0))();

  @override
  Set<Column> get primaryKey => {id};
}

class CachedPergunta extends Table {
  IntColumn get id => integer()();
  IntColumn get categoriaId => integer()();
  TextColumn get texto => text()();
  TextColumn get tipo => text().withDefault(const Constant('objetiva'))();
  BoolColumn get obrigatoria => boolean().withDefault(const Constant(true))();
  TextColumn get imagemUrl => text().nullable()();
  TextColumn get imagemLocal => text().nullable()();
  IntColumn get ordem => integer().withDefault(const Constant(0))();

  @override
  Set<Column> get primaryKey => {id};
}

class CachedOpcao extends Table {
  IntColumn get id => integer()();
  IntColumn get perguntaId => integer()();
  TextColumn get descricao => text()();
  TextColumn get emoji => text().nullable()();
  IntColumn get ordem => integer().withDefault(const Constant(0))();

  @override
  Set<Column> get primaryKey => {id};
}

class OutboxResposta extends Table {
  TextColumn get clientUuid => text()();
  IntColumn get aplicacaoId => integer()();
  IntColumn get perguntaId => integer()();
  TextColumn get status => text().withDefault(const Constant('pendente'))();
  TextColumn get payload => text()();
  DateTimeColumn get criadoEm => dateTime()();

  @override
  Set<Column> get primaryKey => {clientUuid};
}

class LocalProgress extends Table {
  IntColumn get aplicacaoId => integer()();
  IntColumn get perguntaId => integer()();
  IntColumn get categoriaId => integer()();
  TextColumn get respostaJson => text()();
  DateTimeColumn get respondidoEm => dateTime()();

  @override
  Set<Column> get primaryKey => {aplicacaoId, perguntaId};
}
