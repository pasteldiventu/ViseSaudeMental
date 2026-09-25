import 'package:dio/dio.dart';

import '../../core/network/api_client.dart';

/// Mensagem amigável a partir de um erro da API (`{"detail": "..."}`).
String mensagemDeErro(Object error, [String padrao = 'Não foi possível concluir.']) {
  if (error is DioException) {
    final data = error.response?.data;
    if (data is Map && data['detail'] is String) return data['detail'] as String;
    if (error.response == null) return 'Sem conexão com o servidor.';
  }
  return padrao;
}

class AcaoMeta {
  AcaoMeta({required this.nome, required this.label, this.confirmar});

  factory AcaoMeta.fromJson(Map<String, dynamic> json) => AcaoMeta(
    nome: json['nome'].toString(),
    label: json['label'].toString(),
    confirmar: json['confirmar']?.toString(),
  );

  final String nome;
  final String label;
  final String? confirmar;
}

class CadastroMeta {
  CadastroMeta({
    required this.key,
    required this.singular,
    required this.plural,
    required this.grupo,
    required this.podeCriar,
    required this.busca,
    required this.buscaLabel,
    required this.colunas,
    required this.acoes,
  });

  factory CadastroMeta.fromJson(Map<String, dynamic> json) => CadastroMeta(
    key: json['key'].toString(),
    singular: json['singular'].toString(),
    plural: json['plural'].toString(),
    grupo: json['grupo']?.toString() ?? '',
    podeCriar: json['pode_criar'] == true,
    busca: json['busca'] == true,
    buscaLabel: json['busca_label']?.toString() ?? '',
    colunas: _strings(json['colunas']),
    acoes: _maps(json['acoes']).map(AcaoMeta.fromJson).toList(),
  );

  final String key;
  final String singular;
  final String plural;
  final String grupo;
  final bool podeCriar;
  final bool busca;
  final String buscaLabel;
  final List<String> colunas;
  final List<AcaoMeta> acoes;
}

class MenuCadastros {
  MenuCadastros({required this.grupos, required this.cadastros});

  factory MenuCadastros.fromJson(Map<String, dynamic> json) {
    final cadastros = {
      for (final item in _maps(json['data']))
        item['key'].toString(): CadastroMeta.fromJson(item),
    };
    return MenuCadastros(
      grupos: _maps(json['menu'])
          .map(
            (g) => (
              grupo: g['grupo'].toString(),
              itens: _strings(g['itens'])
                  .where(cadastros.containsKey)
                  .map((k) => cadastros[k]!)
                  .toList(),
            ),
          )
          .toList(),
      cadastros: cadastros,
    );
  }

  final List<({String grupo, List<CadastroMeta> itens})> grupos;
  final Map<String, CadastroMeta> cadastros;
}

class RegistroResumo {
  RegistroResumo({required this.id, required this.titulo, required this.colunas});

  factory RegistroResumo.fromJson(Map<String, dynamic> json) => RegistroResumo(
    id: _int(json['id']),
    titulo: json['titulo']?.toString() ?? '',
    colunas: _strings(json['colunas']),
  );

  final int id;
  final String titulo;
  final List<String> colunas;
}

class FiltroAtivo {
  FiltroAtivo({required this.campo, required this.label, required this.valor, required this.titulo});

  factory FiltroAtivo.fromJson(Map<String, dynamic> json) => FiltroAtivo(
    campo: json['campo'].toString(),
    label: json['label'].toString(),
    valor: _int(json['valor']),
    titulo: json['titulo']?.toString() ?? '',
  );

  final String campo;
  final String label;
  final int valor;
  final String titulo;
}

class PaginaRegistros {
  PaginaRegistros({
    required this.registros,
    required this.total,
    required this.page,
    required this.pages,
    required this.filtros,
  });

  factory PaginaRegistros.fromJson(Map<String, dynamic> json) => PaginaRegistros(
    registros: _maps(json['data']).map(RegistroResumo.fromJson).toList(),
    total: _int(json['total']),
    page: _int(json['page'], 1),
    pages: _int(json['pages'], 1),
    filtros: _maps(json['filtros']).map(FiltroAtivo.fromJson).toList(),
  );

  final List<RegistroResumo> registros;
  final int total;
  final int page;
  final int pages;
  final List<FiltroAtivo> filtros;
}

class CampoDetalhe {
  CampoDetalhe({
    required this.name,
    required this.label,
    required this.tipo,
    required this.valor,
    this.linkKey,
    this.linkId,
    this.pares,
  });

  factory CampoDetalhe.fromJson(Map<String, dynamic> json) {
    final link = json['link'];
    final pares = json['pares'];
    return CampoDetalhe(
      name: json['name'].toString(),
      label: json['label'].toString(),
      tipo: json['tipo']?.toString() ?? 'text',
      valor: json['valor']?.toString() ?? '—',
      linkKey: link is Map ? link['key']?.toString() : null,
      linkId: link is Map ? _int(link['id']) : null,
      pares: pares is Map
          ? pares.map((k, v) => MapEntry(k.toString(), v.toString()))
          : null,
    );
  }

  final String name;
  final String label;
  final String tipo;
  final String valor;
  final String? linkKey;
  final int? linkId;
  final Map<String, String>? pares;
}

class Relacionado {
  Relacionado({
    required this.key,
    required this.field,
    required this.label,
    required this.count,
    required this.canAdd,
  });

  factory Relacionado.fromJson(Map<String, dynamic> json) => Relacionado(
    key: json['key'].toString(),
    field: json['field'].toString(),
    label: json['label'].toString(),
    count: _int(json['count']),
    canAdd: json['can_add'] == true,
  );

