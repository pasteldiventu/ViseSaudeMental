import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_theme.dart';
import '../../../data/repositories/cadastros_repository.dart';
import '../../../providers/app_providers.dart';
import '../../common/ui_kit.dart';
import '../escola_foco.dart';
import 'cadastro_detalhe_screen.dart';
import 'cadastro_form_screen.dart';
import 'cadastro_icons.dart';

class CadastroListaScreen extends ConsumerStatefulWidget {
  const CadastroListaScreen({
    super.key,
    required this.meta,
    this.filtros = const {},
    this.drawer,
  });

  final CadastroMeta meta;
  final Map<String, String> filtros;
  final Widget? drawer;

  @override
  ConsumerState<CadastroListaScreen> createState() =>
      _CadastroListaScreenState();
}

class _CadastroListaScreenState extends ConsumerState<CadastroListaScreen> {
  final _busca = TextEditingController();
  Timer? _debounce;
  late Map<String, String> _filtros = {
    if (_segueFoco) 'escola_id': ?ref.read(escolaFocoProvider)?.id.toString(),
    ...widget.filtros,
  };
  List<RegistroResumo> _registros = const [];
  List<FiltroAtivo> _filtrosAtivos = const [];
  int _page = 1;
  int _pages = 1;
  int _total = 0;
  bool _carregando = true;
  bool _carregandoMais = false;
  String? _erro;

  @override
  void initState() {
    super.initState();
    Future.microtask(_carregar);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _busca.dispose();
    super.dispose();
  }

  /// Lista aberta pelo menu (sem filtro de outro cadastro) segue a escola em foco.
  bool get _segueFoco =>
      widget.meta.filtraEscola &&
      widget.filtros.keys.every((campo) => campo == 'escola_id');

  void _acompanharFoco(EscolaFoco? foco) {
    if (!_segueFoco) return;
    final novo = foco == null ? null : '${foco.id}';
    if (novo == _filtros['escola_id']) return;
    _filtros = {...Map.of(_filtros)..remove('escola_id'), 'escola_id': ?novo};
    _carregar();
  }

