import 'dart:convert';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:drift/drift.dart' show Value, driftRuntimeOptions;
import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:vise_sma_app/core/network/api_client.dart';
import 'package:vise_sma_app/core/theme/app_theme.dart';
import 'package:vise_sma_app/data/local/app_database.dart';
import 'package:vise_sma_app/data/repositories/sync_repository.dart';
import 'package:vise_sma_app/features/finish/finish_screen.dart';
import 'package:vise_sma_app/providers/app_providers.dart';

class _FakeApi implements HttpClientAdapter {
  final chamadas = <String>[];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    final rota =
        '${options.method} ${options.uri.path.replaceFirst('/api/v1', '')}';
    chamadas.add(rota);
    final (status, body) = switch (rota) {
      'POST /aplicacoes/7/finalizar' => (200, {'status': 'concluido'}),
      'GET /aplicacoes/7/retorno' => (
        200,
        {
          'data': {
            'mensagem': 'Obrigado pela coragem de responder com sinceridade.',
            'tom': 'cuidado',
            'categorias': [
              {
                'nome': 'Ansiedade',
                'cor': null,
                'tom': 'cuidado',
                'emoji': '🤝',
                'mensagem': 'Procure um professor para conversar.',
              },
              {
                'nome': 'Sono',
                'cor': null,
                'tom': 'bem',
                'emoji': '🌱',
                'mensagem': 'Esta parte parece estar indo bem.',
              },
            ],
            'ajuda': 'O CVV atende pelo telefone 188.',
          },
        },
      ),
      _ => (404, {'detail': 'Not Found'}),
    };
    return ResponseBody.fromString(
      jsonEncode(body),
      status,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

class _Sync extends SyncRepository {
  _Sync(super.api, super.database, super.connectivity, {required this.online});

  final bool online;

  @override
  Future<bool> estaOnline() async => online;
}

Future<_FakeApi> _montar(WidgetTester tester, {required bool online}) async {
  tester.view.physicalSize = const Size(420, 900);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  FlutterSecureStorage.setMockInitialValues({ApiClient.tokenKey: 'token'});
  TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
      .setMockStreamHandler(
        const EventChannel('dev.fluttercommunity.plus/connectivity_status'),
        MockStreamHandler.inline(onListen: (_, _) {}),
      );

  final database = AppDatabase.conectar(NativeDatabase.memory());
  await database.salvarInstrumento(
    aplicacao: CachedAplicacaoCompanion.insert(
      id: const Value(7),
      titulo: 'Bem-estar',
    ),
    categorias: const [],
    perguntas: const [],
    opcoes: const [],
  );
  final api = _FakeApi();
  final container = ProviderContainer(
    overrides: [
      databaseProvider.overrideWithValue(database),
      apiClientProvider.overrideWith((ref) {
        final client = ApiClient(ref.watch(secureStorageProvider));
        client.dio.httpClientAdapter = api;
        return client;
      }),
      syncRepositoryProvider.overrideWith(
        (ref) => _Sync(
          ref.watch(apiClientProvider),
          database,
          Connectivity(),
          online: online,
        ),
      ),
    ],
  );
  addTearDown(() async {
    container.dispose();
    await database.close();
  });

  await tester.pumpWidget(
    UncontrolledProviderScope(
      container: container,
      child: MaterialApp(
        theme: AppTheme.dark,
        home: const FinishScreen(aplicacaoId: 7),
      ),
    ),
  );
  await tester.pumpAndSettle();
  return api;
}

void main() {
  driftRuntimeOptions.dontWarnAboutMultipleDatabases = true;

  testWidgets('aluno vê um retorno acolhedor ao concluir', (tester) async {
    final api = await _montar(tester, online: true);

    expect(api.chamadas, contains('POST /aplicacoes/7/finalizar'));
    expect(find.text('Respostas sincronizadas'), findsOneWidget);
    expect(
      find.text('Obrigado pela coragem de responder com sinceridade.'),
      findsOneWidget,
    );
    expect(find.text('Seu retorno'), findsOneWidget);
    expect(find.text('Ansiedade'), findsOneWidget);
    expect(find.text('Procure um professor para conversar.'), findsOneWidget);
    expect(find.text('Sono'), findsOneWidget);
    expect(find.text('O CVV atende pelo telefone 188.'), findsOneWidget);
    expect(find.text('Enviar ao servidor'), findsNothing);
  });

  testWidgets('sem internet, avisa que o retorno vem depois do envio', (
    tester,
  ) async {
    final api = await _montar(tester, online: false);

    expect(api.chamadas, isEmpty);
    expect(
      find.text(
        'Seu retorno aparece aqui assim que as respostas forem enviadas.',
      ),
      findsOneWidget,
    );
    expect(find.text('Enviar ao servidor'), findsOneWidget);
  });
}
