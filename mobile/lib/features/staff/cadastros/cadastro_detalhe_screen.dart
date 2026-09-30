import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/theme/app_theme.dart';
import '../../../data/repositories/cadastros_repository.dart';
import '../../../providers/app_providers.dart';
import 'cadastro_form_screen.dart';
import 'cadastro_icons.dart';
import 'cadastro_lista_screen.dart';

class CadastroDetalheScreen extends ConsumerStatefulWidget {
  const CadastroDetalheScreen({
    super.key,
    required this.cadastroKey,
    required this.id,
  });

  final String cadastroKey;
  final int id;

  @override
  ConsumerState<CadastroDetalheScreen> createState() =>
      _CadastroDetalheScreenState();
}

class _CadastroDetalheScreenState extends ConsumerState<CadastroDetalheScreen> {
  RegistroDetalhe? _registro;
  String? _erro;
  bool _ocupado = false;

  CadastrosRepository get _repo => ref.read(cadastrosRepositoryProvider);

  @override
  void initState() {
    super.initState();
    Future.microtask(_carregar);
  }

  Future<void> _carregar() async {
    try {
      final registro = await _repo.detalhe(widget.cadastroKey, widget.id);
      if (!mounted) return;
      setState(() {
        _registro = registro;
        _erro = null;
      });
    } catch (error) {
      if (!mounted) return;
      setState(
        () => _erro = mensagemDeErro(
          error,
          'Não foi possível carregar o registro.',
        ),
      );
    }
  }

