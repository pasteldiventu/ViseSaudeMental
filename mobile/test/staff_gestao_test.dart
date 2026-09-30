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
import 'package:vise_sma_app/data/repositories/staff_repository.dart';
import 'package:vise_sma_app/features/staff/cadastros/cadastro_form_screen.dart';
import 'package:vise_sma_app/features/staff/staff_shell.dart';
import 'package:vise_sma_app/providers/app_providers.dart';

/// API falsa com respostas no formato do backend PHP.
class _FakeApi implements HttpClientAdapter {
  final chamadas = <String>[];

  static const _campos = [
    {
      'name': 'nome',
      'label': 'Nome',
      'tipo': 'text',
      'obrigatorio': true,
      'max': 255,
      'valor': null,
    },
    {
      'name': 'descricao',
      'label': 'Descrição',
      'tipo': 'textarea',
      'obrigatorio': false,
      'valor': null,
    },
    {
      'name': 'escola_id',
      'label': 'Escola',
      'tipo': 'fk',
      'obrigatorio': true,
      'opcoes': [
        {'valor': '1', 'label': 'Escola Demo Vise · INEP 00000001'},
      ],
      'valor': '1',
    },
    {
      'name': 'status',
      'label': 'Status',
      'tipo': 'select',
      'obrigatorio': true,
      'opcoes': [
        {'valor': 'rascunho', 'label': 'Rascunho'},
        {'valor': 'publicado', 'label': 'Publicado'},
      ],
      'valor': 'rascunho',
    },
    {
      'name': 'versao',
      'label': 'Versão',
      'tipo': 'int',
      'obrigatorio': true,
      'valor': '1',
    },
    {
      'name': 'peso',
      'label': 'Peso',
      'tipo': 'decimal',
      'obrigatorio': false,
      'valor': '1.00',
    },
    {
      'name': 'compartilhado_na_escola',
      'label': 'Compartilhado na escola',
      'tipo': 'bool',
      'obrigatorio': false,
      'valor': false,
    },
    {
      'name': 'cor',
      'label': 'Cor',
      'tipo': 'color',
      'obrigatorio': false,
      'valor': '#14B8A6',
    },
    {
      'name': 'inicio',
      'label': 'Início',
      'tipo': 'datetime',
      'obrigatorio': false,
      'valor': '2026-09-25 10:30',
      'ajuda': 'Opcional.',
    },
    {
      'name': 'nascimento',
      'label': 'Nascimento',
      'tipo': 'date',
      'obrigatorio': false,
      'valor': null,
    },
    {
      'name': 'email',
      'label': 'E-mail',
      'tipo': 'email',
      'obrigatorio': false,
      'valor': null,
    },
    {
      'name': 'password',
      'label': 'Senha',
      'tipo': 'password',
      'obrigatorio': false,
      'valor': null,
    },
  ];

  static Map<String, dynamic> _meta(
    String key,
    String singular,
    String plural,
    String grupo, {
    List<Map<String, dynamic>> acoes = const [],
  }) => {
    'key': key,
    'singular': singular,
    'plural': plural,
    'grupo': grupo,
    'pode_criar': true,
    'busca': true,
    'busca_label': 'nome',
    'colunas': ['Nome', 'Status', 'Versão'],
    'acoes': acoes,
  };