  Future<void> _carregar() async {
    setState(() {
      _carregando = true;
      _erro = null;
    });
    try {
      final pagina = await ref
          .read(cadastrosRepositoryProvider)
          .listar(widget.meta.key, busca: _busca.text, filtros: _filtros);
      if (!mounted) return;
      setState(() {
        _registros = pagina.registros;
        _filtrosAtivos = pagina.filtros;
        _page = pagina.page;
        _pages = pagina.pages;
        _total = pagina.total;
        _carregando = false;
      });
      if (widget.meta.filtraEscola) {
        for (final filtro in pagina.filtros) {
          if (filtro.campo == 'escola_id') {
            focarEscola(ref, filtro.valor, filtro.titulo);
          }
        }
      }
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _erro = mensagemDeErro(error, 'Não foi possível carregar.');
        _carregando = false;
      });
    }
  }

  Future<void> _carregarMais() async {
    setState(() => _carregandoMais = true);
    try {
      final pagina = await ref
          .read(cadastrosRepositoryProvider)
          .listar(
            widget.meta.key,
            busca: _busca.text,
            filtros: _filtros,
            page: _page + 1,
          );
      if (!mounted) return;
      setState(() {
        _registros = [..._registros, ...pagina.registros];
        _page = pagina.page;
        _pages = pagina.pages;
      });
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(mensagemDeErro(error))));
      }
    } finally {
      if (mounted) setState(() => _carregandoMais = false);
    }
  }

  void _buscar(String _) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), _carregar);
  }

  Future<void> _abrir(int id) async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) =>
            CadastroDetalheScreen(cadastroKey: widget.meta.key, id: id),
      ),
    );
    await _carregar();
  }

  Future<void> _novo() async {
    final id = await Navigator.of(context).push<int>(
      MaterialPageRoute(
        builder: (_) => CadastroFormScreen(
          cadastroKey: widget.meta.key,
          preencher: _filtros,
        ),
      ),
    );
    if (id == null || !mounted) return;
    await _abrir(id);
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(escolaFocoProvider, (_, foco) => _acompanharFoco(foco));
    final meta = widget.meta;
    return Scaffold(
      drawer: widget.drawer,
      appBar: AppBar(
        title: Text(meta.plural),
        actions: [
          BotaoRedondo(
            icone: Icons.refresh_rounded,
            tooltip: 'Atualizar',
            onPressed: _carregando ? null : _carregar,
          ),
          const SizedBox(width: 12),
        ],
      ),
      floatingActionButton: meta.podeCriar
          ? FloatingActionButton.extended(
              onPressed: _novo,
              icon: const Icon(Icons.add_rounded),
              label: Text('Novo(a) ${meta.singular.toLowerCase()}'),
            )
          : null,
      body: RefreshIndicator(
        onRefresh: _carregar,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 100),
          children: [
            if (meta.busca) ...[
              TextField(
                controller: _busca,
                onChanged: _buscar,
                textInputAction: TextInputAction.search,
                decoration: InputDecoration(
                  hintText: 'Buscar por ${meta.buscaLabel}',
                  prefixIcon: const Icon(Icons.search_rounded),
                  isDense: true,
                  fillColor: AppColors.surface,
                  border: const OutlineInputBorder(
                    borderRadius: BorderRadius.all(Radius.circular(99)),
                    borderSide: BorderSide.none,
                  ),
                  enabledBorder: const OutlineInputBorder(
                    borderRadius: BorderRadius.all(Radius.circular(99)),
                    borderSide: BorderSide.none,
                  ),
                  focusedBorder: const OutlineInputBorder(
                    borderRadius: BorderRadius.all(Radius.circular(99)),
                    borderSide: BorderSide(color: AppColors.mint, width: 1.5),
                  ),
                ),
              ),
              const SizedBox(height: 12),
            ],
            if (_filtrosAtivos.isNotEmpty) ...[
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final filtro in _filtrosAtivos)
                    InputChip(
                      label: Text(
                        '${filtro.label}: ${filtro.titulo}',
                        overflow: TextOverflow.ellipsis,
                      ),
                      onDeleted: () {
                        _filtros = Map.of(_filtros)..remove(filtro.campo);
                        if (filtro.campo == 'escola_id') {
                          limparFocoEscola(ref);
                        }
                        _carregar();
                      },
                    ),
                ],
              ),
              const SizedBox(height: 12),
            ],
            if (!_carregando && _erro == null)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Text(
                  '$_total registro(s)',
                  style: GoogleFonts.figtree(
                    color: AppColors.muted,
                    fontSize: 13,
                  ),
                ),
              ),
            if (_carregando)
              const Padding(
                padding: EdgeInsets.only(top: 48),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_erro != null)
              Padding(
                padding: const EdgeInsets.only(top: 32),
                child: Text(
                  _erro!,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Color(0xFFFFB4B4)),
                ),
              )
            else if (_registros.isEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 40),
                child: Column(
                  children: [
                    Icon(
                      iconeDoCadastro(meta.key),
                      size: 48,
                      color: AppColors.locked,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      meta.podeCriar
                          ? 'Nada por aqui ainda. Toque em "Novo(a) ${meta.singular.toLowerCase()}".'
                          : 'Nenhum registro encontrado.',
                      textAlign: TextAlign.center,
                      style: GoogleFonts.figtree(
                        color: AppColors.muted,
                        height: 1.4,
                      ),
                    ),
                  ],
                ),
              )
            else ...[
              for (final registro in _registros)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: _RegistroCard(
                    meta: meta,
                    registro: registro,
                    onTap: () => _abrir(registro.id),
                  ),
                ),
              if (_page < _pages)
                Center(
                  child: TextButton.icon(
                    onPressed: _carregandoMais ? null : _carregarMais,
                    icon: _carregandoMais
                        ? const SizedBox.square(
                            dimension: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.expand_more),
                    label: const Text('Carregar mais'),
                  ),
                ),
            ],
          ],
        ),
      ),
    );
  }
}

class _RegistroCard extends StatelessWidget {
  const _RegistroCard({
    required this.meta,
    required this.registro,
    required this.onTap,
  });

  static const _pessoas = {'alunos', 'usuarios'};

  final CadastroMeta meta;
  final RegistroResumo registro;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final detalhes = <String>[];
    for (
      var i = 1;
      i < registro.colunas.length && i < meta.colunas.length;
      i++
    ) {
      final valor = registro.colunas[i];
      if (valor != '—' && valor.isNotEmpty) {
        detalhes.add('${meta.colunas[i]}: $valor');
      }
    }
    final principal =
        registro.colunas.isNotEmpty && registro.colunas.first != '—'
        ? registro.colunas.first
        : registro.titulo;
    final cor = AppColors.pastelFor(principal.hashCode);
    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadii.md),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppRadii.md),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 14, 10, 14),
          child: Row(
            children: [
              if (_pessoas.contains(meta.key))
                AvatarIniciais(nome: principal, tamanho: 44, fundo: cor)
              else
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: cor,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(
                    iconeDoCadastro(meta.key),
                    color: AppColors.ink,
                    size: 22,
                  ),
                ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      principal,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.figtree(
                        fontWeight: FontWeight.w800,
                        fontSize: 15.5,
                      ),
                    ),
                    if (detalhes.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text(
                        detalhes.take(4).join('  ·  '),
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                        style: GoogleFonts.figtree(
                          color: AppColors.muted,
                          fontSize: 13,
                          height: 1.35,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const Icon(Icons.chevron_right, color: AppColors.muted),
            ],
          ),
        ),
      ),
    );
  }
}
