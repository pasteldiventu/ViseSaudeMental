import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_theme.dart';
import '../../../data/repositories/cadastros_repository.dart';
import '../../../data/repositories/painel_repository.dart';
import '../../../providers/app_providers.dart';
import 'painel_widgets.dart';

/// Indicadores gerais no escopo do usuário (mesmos números do painel web).
class PainelScreen extends ConsumerStatefulWidget {
  const PainelScreen({super.key, this.drawer, this.onGerarRelatorio});

  final Widget? drawer;

  /// Abre a tela de relatórios já com os filtros informados.
  final ValueChanged<Map<String, String>>? onGerarRelatorio;

  @override
  ConsumerState<PainelScreen> createState() => _PainelScreenState();
}

class _PainelScreenState extends ConsumerState<PainelScreen> {
  static const _periodos = {'7': '7 dias', '30': '30 dias', '90': '90 dias', '365': '12 meses', 'tudo': 'Tudo'};

  Map<String, String> _filtros = {'periodo': '30'};
  DadosPainel? _dados;
  bool _carregando = true;
  String? _erro;

  @override
  void initState() {
    super.initState();
    Future.microtask(_carregar);
  }

  Future<void> _carregar() async {
    setState(() {
      _carregando = true;
      _erro = null;
    });
    try {
      final dados = await ref.read(painelRepositoryProvider).painel(_filtros);
      if (!mounted) return;
      setState(() {
        _dados = dados;
        _carregando = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _erro = mensagemDeErro(error, 'Não foi possível carregar o painel.');
        _carregando = false;
      });
    }
  }

  void _aplicar(Map<String, String> filtros) {
    _filtros = {for (final e in filtros.entries) if (e.value.isNotEmpty) e.key: e.value};
    _carregar();
  }

  Future<void> _abrirFiltros() async {
    final escolhidos = await showModalBottomSheet<Map<String, String>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.surface,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (_) => _FiltrosSheet(filtros: _filtros),
    );
    if (escolhidos != null) _aplicar({...escolhidos, 'periodo': _filtros['periodo'] ?? '30'});
  }

  @override
  Widget build(BuildContext context) {
    final dados = _dados;
    final extras = dados?.filtros.where((f) => f.rotulo != 'Período').toList() ?? const [];
    return Scaffold(
      drawer: widget.drawer,
      appBar: AppBar(
        title: const Text('PAINEL'),
        actions: [
          IconButton(tooltip: 'Filtros', onPressed: _abrirFiltros, icon: const Icon(Icons.filter_list_rounded)),
          IconButton(
            tooltip: 'Atualizar',
            onPressed: _carregando ? null : _carregar,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: widget.onGerarRelatorio == null
          ? null
          : FloatingActionButton.extended(
              onPressed: () => widget.onGerarRelatorio!(_filtros),
              icon: const Icon(Icons.table_view_rounded),
              label: const Text('Gerar relatório'),
            ),
      body: RefreshIndicator(
        onRefresh: _carregar,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 100),
          children: [
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  for (final p in _periodos.entries)
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(
                        label: Text(p.value),
                        selected: (_filtros['periodo'] ?? '30') == p.key,
                        onSelected: (_) => _aplicar({..._filtros, 'periodo': p.key}),
                      ),
                    ),
                ],
              ),
            ),
            if (extras.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Wrap(
                  spacing: 8,
                  runSpacing: 6,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    for (final f in extras) Chip(label: Text('${f.rotulo}: ${f.valor}')),
                    TextButton(
                      onPressed: () => _aplicar({'periodo': _filtros['periodo'] ?? '30'}),
                      child: const Text('Limpar filtros'),
                    ),
                  ],
                ),
              ),
            const SizedBox(height: 12),
            if (_carregando && dados == null)
              const Padding(padding: EdgeInsets.all(48), child: Center(child: CircularProgressIndicator()))
            else if (_erro != null && dados == null)
              Column(
                children: [
                  Vazio(_erro!),
                  TextButton(onPressed: _carregar, child: const Text('Tentar de novo')),
                ],
              )
            else if (dados != null) ...[
              if (_carregando) const LinearProgressIndicator(minHeight: 2),
              ..._conteudo(dados),
            ],
          ],
        ),
      ),
    );
  }

  List<Widget> _conteudo(DadosPainel d) {
    final r = d.resumo;
    return [
      GradeKpis(
        cards: [
          KpiCard(rotulo: 'Alunos', valor: '${r.alunos}', detalhe: '${r.turmas} turma(s) · ${r.escolas} escola(s)'),
          KpiCard(
            rotulo: 'Participação',
            valor: formatarPct(r.participacao),
            detalhe: '${r.participantes} de ${r.alunos} concluíram',
            cor: AppColors.mint,
            progresso: r.participacao ?? 0,
          ),
          KpiCard(
            rotulo: 'Concluídos',
            valor: '${r.concluidos}',
            detalhe: '${r.emAndamento} em andamento',
            cor: AppColors.mintDeep,
          ),
          KpiCard(rotulo: 'Aplicações ativas', valor: '${r.aplicacoesAtivas}', detalhe: 'Salas abertas agora'),
          KpiCard(
            rotulo: 'Prioritário',
            valor: '${r.prioritarios}',
            detalhe: 'Alunos que pedem intervenção',
            cor: corAlto,
            destaque: r.prioritarios > 0,
          ),
          KpiCard(
            rotulo: 'Atenção',
            valor: '${r.atencao}',
            detalhe: 'Alunos para acompanhar',
            cor: AppColors.warn,
            destaque: r.atencao > 0,
          ),
        ],
      ),
      const SizedBox(height: 14),
      CartaoSecao(
        titulo: 'Concluídos por ${d.granularidade == 'mes' ? 'mês' : 'dia'}',
        child: GraficoBarras(pontos: d.pontos),
      ),
      CartaoSecao(
        titulo: 'Níveis de atenção por categoria',
        child: d.classificacao.isEmpty
            ? const Vazio('Sem resultados para classificar neste recorte.')
            : Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [const LegendaNiveis(), for (final c in d.classificacao) BarraNiveis(categoria: c)],
              ),
      ),
      CartaoSecao(
        titulo: 'Participação por turma',
        subtitulo: 'Menor participação primeiro · toque para gerar o relatório da turma',
        child: d.turmas.isEmpty
            ? const Vazio('Nenhuma turma com alunos neste recorte.')
            : Column(children: [for (final t in d.turmas.take(12)) _TurmaTile(turma: t, onTap: () => _relatorioDaTurma(t))]),
      ),
      CartaoSecao(
        titulo: 'Aplicações ativas',
        child: d.aplicacoes.isEmpty
            ? const Vazio('Nenhuma aplicação ativa no momento.')
            : Column(children: [for (final a in d.aplicacoes) _AplicacaoTile(aplicacao: a)]),
      ),
      CartaoSecao(
        titulo: 'Alertas recentes',
        subtitulo: 'Alunos com alguma categoria em nível prioritário',
        child: d.alertas.isEmpty
            ? const Vazio('Nenhum aluno em nível prioritário neste recorte.')
            : Column(children: [for (final a in d.alertas) _AlertaTile(alerta: a)]),
      ),
      Text(
        'Dados sensíveis (LGPD): use-os apenas para o acolhimento dos alunos.',
        textAlign: TextAlign.center,
        style: GoogleFonts.figtree(color: AppColors.muted, fontSize: 12),
      ),
    ];
  }

  void _relatorioDaTurma(TurmaPainel t) {
    final gerar = widget.onGerarRelatorio;
    if (gerar == null || t.turmaId == null) return;
    gerar({..._filtros, 'turma_id': '${t.turmaId}', if (t.escolaId != null) 'escola_id': '${t.escolaId}'});
  }
}