  (int, Object) _responder(RequestOptions options) {
    final path = options.uri.path.replaceFirst('/api/v1', '');
    final chave = '${options.method} $path';
    chamadas.add(chave);
    switch (chave) {
      case 'GET /staff/salas':
        return (200, {'data': []});
      case 'GET /staff/cadastros':
        return (
          200,
          {
            'menu': [
              {
                'grupo': 'Instrumentos',
                'itens': ['questionarios', 'categorias', 'perguntas'],
              },
              {
                'grupo': 'Aplicação e dados',
                'itens': ['aplicacoes', 'resultados'],
              },
            ],
            'data': [
              _meta(
                'questionarios',
                'Questionário',
                'Questionários',
                'Instrumentos',
                acoes: [
                  {
                    'nome': 'publicar',
                    'label': 'Publicar',
                    'confirmar': 'Publicar?',
                  },
                ],
              ),
              _meta('categorias', 'Categoria', 'Categorias', 'Instrumentos'),
              _meta('perguntas', 'Pergunta', 'Perguntas', 'Instrumentos'),
              _meta(
                'aplicacoes',
                'Aplicação',
                'Aplicações',
                'Aplicação e dados',
              ),
              _meta(
                'resultados',
                'Resultado',
                'Resultados',
                'Aplicação e dados',
              ),
            ],
          },
        );
      case 'GET /staff/cadastros/questionarios/formulario':
      case 'GET /staff/cadastros/categorias/formulario':
        return (
          200,
          {
            'key': 'questionarios',
            'titulo': 'Novo(a) questionário',
            'campos': _campos,
          },
        );
      case 'POST /staff/cadastros/questionarios':
        return (
          201,
          {'id': 1, 'titulo': 'Novo', 'mensagem': 'Questionário criado(a).'},
        );
      case 'POST /staff/cadastros/questionarios/1':
        return (
          200,
          {
            'id': 1,
            'titulo': 'Novo',
            'mensagem': 'Questionário atualizado(a).',
          },
        );
      case 'POST /staff/cadastros/questionarios/acoes/publicar':
        return (
          200,
          {'mensagem': '1 questionário(s) publicado(s).', 'ok': true},
        );
      case 'GET /staff/cadastros/questionarios/1':
        return (
          200,
          {
            'id': 1,
            'key': 'questionarios',
            'singular': 'Questionário',
            'titulo': 'Bem-estar emocional - Demo (v1 · rascunho)',
            'pode_editar': true,
            'campos': [
              {
                'name': 'nome',
                'label': 'Nome',
                'tipo': 'text',
                'valor': 'Bem-estar emocional - Demo',
              },
              {
                'name': 'escola_id',
                'label': 'Escola',
                'tipo': 'fk',
                'valor': 'Escola Demo',
                'link': {'key': 'escolas', 'id': 1},
              },
              {
                'name': 'totais',
                'label': 'Totais',
                'tipo': 'json',
                'valor': '',
                'pares': {'Humor': '3', 'Sono': 'sem regra'},
              },
            ],
            'relacionados': [
              {
                'key': 'categorias',
                'field': 'questionario_id',
                'label': 'Categorias',
                'count': 2,
                'can_add': true,
              },
            ],
            'acoes': [
              {
                'nome': 'publicar',
                'label': 'Publicar',
                'confirmar': 'Publicar?',
              },
            ],
          },
        );
    }
    if (options.method == 'GET' && path.startsWith('/staff/cadastros/')) {
      return (
        200,
        {
          'data': [
            {
              'id': 1,
              'titulo': 'Bem-estar emocional - Demo',
              'colunas': ['Bem-estar emocional - Demo', 'Rascunho', '1'],
            },
          ],
          'total': 1,
          'page': 1,
          'pages': 2,
          'filtros': options.uri.queryParameters.containsKey('questionario_id')
              ? [
                  {
                    'campo': 'questionario_id',
                    'label': 'Questionário',
                    'valor': 1,
                    'titulo': 'Bem-estar',
                  },
                ]
              : [],
        },
      );
    }
    return (404, {'detail': 'Not Found'});
  }

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    final (status, body) = _responder(options);
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

Future<_FakeApi> _montar(WidgetTester tester, Size tamanho) async {
  tester.view.physicalSize = tamanho;
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);

