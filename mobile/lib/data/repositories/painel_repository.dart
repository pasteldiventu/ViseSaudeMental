import 'dart:typed_data';

import 'package:dio/dio.dart';

import '../../core/network/api_client.dart';

class Opcao {
  const Opcao(this.valor, this.rotulo);

  final String valor;
  final String rotulo;
}

class TurmaOpcao {
  const TurmaOpcao({required this.id, required this.nome, required this.escolaId, required this.escola});

  final int id;
  final String nome;
  final int escolaId;
  final String escola;
}

class AbaRelatorio {
  const AbaRelatorio({
    required this.chave,
    required this.rotulo,
    required this.descricao,
    required this.padrao,
    required this.obrigatoria,
  });

  final String chave;
  final String rotulo;
  final String descricao;
  final bool padrao;
  final bool obrigatoria;
}

class OpcoesRelatorio {
  OpcoesRelatorio.fromJson(Map<String, dynamic> json)
    : escolas = _idNome(json['escolas']),
      turmas = _maps(json['turmas'])
          .map(
            (t) => TurmaOpcao(
              id: _int(t['id']),
              nome: '${t['nome'] ?? ''}',
              escolaId: _int(t['escola_id']),
              escola: '${t['escola'] ?? ''}',
            ),
          )
          .toList(),
      series = _idNome(json['series']),
      turnos = _pares(json['turnos']),
      sexos = _pares(json['sexos']),
      questionarios = _idNome(json['questionarios']),
      periodos = _pares(json['periodos']),
      abas = _maps(json['abas'])
          .map(
            (a) => AbaRelatorio(
              chave: '${a['chave']}',
              rotulo: '${a['rotulo'] ?? a['chave']}',
              descricao: '${a['descricao'] ?? ''}',
              padrao: a['padrao'] == true,
              obrigatoria: a['obrigatoria'] == true,
            ),
          )
          .toList();

  final List<Opcao> escolas;
  final List<TurmaOpcao> turmas;
  final List<Opcao> series;
  final List<Opcao> turnos;
  final List<Opcao> sexos;
  final List<Opcao> questionarios;
  final List<Opcao> periodos;
  final List<AbaRelatorio> abas;
}

class ResumoPainel {
  ResumoPainel.fromJson(Map<String, dynamic> j)
    : escolas = _int(j['escolas']),
      turmas = _int(j['turmas']),
      alunos = _int(j['alunos']),
      participantes = _int(j['participantes']),
      participacao = _double(j['participacao']),
      concluidos = _int(j['concluidos']),
      emAndamento = _int(j['em_andamento']),
      respostas = _int(j['respostas']),
      aplicacoesAtivas = _int(j['aplicacoes_ativas']),
      prioritarios = _int(j['prioritarios']),
      atencao = _int(j['atencao']),
      ultimaConclusao = j['ultima_conclusao']?.toString();

  final int escolas;
  final int turmas;
  final int alunos;
  final int participantes;
  final double? participacao;
  final int concluidos;
  final int emAndamento;
  final int respostas;
  final int aplicacoesAtivas;
  final int prioritarios;
  final int atencao;
  final String? ultimaConclusao;
}

class FaixaClassificacao {
  const FaixaClassificacao({required this.rotulo, required this.nivel, required this.qtd, required this.pct});

  final String rotulo;
  final String? nivel;
  final int qtd;
  final double pct;
}

class CategoriaClassificacao {
  CategoriaClassificacao.fromJson(Map<String, dynamic> j)
    : categoria = '${j['categoria'] ?? ''}',
      questionario = '${j['questionario'] ?? ''}',
      avaliacoes = _int(j['avaliacoes']),
      media = _double(j['media']),
      faixas = _maps(j['faixas'])
          .map(
            (f) => FaixaClassificacao(
              rotulo: '${f['rotulo'] ?? ''}',
              nivel: f['nivel']?.toString(),
              qtd: _int(f['qtd']),
              pct: _double(f['pct']) ?? 0,
            ),
          )
          .toList();

  final String categoria;
  final String questionario;
  final int avaliacoes;
  final double? media;
  final List<FaixaClassificacao> faixas;
}

class TurmaPainel {
  TurmaPainel.fromJson(Map<String, dynamic> j)
    : turmaId = j['turma_id'] == null ? null : _int(j['turma_id']),
      turma = '${j['turma'] ?? ''}',
      escolaId = j['escola_id'] == null ? null : _int(j['escola_id']),
      escola = '${j['escola'] ?? ''}',
      turno = j['turno']?.toString(),
      alunos = _int(j['alunos']),
      participantes = _int(j['participantes']),
      pendentes = _int(j['pendentes']),
      participacao = _double(j['participacao']),
      prioritarios = _int(j['prioritarios']),
      atencao = _int(j['atencao']);