class _TurmaTile extends StatelessWidget {
  const _TurmaTile({required this.turma, required this.onTap});

  final TurmaPainel turma;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final baixa = (turma.participacao ?? 0) < 0.5;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadii.sm),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(turma.turma, style: GoogleFonts.figtree(fontWeight: FontWeight.w700)),
                ),
                if (turma.prioritarios > 0) _Selo('${turma.prioritarios} prioritário(s)', corAlto),
                if (turma.atencao > 0) _Selo('${turma.atencao} atenção', AppColors.warn),
              ],
            ),
            Text(
              [turma.escola, ?turma.turno].join(' · '),
              style: GoogleFonts.figtree(color: AppColors.muted, fontSize: 12),
            ),
            const SizedBox(height: 6),
            Row(
              children: [
                Expanded(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: LinearProgressIndicator(
                      value: (turma.participacao ?? 0).clamp(0, 1),
                      minHeight: 7,
                      color: baixa ? AppColors.warn : AppColors.mint,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Text(
                  '${formatarPct(turma.participacao)} · ${turma.participantes}/${turma.alunos}',
                  style: GoogleFonts.figtree(fontSize: 12.5, fontWeight: FontWeight.w700),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _Selo extends StatelessWidget {
  const _Selo(this.texto, this.cor);

  final String texto;
  final Color cor;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(left: 6),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(color: cor.withValues(alpha: 0.18), borderRadius: BorderRadius.circular(999)),
      child: Text(texto, style: GoogleFonts.figtree(color: cor, fontSize: 11.5, fontWeight: FontWeight.w800)),
    );
  }
}

class _AplicacaoTile extends StatelessWidget {
  const _AplicacaoTile({required this.aplicacao});

  final AplicacaoPainel aplicacao;

  @override
  Widget build(BuildContext context) {
    final a = aplicacao;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: Text(a.questionario, style: GoogleFonts.figtree(fontWeight: FontWeight.w700))),
              if (a.codigo != null)
                Text(
                  a.codigo!,
                  style: GoogleFonts.figtree(color: AppColors.mint, fontWeight: FontWeight.w800, letterSpacing: 1.5),
                ),
            ],
          ),
          Text('${a.alvo} · ${a.escola}', style: GoogleFonts.figtree(color: AppColors.muted, fontSize: 12)),
          const SizedBox(height: 6),
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: LinearProgressIndicator(value: (a.progresso ?? 0).clamp(0, 1), minHeight: 7),
          ),
          const SizedBox(height: 4),
          Text(
            '${a.concluidos} de ${a.alvoTotal} concluíram${a.emAndamento > 0 ? ' · ${a.emAndamento} respondendo' : ''}',
            style: GoogleFonts.figtree(fontSize: 12.5),
          ),
        ],
      ),
    );
  }
}

