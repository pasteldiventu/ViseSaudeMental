import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:drift/drift.dart' show driftRuntimeOptions;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:vise_sma_app/core/network/api_client.dart';
import 'package:vise_sma_app/core/theme/app_theme.dart';
import 'package:vise_sma_app/data/repositories/painel_repository.dart';
import 'package:vise_sma_app/data/repositories/staff_repository.dart';
import 'package:vise_sma_app/features/staff/painel/painel_screen.dart';
import 'package:vise_sma_app/features/staff/painel/relatorios_screen.dart';
import 'package:vise_sma_app/features/staff/staff_shell.dart';
import 'package:vise_sma_app/providers/app_providers.dart';

/// API falsa com respostas no formato do PainelApiController.
class _FakeApi implements HttpClientAdapter {
  final chamadas = <Uri>[];

  static const _painel = {
    'data': {
      'filtros': {
        'query': {'periodo': '30'},
        'descricao': [
          {'rotulo': 'Período', 'valor': 'Últimos 30 dias'},
        ],
      },
      'resumo': {
        'escolas': 1,
        'turmas': 2,
        'alunos': 40,
        'participantes': 25,
        'participacao': 0.625,
        'concluidos': 30,
        'em_andamento': 3,
        'respostas': 600,
        'aplicacoes_ativas': 1,
        'prioritarios': 4,
        'atencao': 7,
        'ultima_conclusao': '2026-09-29 13:00:00',
      },
      'evolucao': {
        'granularidade': 'dia',
        'pontos': [
          {'rotulo': '28/09', 'valor': 5},
          {'rotulo': '29/09', 'valor': 9},
        ],
      },
      'classificacao': [
        {
          'categoria': 'Ansiedade',
          'questionario': 'Bem-estar',
          'avaliacoes': 30,
          'media': 12.5,
          'faixas': [
            {'rotulo': 'Baixo', 'nivel': 'baixo', 'qtd': 20, 'pct': 0.66},
            {'rotulo': 'Alto', 'nivel': 'alto', 'qtd': 10, 'pct': 0.34},
          ],
        },
      ],
      'turmas': [
        {
          'turma_id': 7,
          'turma': '6º Ano A',
          'escola_id': 1,
          'escola': 'Escola Demo',
          'turno': 'matutino',
          'alunos': 20,
          'participantes': 8,
          'pendentes': 12,
          'participacao': 0.4,
          'prioritarios': 3,
          'atencao': 2,
        },
      ],
      'aplicacoes': [
        {
          'questionario': 'Bem-estar',
          'escola': 'Escola Demo',
          'alvo': '6º Ano A',
          'codigo': 'ABC123',
          'alvo_total': 20,
          'concluidos': 8,
          'em_andamento': 1,
          'progresso': 0.4,
        },
      ],
      'alertas': [
        {
          'aluno': 'Maria Souza',
          'turma': '6º Ano A',
          'escola': 'Escola Demo',
          'questionario': 'Bem-estar',
          'categorias': ['Ansiedade'],
          'data': '2026-09-29 13:00:00',
        },
      ],
    },
  };

  static const _opcoes = {
    'data': {
      'escolas': [
        {'id': 1, 'nome': 'Escola Demo'},
      ],
      'turmas': [
        {'id': 7, 'nome': '6º Ano A', 'escola_id': 1, 'escola': 'Escola Demo', 'turno': 'matutino'},
        {'id': 8, 'nome': '7º Ano B', 'escola_id': 1, 'escola': 'Escola Demo', 'turno': 'vespertino'},
      ],
      'series': [
        {'id': 1, 'nome': '6º ano'},
      ],
      'turnos': [
        {'valor': 'matutino', 'rotulo': 'Matutino'},
      ],
      'sexos': [],
      'questionarios': [
        {'id': 1, 'nome': 'Bem-estar'},
      ],
      'periodos': [
        {'valor': '30', 'rotulo': 'Últimos 30 dias'},
        {'valor': 'tudo', 'rotulo': 'Todo o período'},
        {'valor': 'personalizado', 'rotulo': 'Personalizado'},
      ],
      'abas': [
        {'chave': 'resumo', 'rotulo': 'Resumo', 'descricao': 'Indicadores gerais', 'padrao': true, 'obrigatoria': true},
        {'chave': 'turmas', 'rotulo': 'Por turma', 'descricao': 'Participação por turma', 'padrao': true, 'obrigatoria': false},
        {'chave': 'respostas', 'rotulo': 'Respostas detalhadas', 'descricao': 'Uma linha por resposta', 'padrao': false, 'obrigatoria': false},
      ],
    },
  };

  static const _tipos = {
    'data': [
      {
        'tipo': 'alunos',
        'label': 'Alunos',
        'descricao': 'Cadastra ou atualiza alunos pela matrícula.',
        'colunas': [
          {'chave': 'nome', 'label': 'Nome', 'obrigatoria': true, 'ajuda': 'Nome completo', 'exemplo': 'Ana Lima'},
          {'chave': 'turma', 'label': 'Turma', 'obrigatoria': false},
        ],
      },
    ],
    'limite_linhas': 5000,
  };

