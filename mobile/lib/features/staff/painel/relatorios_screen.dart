import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/files/entregar_arquivo.dart';
import '../../../core/theme/app_theme.dart';
import '../../../data/repositories/cadastros_repository.dart';
import '../../../data/repositories/painel_repository.dart';
import '../../../providers/app_providers.dart';
import 'painel_screen.dart';
import 'painel_widgets.dart';

/// Relatório em Excel com filtros (escola, turma, série, turno, sexo, questionário, período).
class RelatoriosScreen extends ConsumerStatefulWidget {
  const RelatoriosScreen({
    super.key,
    this.drawer,
    this.filtrosIniciais = const {},
  });

  final Widget? drawer;
  final Map<String, String> filtrosIniciais;

  @override
  ConsumerState<RelatoriosScreen> createState() => _RelatoriosScreenState();
}

class _RelatoriosScreenState extends ConsumerState<RelatoriosScreen> {
  late final Map<String, String> _filtros = {
    'periodo': 'tudo',
    ...widget.filtrosIniciais,
  };
  Set<String>? _abas;
  bool _anonimizar = false;
  bool _gerando = false;
  int _versaoPeriodo = 0;

  void _definir(String chave, String? valor) => setState(() {
    if (valor == null || valor.isEmpty) {
      _filtros.remove(chave);
    } else {
      _filtros[chave] = valor;
    }
  });

