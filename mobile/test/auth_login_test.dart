import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:drift/drift.dart' hide isNull, isNotNull;
import 'package:drift/native.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:vise_sma_app/core/network/api_client.dart';
import 'package:vise_sma_app/data/local/app_database.dart';
import 'package:vise_sma_app/data/repositories/auth_repository.dart';

class _Api implements HttpClientAdapter {
  _Api(this.responder);

  final ResponseBody Function(RequestOptions options) responder;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async => responder(options);

  @override
  void close({bool force = false}) {}
}

ResponseBody _json(Object body, int status) => ResponseBody.fromString(
  jsonEncode(body),
  status,
  headers: {
    Headers.contentTypeHeader: [Headers.jsonContentType],
  },
);

ResponseBody _semConexao(RequestOptions options) => throw DioException.connectionError(
  requestOptions: options,
  reason: 'offline',
);

Future<(AuthRepository, AppDatabase, FlutterSecureStorage)> _montar(
  ResponseBody Function(RequestOptions) responder, {
  Map<String, String> storage = const {},
}) async {
  FlutterSecureStorage.setMockInitialValues(Map.of(storage));
  const secure = FlutterSecureStorage();
  final db = AppDatabase.conectar(NativeDatabase.memory());
  addTearDown(db.close);
  await db.salvarInstrumento(
    aplicacao: CachedAplicacaoCompanion.insert(id: const Value(1), titulo: 'Bem-estar'),
    categorias: const [],
    perguntas: const [],
    opcoes: const [],
  );
  final api = ApiClient(secure)..dio.httpClientAdapter = _Api(responder);
  return (AuthRepository(api, secure, db), db, secure);
}

void main() {
  driftRuntimeOptions.dontWarnAboutMultipleDatabases = true;

  test('credenciais inválidas não entram em modo offline com o cache', () async {
    final (auth, _, _) = await _montar(
      (_) => _json({'message': 'Credenciais inválidas.'}, 422),
    );
    expect(
      auth.login('12345678901', '2010-01-01'),
      throwsA(isA<DioException>().having((e) => e.response?.statusCode, 'status', 422)),
    );
  });

  test('sem conexão e sem sessão anterior o login falha', () async {
    final (auth, _, _) = await _montar(_semConexao);
    expect(auth.login('12345678901', '2010-01-01'), throwsA(isA<DioException>()));
  });

  test('sem conexão e com token guardado continua offline', () async {
    final (auth, _, _) = await _montar(
      _semConexao,
      storage: {ApiClient.tokenKey: 'abc', AuthRepository.alunoIdKey: '7'},
    );
    expect((await auth.login('12345678901', '2010-01-01')).offline, isTrue);
  });

  test('outro aluno no aparelho descarta o cache do anterior', () async {
    final (auth, db, secure) = await _montar(
      (_) => _json({
        'token': 'novo',
        'aluno': {'id': 9, 'nome': 'Ana'},
      }, 200),
      storage: {AuthRepository.alunoIdKey: '7'},
    );
    final result = await auth.login('12345678901', '2010-01-01');
    expect(result.offline, isFalse);
    expect(await db.listarAplicacoes(), isEmpty);
    expect(await secure.read(key: ApiClient.tokenKey), 'novo');
    expect(await secure.read(key: AuthRepository.alunoIdKey), '9');
  });
}