class _AlertaTile extends StatelessWidget {
  const _AlertaTile({required this.alerta});

  final AlertaPainel alerta;

  @override
  Widget build(BuildContext context) {
    final a = alerta;
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: corAlto.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(AppRadii.sm),
        border: Border(left: BorderSide(color: corAlto, width: 3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(a.aluno, style: GoogleFonts.figtree(fontWeight: FontWeight.w800))),
              Text(formatarDataHora(a.data), style: GoogleFonts.figtree(color: AppColors.muted, fontSize: 12)),
            ],
          ),
          Text(
            [?a.turma, a.escola, a.questionario].join(' · '),
            style: GoogleFonts.figtree(color: AppColors.muted, fontSize: 12),
          ),
          const SizedBox(height: 6),
          for (final c in a.categorias)
            Text('• $c', style: GoogleFonts.figtree(color: const Color(0xFFFFB4AE), fontSize: 13, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}

/// Escola, turma e questionário do painel (o período fica nos chips da tela).
class _FiltrosSheet extends ConsumerStatefulWidget {
  const _FiltrosSheet({required this.filtros});

  final Map<String, String> filtros;

  @override
  ConsumerState<_FiltrosSheet> createState() => _FiltrosSheetState();
}

class _FiltrosSheetState extends ConsumerState<_FiltrosSheet> {
  late String? _escola = widget.filtros['escola_id'];
  late String? _turma = widget.filtros['turma_id'];
  late String? _questionario = widget.filtros['questionario_id'];

  @override
  Widget build(BuildContext context) {
    final opcoes = ref.watch(opcoesRelatorioProvider);
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 18, 20, 24 + MediaQuery.viewInsetsOf(context).bottom),
      child: opcoes.when(
        loading: () => const SizedBox(height: 160, child: Center(child: CircularProgressIndicator())),
        error: (e, _) => Text(mensagemDeErro(e, 'Não foi possível carregar os filtros.')),
        data: (o) {
          final turmas = o.turmas.where((t) => _escola == null || '${t.escolaId}' == _escola).toList();
          return Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('Filtrar painel', style: GoogleFonts.figtree(fontSize: 20, fontWeight: FontWeight.w800)),
              const SizedBox(height: 14),
              if (o.escolas.length > 1) ...[
                SeletorOpcao(
                  rotulo: 'Escola',
                  vazio: 'Todas as escolas',
                  valor: _escola,
                  opcoes: o.escolas,
                  onChanged: (v) => setState(() {
                    _escola = v;
                    _turma = null;
                  }),
                ),
                const SizedBox(height: 12),
              ],
              SeletorOpcao(
                key: ValueKey('turmas-$_escola'),
                rotulo: 'Turma',
                vazio: 'Todas as turmas',
                valor: _turma,
                opcoes: [
                  for (final t in turmas) Opcao('${t.id}', o.escolas.length > 1 && _escola == null ? '${t.nome} — ${t.escola}' : t.nome),
                ],
                onChanged: (v) => setState(() => _turma = v),
              ),
              const SizedBox(height: 12),
              SeletorOpcao(
                rotulo: 'Questionário',
                vazio: 'Todos os questionários',
                valor: _questionario,
                opcoes: o.questionarios,
                onChanged: (v) => setState(() => _questionario = v),
              ),
              const SizedBox(height: 18),
              FilledButton(
                onPressed: () => Navigator.of(context).pop({
                  'escola_id': ?_escola,
                  'turma_id': ?_turma,
                  'questionario_id': ?_questionario,
                }),
                child: const Text('Aplicar filtros'),
              ),
            ],
          );
        },
      ),
    );
  }
}

/// Dropdown com a opção "todos" (valor nulo).
class SeletorOpcao extends StatelessWidget {
  const SeletorOpcao({
    super.key,
    required this.rotulo,
    required this.vazio,
    required this.valor,
    required this.opcoes,
    required this.onChanged,
  });

  final String rotulo;
  final String? vazio;
  final String? valor;
  final List<Opcao> opcoes;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) {
    final existe = opcoes.any((o) => o.valor == valor);
    return DropdownButtonFormField<String?>(
      initialValue: existe ? valor : (vazio == null && opcoes.isNotEmpty ? opcoes.first.valor : null),
      isExpanded: true,
      decoration: InputDecoration(labelText: rotulo),
      dropdownColor: AppColors.surfaceRaised,
      items: [
        if (vazio != null) DropdownMenuItem<String?>(value: null, child: Text(vazio!)),
        for (final o in opcoes)
          DropdownMenuItem<String?>(value: o.valor, child: Text(o.rotulo, overflow: TextOverflow.ellipsis)),
      ],
      onChanged: onChanged,
    );
  }
}