  Future<void> _escolherDatas() async {
    final hoje = DateTime.now();
    final inicio = DateTime.tryParse(_filtros['inicio'] ?? '');
    final fim = DateTime.tryParse(_filtros['fim'] ?? '');
    final faixa = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2020),
      lastDate: hoje,
      initialDateRange: inicio != null && fim != null
          ? DateTimeRange(start: inicio, end: fim)
          : null,
      helpText: 'Período do relatório',
    );
    if (faixa == null) {
      setState(() => _versaoPeriodo++);
      return;
    }
    String iso(DateTime d) =>
        '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
    setState(() {
      _filtros['periodo'] = 'personalizado';
      _filtros['inicio'] = iso(faixa.start);
      _filtros['fim'] = iso(faixa.end);
    });
  }

  Future<void> _gerar(List<AbaRelatorio> todas) async {
    setState(() => _gerando = true);
    try {
      final abas =
          _abas ??
          {
            for (final a in todas)
              if (a.padrao) a.chave,
          };
      final filtros = Map.of(_filtros);
      if (filtros['periodo'] != 'personalizado') {
        filtros
          ..remove('inicio')
          ..remove('fim');
      }
      final arquivo = await ref
          .read(painelRepositoryProvider)
          .exportarRelatorio(
            filtros,
            abas: abas.toList(),
            anonimizar: _anonimizar,
          );
      await entregarArquivo(arquivo.bytes, arquivo.nome);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Relatório gerado: ${arquivo.nome}')),
        );
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              mensagemDeErro(error, 'Não foi possível gerar o relatório.'),
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _gerando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final opcoes = ref.watch(opcoesRelatorioProvider);
    return Scaffold(
      drawer: widget.drawer,
      appBar: AppBar(title: const Text('Relatórios')),
      body: opcoes.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(mensagemDeErro(e, 'Não foi possível carregar os filtros.')),
              TextButton(
                onPressed: () => ref.invalidate(opcoesRelatorioProvider),
                child: const Text('Tentar de novo'),
              ),
            ],
          ),
        ),
        data: _formulario,
      ),
    );
  }

  Widget _formulario(OpcoesRelatorio o) {
    final escola = _filtros['escola_id'];
    final turmas = o.turmas
        .where((t) => escola == null || '${t.escolaId}' == escola)
        .toList();
    final abas =
        _abas ??
        {
          for (final a in o.abas)
            if (a.padrao) a.chave,
        };
    final personalizado = _filtros['periodo'] == 'personalizado';

    Widget campo(Widget child) =>
        Padding(padding: const EdgeInsets.only(bottom: 12), child: child);

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 40),
      children: [
        CartaoSecao(
          titulo: '1. Recorte dos dados',
          subtitulo:
              'Deixe em branco para incluir tudo o que seu perfil pode ver.',
          child: Column(
            children: [
              if (o.escolas.length > 1)
                campo(
                  SeletorOpcao(
                    rotulo: 'Escola',
                    vazio: 'Todas as escolas',
                    valor: escola,
                    opcoes: o.escolas,
                    onChanged: (v) {
                      _definir('escola_id', v);
                      _definir('turma_id', null);
                    },
                  ),
                ),
              campo(
                SeletorOpcao(
                  key: ValueKey('turma-$escola'),
                  rotulo: 'Turma',
                  vazio: 'Todas as turmas',
                  valor: _filtros['turma_id'],
                  opcoes: [
                    for (final t in turmas)
                      Opcao(
                        '${t.id}',
                        o.escolas.length > 1 && escola == null
                            ? '${t.nome} — ${t.escola}'
                            : t.nome,
                      ),
                  ],
                  onChanged: (v) => _definir('turma_id', v),
                ),
              ),
              campo(
                SeletorOpcao(
                  rotulo: 'Série',
                  vazio: 'Todas as séries',
                  valor: _filtros['serie_id'],
                  opcoes: o.series,
                  onChanged: (v) => _definir('serie_id', v),
                ),
              ),
              campo(
                SeletorOpcao(
                  rotulo: 'Turno',
                  vazio: 'Todos os turnos',
                  valor: _filtros['turno'],
                  opcoes: o.turnos,
                  onChanged: (v) => _definir('turno', v),
                ),
              ),
              if (o.sexos.isNotEmpty)
                campo(
                  SeletorOpcao(
                    rotulo: 'Sexo',
                    vazio: 'Todos',
                    valor: _filtros['sexo'],
                    opcoes: o.sexos,
                    onChanged: (v) => _definir('sexo', v),
                  ),
                ),
              campo(
                SeletorOpcao(
                  rotulo: 'Questionário',
                  vazio: 'Todos os questionários',
                  valor: _filtros['questionario_id'],
                  opcoes: o.questionarios,
                  onChanged: (v) => _definir('questionario_id', v),
                ),
              ),
              campo(
                SeletorOpcao(
                  key: ValueKey(
                    'periodo-${_filtros['periodo']}-$_versaoPeriodo',
                  ),
                  rotulo: 'Período',
                  vazio: null,
                  valor: _filtros['periodo'],
                  opcoes: o.periodos,
                  onChanged: (v) {
                    if (v == 'personalizado') {
                      _escolherDatas();
                    } else {
                      _definir('periodo', v);
                    }
                  },
                ),
              ),
              if (personalizado)
                OutlinedButton.icon(
                  onPressed: _escolherDatas,
                  icon: const Icon(Icons.date_range_rounded),
                  label: Text(
                    _filtros['inicio'] == null
                        ? 'Escolher datas'
                        : '${_br(_filtros['inicio'])} a ${_br(_filtros['fim'])}',
                  ),
                ),
            ],
          ),
        ),
        CartaoSecao(
          titulo: '2. Conteúdo do arquivo',
          subtitulo: 'Cada item vira uma aba da planilha.',
          child: Column(
            children: [
              for (final a in o.abas)
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: a.obrigatoria || abas.contains(a.chave),
                  onChanged: a.obrigatoria
                      ? null
                      : (marcado) => setState(() {
                          final novas = {...abas};
                          marcado == true
                              ? novas.add(a.chave)
                              : novas.remove(a.chave);
                          _abas = novas;
                        }),
                  title: Text(
                    a.rotulo,
                    style: GoogleFonts.figtree(fontWeight: FontWeight.w700),
                  ),
                  subtitle: Text(
                    a.descricao,
                    style: GoogleFonts.figtree(
                      color: AppColors.muted,
                      fontSize: 12.5,
                    ),
                  ),
                ),
              const Divider(color: AppColors.border),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: _anonimizar,
                onChanged: (v) => setState(() => _anonimizar = v),
                title: Text(
                  'Anonimizar alunos',
                  style: GoogleFonts.figtree(fontWeight: FontWeight.w700),
                ),
                subtitle: Text(
                  'Troca nomes por códigos e remove CPF, matrícula e contatos. Use para compartilhar com pesquisa ou secretaria.',
                  style: GoogleFonts.figtree(
                    color: AppColors.muted,
                    fontSize: 12.5,
                  ),
                ),
              ),
            ],
          ),
        ),
        FilledButton.icon(
          onPressed: _gerando ? null : () => _gerar(o.abas),
          icon: _gerando
              ? const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(strokeWidth: 2.5),
                )
              : const Icon(Icons.download_rounded),
          label: Text(_gerando ? 'Gerando…' : 'Baixar relatório (.xlsx)'),
        ),
        const SizedBox(height: 10),
        Text(
          'O arquivo abre no Excel, Google Planilhas ou LibreOffice. Dados sensíveis: compartilhe só com quem precisa (LGPD).',
          textAlign: TextAlign.center,
          style: GoogleFonts.figtree(color: AppColors.muted, fontSize: 12),
        ),
      ],
    );
  }

  static String _br(String? iso) {
    final partes = (iso ?? '').split('-');
    return partes.length == 3 ? '${partes[2]}/${partes[1]}/${partes[0]}' : '';
  }
}