  FlutterSecureStorage.setMockInitialValues({
    ApiClient.staffTokenKey: 'token',
    ApiClient.sessionModeKey: 'staff',
  });
  TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
      .setMockStreamHandler(
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
      id: 3,
      name: 'Pesquisadora Demo',
      email: 'pesquisador@vise.local',
      isSuperuser: false,
      escolas: [
        StaffEscola(
          id: 1,
          nome: 'Escola Demo Vise',
          role: 'pesquisador',
          roleLabel: 'Pesquisador',
        ),
      ],
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

final _rolagem = find
    .descendant(
      of: find.byType(CadastroFormScreen),
      matching: find.byType(Scrollable),
    )
    .first;

void main() {
  driftRuntimeOptions.dontWarnAboutMultipleDatabases = true;

  testWidgets('celular: menu em gaveta, lista, detalhe, ação e formulário', (
    tester,
  ) async {
    final api = await _montar(tester, const Size(390, 844));

    expect(find.text('Gestão'), findsOneWidget);
    await tester.tap(find.widgetWithText(ActionChip, 'Questionários'));
    await tester.pumpAndSettle();
    expect(find.widgetWithText(AppBar, 'Questionários'), findsOneWidget);
    expect(find.text('Bem-estar emocional - Demo'), findsOneWidget);
    expect(find.text('Carregar mais'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.menu));
    await tester.pumpAndSettle();
    expect(find.text('Instrumentos'), findsOneWidget);
    await tester.tap(find.text('Categorias'));
    await tester.pumpAndSettle();
    expect(find.widgetWithText(AppBar, 'Categorias'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.menu));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Questionários'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Bem-estar emocional - Demo'));
    await tester.pumpAndSettle();
    expect(find.text('Conteúdo'), findsOneWidget);
    expect(find.textContaining('Humor'), findsOneWidget);

    await tester.tap(find.text('Publicar'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Confirmar'));
    await tester.pumpAndSettle();
    expect(
      api.chamadas,
      contains('POST /staff/cadastros/questionarios/acoes/publicar'),
    );
    await tester.tap(find.text('Categorias').last);
    await tester.pumpAndSettle();
    expect(find.textContaining('Questionário: Bem-estar'), findsOneWidget);
    await tester.tap(find.byType(BackButton));
    await tester.pumpAndSettle();

    await tester.tap(find.byTooltip('Editar'));
    await tester.pumpAndSettle();
    expect(find.text('Compartilhado na escola'), findsOneWidget);
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Nome *'),
      'Ansiedade',
    );
    await tester.tap(find.byType(Switch));
    await tester.scrollUntilVisible(
      find.text('Salvar'),
      200,
      scrollable: _rolagem,
    );
    await tester.tap(find.text('Salvar'));
    await tester.pumpAndSettle();
    expect(api.chamadas, contains('POST /staff/cadastros/questionarios/1'));
    expect(find.text('Conteúdo'), findsOneWidget);
  });

  testWidgets('celular: novo questionário exige nome e abre o detalhe', (
    tester,
  ) async {
    final api = await _montar(tester, const Size(390, 844));
    await tester.tap(find.widgetWithText(ActionChip, 'Questionários'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Novo(a) questionário'));
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.text('Salvar'),
      200,
      scrollable: _rolagem,
    );
    await tester.tap(find.text('Salvar'));
    await tester.pumpAndSettle();
    expect(
      api.chamadas,
      isNot(contains('POST /staff/cadastros/questionarios')),
    );

    await tester.scrollUntilVisible(
      find.widgetWithText(TextFormField, 'Nome *'),
      -200,
      scrollable: _rolagem,
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Nome *'),
      'Sono',
    );
    await tester.scrollUntilVisible(
      find.text('Salvar'),
      200,
      scrollable: _rolagem,
    );
    await tester.tap(find.text('Salvar'));
    await tester.pumpAndSettle();
    expect(api.chamadas, contains('POST /staff/cadastros/questionarios'));
    expect(find.text('Conteúdo'), findsOneWidget);
  });

  testWidgets('tablet: menu lateral fixo', (tester) async {
    await _montar(tester, const Size(1280, 800));
    expect(find.byIcon(Icons.menu), findsNothing);
    expect(find.text('Instrumentos'), findsOneWidget);
    await tester.tap(find.text('Perguntas'));
    await tester.pumpAndSettle();
    expect(find.widgetWithText(AppBar, 'Perguntas'), findsOneWidget);
    await tester.tap(find.text('Salas'));
    await tester.pumpAndSettle();
    expect(find.text('Gestão'), findsOneWidget);
  });
}