  final int? turmaId;
  final String turma;
  final int? escolaId;
  final String escola;
  final String? turno;
  final int alunos;
  final int participantes;
  final int pendentes;
  final double? participacao;
  final int prioritarios;
  final int atencao;
}

class AplicacaoPainel {
  AplicacaoPainel.fromJson(Map<String, dynamic> j)
    : questionario = '${j['questionario'] ?? ''}',
      escola = '${j['escola'] ?? ''}',
      alvo = '${j['alvo'] ?? ''}',
      codigo = j['codigo']?.toString(),
      alvoTotal = _int(j['alvo_total']),
      concluidos = _int(j['concluidos']),
      emAndamento = _int(j['em_andamento']),
      progresso = _double(j['progresso']);

  final String questionario;
  final String escola;
  final String alvo;
  final String? codigo;
  final int alvoTotal;
  final int concluidos;
  final int emAndamento;
  final double? progresso;
}

class AlertaPainel {
  AlertaPainel.fromJson(Map<String, dynamic> j)
    : aluno = '${j['aluno'] ?? ''}',
      turma = j['turma']?.toString(),
      escola = '${j['escola'] ?? ''}',
      questionario = '${j['questionario'] ?? ''}',
      categorias = _strings(j['categorias']),
      data = j['data']?.toString();

  final String aluno;
  final String? turma;
  final String escola;
  final String questionario;
  final List<String> categorias;

  /// UTC, "AAAA-MM-DD HH:MM:SS".
  final String? data;
}

class DadosPainel {
  DadosPainel.fromJson(Map<String, dynamic> j)
    : resumo = ResumoPainel.fromJson(_map(j['resumo'])),
      granularidade = '${_map(j['evolucao'])['granularidade'] ?? 'dia'}',
      pontos = _maps(_map(j['evolucao'])['pontos'])
          .map((p) => (rotulo: '${p['rotulo'] ?? ''}', valor: _int(p['valor'])))
          .toList(),
      classificacao = _maps(j['classificacao']).map(CategoriaClassificacao.fromJson).toList(),
      turmas = _maps(j['turmas']).map(TurmaPainel.fromJson).toList(),
      aplicacoes = _maps(j['aplicacoes']).map(AplicacaoPainel.fromJson).toList(),
      alertas = _maps(j['alertas']).map(AlertaPainel.fromJson).toList(),
      filtros = _maps(_map(j['filtros'])['descricao'])
          .map((f) => (rotulo: '${f['rotulo'] ?? ''}', valor: '${f['valor'] ?? ''}'))
          .toList();

  final ResumoPainel resumo;
  final String granularidade;
  final List<({String rotulo, int valor})> pontos;
  final List<CategoriaClassificacao> classificacao;
  final List<TurmaPainel> turmas;
  final List<AplicacaoPainel> aplicacoes;
  final List<AlertaPainel> alertas;
  final List<({String rotulo, String valor})> filtros;
}

class ColunaImportacao {
  const ColunaImportacao({required this.chave, required this.label, required this.obrigatoria, this.ajuda, this.exemplo});

  final String chave;
  final String label;
  final bool obrigatoria;
  final String? ajuda;
  final String? exemplo;
}

class TipoImportacao {
  TipoImportacao.fromJson(Map<String, dynamic> j)
    : tipo = '${j['tipo']}',
      label = '${j['label'] ?? j['tipo']}',
      descricao = '${j['descricao'] ?? ''}',
      colunas = _maps(j['colunas'])
          .map(
            (c) => ColunaImportacao(
              chave: '${c['chave']}',
              label: '${c['label'] ?? c['chave']}',
              obrigatoria: c['obrigatoria'] == true,
              ajuda: c['ajuda']?.toString(),
              exemplo: c['exemplo']?.toString(),
            ),
          )
          .toList();

  final String tipo;
  final String label;
  final String descricao;
  final List<ColunaImportacao> colunas;
}

class ResultadoImportacao {
  ResultadoImportacao.fromJson(Map<String, dynamic> j)
    : simulacao = j['simulacao'] == true,
      formato = '${j['formato'] ?? ''}',
      total = _int(j['total']),
      criados = _int(j['criados']),
      atualizados = _int(j['atualizados']),
      erros = _int(j['erros']),
      colunasIgnoradas = _strings(j['colunas_ignoradas']),
      linhas = _maps(j['linhas'])
          .map((l) => (linha: _int(l['linha']), status: '${l['status']}', mensagem: '${l['mensagem'] ?? ''}'))
          .toList(),
      linhasTruncadas = j['linhas_truncadas'] == true;