  void _avisar(String mensagem) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(mensagem)));
  }

  Future<bool> _confirmar(String titulo, String? texto) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppColors.surface,
        title: Text(titulo),
        content: texto == null ? null : Text(texto),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancelar'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Confirmar'),
          ),
        ],
      ),
    );
    return ok == true;
  }

  Future<void> _editar() async {
    final salvo = await Navigator.of(context).push<int>(
      MaterialPageRoute(
        builder: (_) =>
            CadastroFormScreen(cadastroKey: widget.cadastroKey, id: widget.id),
      ),
    );
    if (salvo != null) await _carregar();
  }

  Future<void> _excluir() async {
    final registro = _registro!;
    if (!await _confirmar(
      'Excluir ${registro.singular.toLowerCase()}?',
      registro.titulo,
    )) {
      return;
    }
    setState(() => _ocupado = true);
    try {
      final mensagem = await _repo.excluir(widget.cadastroKey, widget.id);
      if (!mounted) return;
      _avisar(mensagem);
      Navigator.of(context).pop(true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _ocupado = false);
      _avisar(mensagemDeErro(error, 'Não foi possível excluir.'));
    }
  }

  Future<void> _acao(AcaoMeta acao) async {
    if (!await _confirmar(acao.label, acao.confirmar)) return;
    setState(() => _ocupado = true);
    try {
      final mensagem = await _repo.executarAcao(widget.cadastroKey, acao.nome, [
        widget.id,
      ]);
      if (!mounted) return;
      _avisar(mensagem);
      await _carregar();
    } catch (error) {
      if (mounted) _avisar(mensagemDeErro(error));
    } finally {
      if (mounted) setState(() => _ocupado = false);
    }
  }

  Future<void> _abrirLista(Relacionado rel) async {
    final menu = await ref.read(cadastrosMenuProvider.future);
    final meta = menu.cadastros[rel.key];
    if (meta == null || !mounted) return;
    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => CadastroListaScreen(
          meta: meta,
          filtros: {rel.field: '${widget.id}'},
        ),
      ),
    );
    await _carregar();
  }

  Future<void> _adicionar(Relacionado rel) async {
    final novoId = await Navigator.of(context).push<int>(
      MaterialPageRoute(
        builder: (_) => CadastroFormScreen(
          cadastroKey: rel.key,
          preencher: {rel.field: '${widget.id}'},
        ),
      ),
    );
    if (novoId == null || !mounted) return;
    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => CadastroDetalheScreen(cadastroKey: rel.key, id: novoId),
      ),
    );
    await _carregar();
  }

  @override
  Widget build(BuildContext context) {
    final registro = _registro;
    return Scaffold(
      appBar: AppBar(
        title: Text(registro?.singular ?? ''),
        actions: [
          if (registro?.podeEditar == true) ...[
            IconButton(
              tooltip: 'Editar',
              onPressed: _ocupado ? null : _editar,
              icon: const Icon(Icons.edit_outlined),
            ),
            IconButton(
              tooltip: 'Excluir',
              onPressed: _ocupado ? null : _excluir,
              icon: const Icon(Icons.delete_outline),
            ),
          ],
        ],
      ),
      body: registro == null
          ? Center(
              child: _erro == null
                  ? const CircularProgressIndicator()
                  : Padding(
                      padding: const EdgeInsets.all(24),
                      child: Text(_erro!, textAlign: TextAlign.center),
                    ),
            )
          : RefreshIndicator(
              onRefresh: _carregar,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(20, 4, 20, 40),
                children: [
                  Text(
                    registro.titulo,
                    style: GoogleFonts.figtree(
                      fontSize: 24,
                      fontWeight: FontWeight.w800,
                      height: 1.2,
                    ),
                  ),
                  if (registro.acoes.isNotEmpty) ...[
                    const SizedBox(height: 14),
                    Wrap(
                      spacing: 10,
                      runSpacing: 10,
                      children: [
                        for (final acao in registro.acoes)
                          FilledButton.tonal(
                            style: FilledButton.styleFrom(
                              minimumSize: const Size(0, 44),
                              backgroundColor: AppColors.mintSoft,
                              foregroundColor: AppColors.mint,
                            ),
                            onPressed: _ocupado ? null : () => _acao(acao),
                            child: Text(acao.label),
                          ),
                      ],
                    ),
                  ],
                  if (registro.relacionados.isNotEmpty) ...[
                    const SizedBox(height: 24),
                    _Titulo('Conteúdo'),
                    for (final (i, rel) in registro.relacionados.indexed)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: Material(
                          color: AppColors.surface,
                          borderRadius: BorderRadius.circular(AppRadii.md),
                          child: ListTile(
                            contentPadding: const EdgeInsets.fromLTRB(
                              12,
                              6,
                              8,
                              6,
                            ),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(AppRadii.md),
                            ),
                            leading: Container(
                              width: 44,
                              height: 44,
                              decoration: BoxDecoration(
                                color: AppColors.pastelFor(i),
                                borderRadius: BorderRadius.circular(14),
                              ),
                              child: Icon(
                                iconeDoCadastro(rel.key),
                                color: AppColors.ink,
                                size: 22,
                              ),
                            ),
                            title: Text(
                              rel.label,
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            subtitle: Text('${rel.count} cadastrado(s)'),
                            onTap: () => _abrirLista(rel),
                            trailing: rel.canAdd
                                ? IconButton(
                                    tooltip: 'Adicionar',
                                    icon: const Icon(
                                      Icons.add_circle_outline,
                                      color: AppColors.mint,
                                    ),
                                    onPressed: () => _adicionar(rel),
                                  )
                                : const Icon(Icons.chevron_right),
                          ),
                        ),
                      ),
                  ],
                  const SizedBox(height: 24),
                  _Titulo('Dados'),
                  Container(
                    decoration: BoxDecoration(
                      color: AppColors.surface,
                      borderRadius: BorderRadius.circular(AppRadii.md),
                    ),
                    child: Column(
                      children: [
                        for (final campo in registro.campos)
                          _CampoLinha(campo: campo),
                      ],
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}

class _Titulo extends StatelessWidget {
  const _Titulo(this.texto);

  final String texto;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: Text(
      texto,
      style: GoogleFonts.figtree(
        fontSize: 18,
        fontWeight: FontWeight.w800,
        letterSpacing: -0.3,
      ),
    ),
  );
}

class _CampoLinha extends StatelessWidget {
  const _CampoLinha({required this.campo});

  final CampoDetalhe campo;

  @override
  Widget build(BuildContext context) {
    final pares = campo.pares;
    final linkKey = campo.linkKey;
    final linkId = campo.linkId;
    final valor = pares != null
        ? Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              for (final par in pares.entries)
                Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Text.rich(
                    TextSpan(
                      children: [
                        TextSpan(
                          text: '${par.key}: ',
                          style: const TextStyle(color: AppColors.muted),
                        ),
                        TextSpan(
                          text: par.value,
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          )
        : Text(
            campo.valor,
            style: TextStyle(
              fontWeight: FontWeight.w600,
              color: linkKey != null ? AppColors.mint : AppColors.text,
            ),
          );
    return InkWell(
      onTap: linkKey == null || linkId == null
          ? null
          : () => Navigator.of(context).push<void>(
              MaterialPageRoute(
                builder: (_) =>
                    CadastroDetalheScreen(cadastroKey: linkKey, id: linkId),
              ),
            ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              campo.label,
              style: GoogleFonts.figtree(
                color: AppColors.muted,
                fontSize: 12.5,
              ),
            ),
            const SizedBox(height: 3),
            SizedBox(width: double.infinity, child: valor),
          ],
        ),
      ),
    );
  }
}