  (int, Object) _responder(RequestOptions options) {
    final path = options.uri.path.replaceFirst('/api/v1', '');
    return switch ('${options.method} $path') {
      'GET /staff/salas' => (200, {'data': []}),
      'GET /staff/cadastros' => (200, {'menu': [], 'data': []}),
      'GET /staff/painel' => (200, _painel),
      'GET /staff/relatorios/opcoes' => (200, _opcoes),
      'GET /staff/importacao' => (200, _tipos),
      _ => (404, {'detail': 'Not Found'}),
    };
  }

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    chamadas.add(options.uri);
    final path = options.uri.path.replaceFirst('/api/v1', '');
    if (path == '/staff/relatorios/exportar' || path.endsWith('/modelo')) {
      return ResponseBody.fromBytes(
        [0x50, 0x4B, 0x03, 0x04],
        200,
        headers: {
          Headers.contentTypeHeader: ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
          'content-disposition': ["attachment; filename=\"relatorio.xlsx\"; filename*=UTF-8''relat%C3%B3rio-vise.xlsx"],
        },
      );
    }
    final (status, body) = _responder(options);
    return ResponseBody.fromString(jsonEncode(body), status, headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    });
  }

  @override
  void close({bool force = false}) {}
}

Future<_FakeApi> _montar(WidgetTester tester, Size tamanho) async {
  tester.view.physicalSize = tamanho;
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);

  FlutterSecureStorage.setMockInitialValues({
    ApiClient.staffTokenKey: 'token',
    ApiClient.sessionModeKey: 'staff',
  });
  TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockStreamHandler(
    const EventChannel('dev.fluttercommunity.plus/connectivity_status'),
    MockStreamHandler.inline(onListen: (_, _) {}),
  );

  final api = _FakeApi();
  final container = ProviderContainer(
    overrides: [
      apiClientProvider.overrideWith((ref) {
        final client = ApiClient(ref.watch(secureStorageProvider));
        client.dio.httpClientAdapter = api;
        return client;
      }),
    ],
  );
  addTearDown(container.dispose);
  container.read(appControllerProvider)
    ..modoStaff = true
    ..autenticado = true
    ..staffUser = StaffUser(
      id: 2,
      name: 'Diretora Demo',
      email: 'admin.escola@vise.local',
      isSuperuser: false,
      escolas: [StaffEscola(id: 1, nome: 'Escola Demo', role: 'admin_escola', roleLabel: 'Administrador da Escola')],
    );

  await tester.pumpWidget(
    UncontrolledProviderScope(
      container: container,
      child: MaterialApp(
        theme: AppTheme.dark,
        locale: const Locale('pt', 'BR'),
        supportedLocales: const [Locale('pt', 'BR')],
        localizationsDelegates: const [
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: const StaffShell(),
      ),
    ),
  );
  await tester.pumpAndSettle();
  return api;
}

void main() {
  driftRuntimeOptions.dontWarnAboutMultipleDatabases = true;

  test('nome do arquivo prefere o filename* UTF-8', () {
    expect(nomeDoArquivo("attachment; filename=\"a.xlsx\"; filename*=UTF-8''relat%C3%B3rio.xlsx"), 'relatório.xlsx');
    expect(nomeDoArquivo('attachment; filename="modelo.xlsx"'), 'modelo.xlsx');
    expect(nomeDoArquivo(null), isNull);
  });

  testWidgets('painel mostra indicadores e a turma abre o relatório filtrado', (tester) async {
    final api = await _montar(tester, const Size(1280, 900));

    expect(find.text('Painel de indicadores'), findsOneWidget);
    expect(find.text('Importar planilha'), findsOneWidget);
    await tester.tap(find.text('Painel').first);
    await tester.pumpAndSettle();

    expect(find.text('PAINEL'), findsOneWidget);
    expect(find.text('63%'), findsOneWidget);
    expect(find.text('Ansiedade'), findsWidgets);
    expect(api.chamadas.where((u) => u.path.endsWith('/staff/painel')).first.queryParameters['periodo'], '30');

    final rolagem = find.descendant(of: find.byType(PainelScreen), matching: find.byType(Scrollable)).first;
    await tester.scrollUntilVisible(find.text('Maria Souza'), 300, scrollable: rolagem);
    expect(find.text('Maria Souza'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('6º Ano A').first, -300, scrollable: rolagem);
    await tester.tap(find.text('6º Ano A').first);
    await tester.pumpAndSettle();

    expect(find.text('RELATÓRIOS'), findsOneWidget);
    expect(find.text('6º Ano A'), findsOneWidget);

    await tester.tap(find.text('Respostas detalhadas'));
    await tester.scrollUntilVisible(
      find.text('Baixar relatório (.xlsx)'),
      200,
      scrollable: find.descendant(of: find.byType(RelatoriosScreen), matching: find.byType(Scrollable)).first,
    );
    await tester.tap(find.text('Baixar relatório (.xlsx)'));
    await tester.pump();
    expect(find.text('Gerando…'), findsOneWidget);
    await tester.pump(const Duration(milliseconds: 500));

    final exportar = api.chamadas.lastWhere((u) => u.path.endsWith('/staff/relatorios/exportar'));
    expect(exportar.queryParameters['turma_id'], '7');
    expect(exportar.queryParameters['escola_id'], '1');
    expect(exportar.queryParameters['periodo'], '30');
    expect(exportar.queryParameters['abas']!.split(','), containsAll(['resumo', 'turmas', 'respostas']));
    expect(exportar.queryParameters.containsKey('anonimizar'), isFalse);
  });

  testWidgets('importação lista colunas e baixa o modelo', (tester) async {
    final api = await _montar(tester, const Size(1280, 900));
    await tester.tap(find.text('Importar planilha'));
    await tester.pumpAndSettle();

    expect(find.text('IMPORTAR PLANILHA'), findsOneWidget);
    expect(find.text('Importar alunos'), findsOneWidget);
    expect(find.text('Nome *'), findsOneWidget);
    expect(tester.widget<FilledButton>(find.widgetWithText(FilledButton, 'Importar')).onPressed, isNull);

    await tester.tap(find.text('Baixar modelo'));
    await tester.pumpAndSettle();
    expect(api.chamadas.map((u) => u.path), contains(endsWith('/staff/importacao/alunos/modelo')));
  });
}
