import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_theme.dart';
import '../../../data/repositories/cadastros_repository.dart';
import '../../../data/repositories/painel_repository.dart';
import '../../../providers/app_providers.dart';
import '../../common/ui_kit.dart';
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
  static const _periodos = {
    '7': '7 dias',
    '30': '30 dias',
    '90': '90 dias',
    '365': '12 meses',
    'tudo': 'Tudo',
  };

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
    _filtros = {
      for (final e in filtros.entries)
        if (e.value.isNotEmpty) e.key: e.value,
    };
    _carregar();
  }

  Future<void> _abrirFiltros() async {
    final escolhidos = await showModalBottomSheet<Map<String, String>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (_) => _FiltrosSheet(filtros: _filtros),
    );
    if (escolhidos != null) {
      _aplicar({...escolhidos, 'periodo': _filtros['periodo'] ?? '30'});
    }
  }

  @override
  Widget build(BuildContext context) {
    final dados = _dados;
    final extras =
        dados?.filtros.where((f) => f.rotulo != 'Período').toList() ?? const [];
    return Scaffold(
      drawer: widget.drawer,
      appBar: AppBar(
        title: const Text('Painel'),
        actions: [
          BotaoRedondo(
            icone: Icons.tune_rounded,
            tooltip: 'Filtros',
            onPressed: _abrirFiltros,
          ),
          const SizedBox(width: 8),
          BotaoRedondo(
            icone: Icons.refresh_rounded,
            tooltip: 'Atualizar',
            onPressed: _carregando ? null : _carregar,
          ),
          const SizedBox(width: 12),
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
                        onSelected: (_) =>
                            _aplicar({..._filtros, 'periodo': p.key}),
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
                    for (final f in extras)
                      Chip(label: Text('${f.rotulo}: ${f.valor}')),
                    TextButton(
                      onPressed: () =>
                          _aplicar({'periodo': _filtros['periodo'] ?? '30'}),
                      child: const Text('Limpar filtros'),
                    ),
                  ],
                ),
              ),
            const SizedBox(height: 12),
            if (_carregando && dados == null)
              const Padding(
                padding: EdgeInsets.all(48),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_erro != null && dados == null)
              Column(
                children: [
                  Vazio(_erro!),
                  TextButton(
                    onPressed: _carregar,
                    child: const Text('Tentar de novo'),
                  ),
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
    final grafico = CartaoSecao(
      titulo: 'Questionários concluídos',
      subtitulo: 'Por ${d.granularidade == 'mes' ? 'mês' : 'dia'}',
      child: GraficoBarras(pontos: d.pontos),
    );
    final medidor = CartaoSecao(
      titulo: 'Participação',
      subtitulo: '${r.participantes} de ${r.alunos} alunos',
      child: MedidorParticipacao(
        participacao: r.participacao,
        concluiram: r.participantes,
        pendentes: (r.alunos - r.participantes).clamp(0, r.alunos),
      ),
    );
    return [
      HeroCard(
        onTap: widget.onGerarRelatorio == null
            ? null
            : () => widget.onGerarRelatorio!(_filtros),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Alunos acompanhados',
                    style: GoogleFonts.figtree(
                      fontWeight: FontWeight.w700,
                      fontSize: 14,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    '${r.alunos}',
                    style: GoogleFonts.figtree(
                      fontSize: 44,
                      fontWeight: FontWeight.w800,
                      height: 1,
                      letterSpacing: -1.5,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    '${r.turmas} turma(s) · ${r.escolas} escola(s)',
                    style: GoogleFonts.figtree(
                      fontWeight: FontWeight.w600,
                      color: AppColors.ink.withValues(alpha: 0.75),
                    ),
                  ),
                ],
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.ink,
                    borderRadius: BorderRadius.circular(99),
                  ),
                  child: Text(
                    '${formatarPct(r.participacao)} participação',
                    style: GoogleFonts.figtree(
                      color: AppColors.mint,
                      fontWeight: FontWeight.w800,
                      fontSize: 12.5,
                    ),
                  ),
                ),
                if (widget.onGerarRelatorio != null) ...[
                  const SizedBox(height: 26),
                  Text(
                    'Gerar relatório →',
                    style: GoogleFonts.figtree(
                      fontWeight: FontWeight.w800,
                      fontSize: 13,
                    ),
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
      const SizedBox(height: 12),
      GradeKpis(
        cards: [
          KpiCard(
            rotulo: 'Concluídos',
            valor: '${r.concluidos}',
            detalhe: '${r.emAndamento} em andamento',
          ),
          KpiCard(
            rotulo: 'Aplicações ativas',
            valor: '${r.aplicacoesAtivas}',
            detalhe: 'Salas abertas agora',
          ),
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
      LayoutBuilder(
        builder: (context, c) => c.maxWidth >= 720
            ? Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(flex: 3, child: grafico),
                  const SizedBox(width: 14),
                  Expanded(flex: 2, child: medidor),
                ],
              )
            : Column(children: [grafico, medidor]),
      ),
      CartaoSecao(
        titulo: 'Níveis de atenção por categoria',
        child: d.classificacao.isEmpty
            ? const Vazio('Sem resultados para classificar neste recorte.')
            : Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const LegendaNiveis(),
                  for (final c in d.classificacao) BarraNiveis(categoria: c),
                ],
              ),
      ),
      CartaoSecao(
        titulo: 'Participação por turma',
        subtitulo:
            'Menor participação primeiro · toque para gerar o relatório da turma',
        child: d.turmas.isEmpty
            ? const Vazio('Nenhuma turma com alunos neste recorte.')
            : Column(
                children: [
                  for (final t in d.turmas.take(12))
                    _TurmaTile(turma: t, onTap: () => _relatorioDaTurma(t)),
                ],
              ),
      ),
      CartaoSecao(
        titulo: 'Aplicações ativas',
        child: d.aplicacoes.isEmpty
            ? const Vazio('Nenhuma aplicação ativa no momento.')
            : Column(
                children: [
                  for (final a in d.aplicacoes) _AplicacaoTile(aplicacao: a),
                ],
              ),
      ),
      CartaoSecao(
        titulo: 'Alertas recentes',
        subtitulo: 'Alunos com alguma categoria em nível prioritário',
        child: d.alertas.isEmpty
            ? const Vazio('Nenhum aluno em nível prioritário neste recorte.')
            : Column(
                children: [for (final a in d.alertas) _AlertaTile(alerta: a)],
              ),
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
    gerar({
      ..._filtros,
      'turma_id': '${t.turmaId}',
      if (t.escolaId != null) 'escola_id': '${t.escolaId}',
    });
  }
}

class _TurmaTile extends StatelessWidget {
  const _TurmaTile({required this.turma, required this.onTap});

  final TurmaPainel turma;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = (turma.participacao ?? 0).clamp(0.0, 1.0);
    final cor = p < 0.5 ? AppColors.warn : AppColors.mint;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadii.md),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 2),
        child: Row(
          children: [
            SizedBox(
              width: 50,
              height: 50,
              child: Stack(
                alignment: Alignment.center,
                children: [
                  SizedBox.expand(
                    child: CircularProgressIndicator(
                      value: p,
                      strokeWidth: 5,
                      strokeCap: StrokeCap.round,
                      color: cor,
                      backgroundColor: AppColors.surfaceRaised,
                    ),
                  ),
                  Text(
                    formatarPct(turma.participacao),
                    style: GoogleFonts.figtree(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    turma.turma,
                    style: GoogleFonts.figtree(
                      fontWeight: FontWeight.w800,
                      fontSize: 15,
                    ),
                  ),
                  Text(
                    '${[turma.escola, ?turma.turno].join(' · ')} · ${turma.participantes}/${turma.alunos}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: GoogleFonts.figtree(
                      color: AppColors.muted,
                      fontSize: 12,
                    ),
                  ),
                  if (turma.prioritarios > 0 || turma.atencao > 0) ...[
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 6,
                      runSpacing: 4,
                      children: [
                        if (turma.prioritarios > 0)
                          Selo(
                            '${turma.prioritarios} prioritário(s)',
                            cor: corAlto,
                          ),
                        if (turma.atencao > 0)
                          Selo('${turma.atencao} atenção', cor: AppColors.warn),
                      ],
                    ),
                  ],
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: AppColors.muted),
          ],
        ),
      ),
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
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: AppColors.mintSoft,
              borderRadius: BorderRadius.circular(14),
            ),
            child: const Icon(
              Icons.play_circle_outline_rounded,
              color: AppColors.mint,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        a.questionario,
                        style: GoogleFonts.figtree(fontWeight: FontWeight.w800),
                      ),
                    ),
                    Text(
                      formatarPct(a.progresso),
                      style: GoogleFonts.figtree(
                        color: AppColors.mint,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
                Text(
                  [
                    a.alvo,
                    a.escola,
                    if (a.codigo != null) 'sala ${a.codigo}',
                  ].join(' · '),
                  style: GoogleFonts.figtree(
                    color: AppColors.muted,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 8),
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: LinearProgressIndicator(
                    value: (a.progresso ?? 0).clamp(0, 1),
                    minHeight: 7,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  '${a.concluidos} de ${a.alvoTotal} concluíram${a.emAndamento > 0 ? ' · ${a.emAndamento} respondendo' : ''}',
                  style: GoogleFonts.figtree(
                    fontSize: 12,
                    color: AppColors.muted,
                  ),
                ),
              ],
            ),
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
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AvatarIniciais(
            nome: a.aluno,
            tamanho: 44,
            fundo: corAlto.withValues(alpha: 0.18),
            corTexto: const Color(0xFFFFB4AE),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        a.aluno,
                        style: GoogleFonts.figtree(
                          fontWeight: FontWeight.w800,
                          fontSize: 15,
                        ),
                      ),
                    ),
                    Text(
                      formatarDataHora(a.data),
                      style: GoogleFonts.figtree(
                        color: AppColors.muted,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
                Text(
                  [?a.turma, a.escola, a.questionario].join(' · '),
                  style: GoogleFonts.figtree(
                    color: AppColors.muted,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 6),
                Wrap(
                  spacing: 6,
                  runSpacing: 4,
                  children: [
                    for (final c in a.categorias)
                      Selo(c, cor: const Color(0xFFFFB4AE)),
                  ],
                ),
              ],
            ),
          ),
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
      padding: EdgeInsets.fromLTRB(
        20,
        18,
        20,
        24 + MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: opcoes.when(
        loading: () => const SizedBox(
          height: 160,
          child: Center(child: CircularProgressIndicator()),
        ),
        error: (e, _) =>
            Text(mensagemDeErro(e, 'Não foi possível carregar os filtros.')),
        data: (o) {
          final turmas = o.turmas
              .where((t) => _escola == null || '${t.escolaId}' == _escola)
              .toList();
          return Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Filtrar painel',
                style: GoogleFonts.figtree(
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                ),
              ),
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
                  for (final t in turmas)
                    Opcao(
                      '${t.id}',
                      o.escolas.length > 1 && _escola == null
                          ? '${t.nome} — ${t.escola}'
                          : t.nome,
                    ),
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
      initialValue: existe
          ? valor
          : (vazio == null && opcoes.isNotEmpty ? opcoes.first.valor : null),
      isExpanded: true,
      decoration: InputDecoration(labelText: rotulo),
      dropdownColor: AppColors.surfaceRaised,
      items: [
        if (vazio != null)
          DropdownMenuItem<String?>(value: null, child: Text(vazio!)),
        for (final o in opcoes)
          DropdownMenuItem<String?>(
            value: o.valor,
            child: Text(o.rotulo, overflow: TextOverflow.ellipsis),
          ),
      ],
      onChanged: onChanged,
    );
  }
}