  final bool simulacao;
  final String formato;
  final int total;
  final int criados;
  final int atualizados;
  final int erros;
  final List<String> colunasIgnoradas;
  final List<({int linha, String status, String mensagem})> linhas;
  final bool linhasTruncadas;
}

typedef ArquivoBaixado = ({Uint8List bytes, String nome});

/// Painel de indicadores, relatórios em Excel e importação de planilhas (API da equipe).
class PainelRepository {
  PainelRepository(this.api);

  final ApiClient api;

  static final _download = Options(responseType: ResponseType.bytes, receiveTimeout: const Duration(minutes: 3));

  Future<DadosPainel> painel(Map<String, String> filtros) async {
    final response = await api.dio.get<Map<String, dynamic>>('/staff/painel', queryParameters: filtros);
    return DadosPainel.fromJson(_map(response.data?['data']));
  }

  Future<OpcoesRelatorio> opcoes() async {
    final response = await api.dio.get<Map<String, dynamic>>('/staff/relatorios/opcoes');
    return OpcoesRelatorio.fromJson(_map(response.data?['data']));
  }

  Future<ArquivoBaixado> exportarRelatorio(
    Map<String, String> filtros, {
    required List<String> abas,
    bool anonimizar = false,
  }) async {
    final response = await api.dio.get<List<int>>(
      '/staff/relatorios/exportar',
      queryParameters: {...filtros, 'abas': abas.join(','), if (anonimizar) 'anonimizar': '1'},
      options: _download,
    );
    return _arquivo(response, 'relatorio-vise.xlsx');
  }

  Future<List<TipoImportacao>> tiposImportacao() async {
    final response = await api.dio.get<Map<String, dynamic>>('/staff/importacao');
    return _maps(response.data?['data']).map(TipoImportacao.fromJson).toList();
  }

  Future<ArquivoBaixado> modelo(String tipo) async {
    final response = await api.dio.get<List<int>>('/staff/importacao/$tipo/modelo', options: _download);
    return _arquivo(response, 'modelo-importacao-$tipo.xlsx');
  }

  Future<ResultadoImportacao> importar(
    String tipo,
    Uint8List bytes,
    String nomeArquivo, {
    required bool simular,
  }) async {
    final response = await api.dio.post<Map<String, dynamic>>(
      '/staff/importacao/$tipo',
      data: FormData.fromMap({
        'arquivo': MultipartFile.fromBytes(bytes, filename: nomeArquivo),
        'simular': simular ? '1' : '0',
      }),
      options: Options(sendTimeout: const Duration(minutes: 2), receiveTimeout: const Duration(minutes: 5)),
    );
    return ResultadoImportacao.fromJson(_map(response.data?['data']));
  }

  static ArquivoBaixado _arquivo(Response<List<int>> response, String padrao) {
    final bytes = Uint8List.fromList(response.data ?? const []);
    return (bytes: bytes, nome: nomeDoArquivo(response.headers.value('content-disposition')) ?? padrao);
  }
}

/// Nome do arquivo no cabeçalho Content-Disposition (prefere o filename* UTF-8).
String? nomeDoArquivo(String? disposition) {
  if (disposition == null) return null;
  final utf8 = RegExp(r"filename\*=UTF-8''([^;]+)", caseSensitive: false).firstMatch(disposition);
  if (utf8 != null) return Uri.decodeComponent(utf8.group(1)!.trim());
  final simples = RegExp(r'filename="?([^";]+)"?', caseSensitive: false).firstMatch(disposition);
  return simples?.group(1)?.trim();
}

int _int(dynamic value) => value is int ? value : (value is num ? value.toInt() : int.tryParse('${value ?? ''}') ?? 0);

double? _double(dynamic value) => value is num ? value.toDouble() : double.tryParse('${value ?? ''}');

Map<String, dynamic> _map(dynamic value) => value is Map ? Map<String, dynamic>.from(value) : const {};

List<String> _strings(dynamic value) => value is Iterable ? value.map((e) => '${e ?? ''}').toList() : const [];

List<Map<String, dynamic>> _maps(dynamic value) =>
    value is Iterable ? value.whereType<Map>().map(Map<String, dynamic>.from).toList() : const [];

List<Opcao> _idNome(dynamic value) => _maps(value).map((m) => Opcao('${m['id']}', '${m['nome'] ?? ''}')).toList();

List<Opcao> _pares(dynamic value) => _maps(value).map((m) => Opcao('${m['valor']}', '${m['rotulo'] ?? ''}')).toList();