  final String key;
  final String field;
  final String label;
  final int count;
  final bool canAdd;
}

class RegistroDetalhe {
  RegistroDetalhe({
    required this.id,
    required this.key,
    required this.singular,
    required this.titulo,
    required this.podeEditar,
    required this.campos,
    required this.relacionados,
    required this.acoes,
  });

  factory RegistroDetalhe.fromJson(Map<String, dynamic> json) => RegistroDetalhe(
    id: _int(json['id']),
    key: json['key'].toString(),
    singular: json['singular']?.toString() ?? '',
    titulo: json['titulo']?.toString() ?? '',
    podeEditar: json['pode_editar'] == true,
    campos: _maps(json['campos']).map(CampoDetalhe.fromJson).toList(),
    relacionados: _maps(json['relacionados']).map(Relacionado.fromJson).toList(),
    acoes: _maps(json['acoes']).map(AcaoMeta.fromJson).toList(),
  );

  final int id;
  final String key;
  final String singular;
  final String titulo;
  final bool podeEditar;
  final List<CampoDetalhe> campos;
  final List<Relacionado> relacionados;
  final List<AcaoMeta> acoes;
}

class OpcaoCampo {
  OpcaoCampo({required this.valor, required this.label});

  final String valor;
  final String label;
}

class CampoFormulario {
  CampoFormulario({
    required this.name,
    required this.label,
    required this.tipo,
    required this.obrigatorio,
    this.max,
    this.ajuda,
    this.opcoes,
    this.valor,
  });

  factory CampoFormulario.fromJson(Map<String, dynamic> json) {
    final opcoes = json['opcoes'];
    return CampoFormulario(
      name: json['name'].toString(),
      label: json['label'].toString(),
      tipo: json['tipo']?.toString() ?? 'text',
      obrigatorio: json['obrigatorio'] == true,
      max: json['max'] == null ? null : _int(json['max']),
      ajuda: json['ajuda']?.toString(),
      opcoes: opcoes is Iterable
          ? _maps(opcoes)
                .map((o) => OpcaoCampo(valor: o['valor'].toString(), label: o['label'].toString()))
                .toList()
          : null,
      valor: json['valor'],
    );
  }

  final String name;
  final String label;
  final String tipo;
  final bool obrigatorio;
  final int? max;
  final String? ajuda;
  final List<OpcaoCampo>? opcoes;
  final dynamic valor;
}

class Formulario {
  Formulario({required this.titulo, required this.campos});

  factory Formulario.fromJson(Map<String, dynamic> json) => Formulario(
    titulo: json['titulo']?.toString() ?? '',
    campos: _maps(json['campos']).map(CampoFormulario.fromJson).toList(),
  );

  final String titulo;
  final List<CampoFormulario> campos;
}

/// Cadastros da equipe (mesmas regras e escopo do painel web).
class CadastrosRepository {
  CadastrosRepository(this.api);

  final ApiClient api;

  static const _base = '/staff/cadastros';

  Future<MenuCadastros> menu() async {
    final response = await api.dio.get<Map<String, dynamic>>(_base);
    return MenuCadastros.fromJson(response.data ?? const {});
  }

  Future<PaginaRegistros> listar(
    String key, {
    String? busca,
    int page = 1,
    Map<String, String> filtros = const {},
  }) async {
    final response = await api.dio.get<Map<String, dynamic>>(
      '$_base/$key',
      queryParameters: {
        ...filtros,
        if (busca != null && busca.trim().isNotEmpty) 'q': busca.trim(),
        'page': page,
      },
    );
    return PaginaRegistros.fromJson(response.data ?? const {});
  }

  Future<RegistroDetalhe> detalhe(String key, int id) async {
    final response = await api.dio.get<Map<String, dynamic>>('$_base/$key/$id');
    return RegistroDetalhe.fromJson(response.data ?? const {});
  }

  Future<Formulario> formulario(
    String key, {
    int? id,
    Map<String, String> preencher = const {},
  }) async {
    final response = await api.dio.get<Map<String, dynamic>>(
      '$_base/$key/formulario',
      queryParameters: {...preencher, 'id': ?id},
    );
    return Formulario.fromJson(response.data ?? const {});
  }

  /// Cria (sem [id]) ou atualiza. Retorna o id gravado.
  Future<({int id, String mensagem})> salvar(
    String key,
    Map<String, dynamic> dados, {
    int? id,
  }) async {
    final response = await api.dio.post<Map<String, dynamic>>(
      id == null ? '$_base/$key' : '$_base/$key/$id',
      data: dados,
    );
    final data = response.data ?? const {};
    return (id: _int(data['id']), mensagem: data['mensagem']?.toString() ?? 'Salvo.');
  }

  Future<String> excluir(String key, int id) async {
    final response = await api.dio.post<Map<String, dynamic>>('$_base/$key/$id/excluir');
    return response.data?['mensagem']?.toString() ?? 'Excluído.';
  }

  Future<String> executarAcao(String key, String acao, List<int> ids) async {
    final response = await api.dio.post<Map<String, dynamic>>(
      '$_base/$key/acoes/$acao',
      data: {'ids': ids},
    );
    return response.data?['mensagem']?.toString() ?? 'Concluído.';
  }
}

int _int(dynamic value, [int fallback = 0]) =>
    value is int ? value : int.tryParse(value?.toString() ?? '') ?? fallback;

List<String> _strings(dynamic value) =>
    value is Iterable ? value.map((e) => e?.toString() ?? '').toList() : const [];

List<Map<String, dynamic>> _maps(dynamic value) => value is Iterable
    ? value.whereType<Map>().map(Map<String, dynamic>.from).toList()
    : const [];
