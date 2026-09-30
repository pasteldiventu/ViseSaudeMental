import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../../core/files/entregar_arquivo.dart';
import '../../../core/theme/app_theme.dart';
import '../../../data/repositories/cadastros_repository.dart';
import '../../../data/repositories/painel_repository.dart';
import '../../../providers/app_providers.dart';
import 'painel_widgets.dart';

/// Importação de escolas, turmas, alunos e equipe por planilha (.xlsx ou .csv).
class ImportarScreen extends ConsumerStatefulWidget {
  const ImportarScreen({super.key, this.drawer});

  final Widget? drawer;

  @override
  ConsumerState<ImportarScreen> createState() => _ImportarScreenState();
}

class _ImportarScreenState extends ConsumerState<ImportarScreen> {
  String? _tipo;
  ({Uint8List bytes, String nome})? _arquivo;
  ResultadoImportacao? _resultado;
  bool _enviando = false;
  bool _baixandoModelo = false;

  void _mostrar(String mensagem) {
    if (mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(mensagem)));
    }
  }

  Future<void> _baixarModelo(String tipo) async {
    setState(() => _baixandoModelo = true);
    try {
      final arquivo = await ref.read(painelRepositoryProvider).modelo(tipo);
      await entregarArquivo(arquivo.bytes, arquivo.nome);
    } catch (error) {
      _mostrar(mensagemDeErro(error, 'Não foi possível baixar o modelo.'));
    } finally {
      if (mounted) setState(() => _baixandoModelo = false);
    }
  }

  Future<void> _escolherArquivo() async {
    final escolhido = await FilePicker.pickFile(
      type: FileType.custom,
      allowedExtensions: const ['xlsx', 'csv'],
    );
    if (escolhido == null) return;
    final bytes = await escolhido.readAsBytes();
    setState(() {
      _arquivo = (bytes: bytes, nome: escolhido.name);
      _resultado = null;
    });
  }

  Future<void> _enviar(String tipo, {required bool simular}) async {
    final arquivo = _arquivo;
    if (arquivo == null) return;
    if (!simular) {
      final ok = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Gravar a importação?'),
          content: Text(
            'Os registros de "${arquivo.nome}" serão criados ou atualizados. Linhas com erro são ignoradas.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancelar'),
            ),
            TextButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Importar'),
            ),
          ],
        ),
      );
      if (ok != true) return;
    }
    setState(() => _enviando = true);
    try {
      final resultado = await ref
          .read(painelRepositoryProvider)
          .importar(tipo, arquivo.bytes, arquivo.nome, simular: simular);
      if (!mounted) return;
      setState(() => _resultado = resultado);
      if (!simular) ref.invalidate(opcoesRelatorioProvider);
    } catch (error) {
      _mostrar(mensagemDeErro(error, 'Não foi possível importar a planilha.'));
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final tipos = ref.watch(tiposImportacaoProvider);
    return Scaffold(
      drawer: widget.drawer,
      appBar: AppBar(title: const Text('Importar planilha')),
      body: tipos.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Text(mensagemDeErro(e, 'Não foi possível carregar.')),
        ),
        data: (lista) {
          if (lista.isEmpty) {
            return const Padding(
              padding: EdgeInsets.all(24),
              child: Vazio('Seu perfil não pode importar planilhas.'),
            );
          }
          final tipo = lista.firstWhere(
            (t) => t.tipo == _tipo,
            orElse: () => lista.first,
          );
          return _conteudo(lista, tipo);
        },
      ),
    );
  }

  Widget _conteudo(List<TipoImportacao> lista, TipoImportacao tipo) {
    final arquivo = _arquivo;
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 40),
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final t in lista)
              ChoiceChip(
                label: Text(t.label),
                selected: t.tipo == tipo.tipo,
                onSelected: (_) => setState(() {
                  _tipo = t.tipo;
                  _resultado = null;
                }),
              ),
          ],
        ),
        const SizedBox(height: 14),
        CartaoSecao(
          titulo: 'Importar ${tipo.label.toLowerCase()}',
          subtitulo: tipo.descricao,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _Passo(
                numero: 1,
                texto:
                    'Baixe a planilha modelo (já traz instruções e as escolas/turmas que você pode usar).',
                child: OutlinedButton.icon(
                  onPressed: _baixandoModelo
                      ? null
                      : () => _baixarModelo(tipo.tipo),
                  icon: const Icon(Icons.description_outlined),
                  label: Text(_baixandoModelo ? 'Baixando…' : 'Baixar modelo'),
                ),
              ),
              _Passo(
                numero: 2,
                texto:
                    'Preencha uma linha por registro. Planilhas próprias também servem: os nomes das colunas são reconhecidos.',
                child: OutlinedButton.icon(
                  onPressed: _enviando ? null : _escolherArquivo,
                  icon: const Icon(Icons.upload_file_rounded),
                  label: Text(
                    arquivo == null
                        ? 'Escolher planilha (.xlsx ou .csv)'
                        : arquivo.nome,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ),
              _Passo(
                numero: 3,
                texto:
                    'Simule para ver os erros sem gravar nada. Depois, importe.',
                child: Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: arquivo == null || _enviando
                            ? null
                            : () => _enviar(tipo.tipo, simular: true),
                        child: const Text('Simular'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: FilledButton(
                        style: FilledButton.styleFrom(
                          minimumSize: const Size.fromHeight(44),
                        ),
                        onPressed: arquivo == null || _enviando
                            ? null
                            : () => _enviar(tipo.tipo, simular: false),
                        child: const Text('Importar'),
                      ),
                    ),
                  ],
                ),
              ),
              if (_enviando) const LinearProgressIndicator(minHeight: 3),
            ],
          ),
        ),
        if (_resultado != null) _ResultadoCard(resultado: _resultado!),
        CartaoSecao(
          titulo: 'Colunas aceitas',
          subtitulo: '* obrigatória · a ordem das colunas não importa',
          child: Column(
            children: [
              for (final c in tipo.colunas)
                ListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  title: Text(
                    '${c.label}${c.obrigatoria ? ' *' : ''}',
                    style: GoogleFonts.figtree(fontWeight: FontWeight.w700),
                  ),
                  subtitle: Text(
                    [
                      if (c.ajuda != null) c.ajuda!,
                      if (c.exemplo != null) 'Ex.: ${c.exemplo}',
                    ].join('\n'),
                    style: GoogleFonts.figtree(
                      color: AppColors.muted,
                      fontSize: 12.5,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class _Passo extends StatelessWidget {
  const _Passo({
    required this.numero,
    required this.texto,
    required this.child,
  });

  final int numero;
  final String texto;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CircleAvatar(
            radius: 13,
            backgroundColor: AppColors.mintSoft,
            child: Text(
              '$numero',
              style: GoogleFonts.figtree(
                color: AppColors.mint,
                fontWeight: FontWeight.w800,
                fontSize: 13,
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(texto, style: GoogleFonts.figtree(fontSize: 13.5)),
                const SizedBox(height: 8),
                child,
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ResultadoCard extends StatelessWidget {
  const _ResultadoCard({required this.resultado});

  final ResultadoImportacao resultado;

  @override
  Widget build(BuildContext context) {
    final r = resultado;
    final ok = r.criados + r.atualizados;
    final cor = r.erros == 0
        ? AppColors.mint
        : (ok > 0 ? AppColors.warn : corAlto);
    Widget numero(String rotulo, int valor, Color cor) => Expanded(
      child: Column(
        children: [
          Text(
            '$valor',
            style: GoogleFonts.figtree(
              fontSize: 24,
              fontWeight: FontWeight.w800,
              color: cor,
            ),
          ),
          Text(
            rotulo,
            style: GoogleFonts.figtree(color: AppColors.muted, fontSize: 12),
          ),
        ],
      ),
    );
    return CartaoSecao(
      titulo: r.simulacao
          ? 'Simulação (nada foi gravado)'
          : 'Importação concluída',
      subtitulo: '${r.total} linha(s) lidas · ${r.formato.toUpperCase()}',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              numero(
                r.simulacao ? 'seriam criados' : 'criados',
                r.criados,
                AppColors.mint,
              ),
              numero(
                r.simulacao ? 'seriam atualizados' : 'atualizados',
                r.atualizados,
                AppColors.text,
              ),
              numero(
                'com erro',
                r.erros,
                r.erros > 0 ? corAlto : AppColors.muted,
              ),
            ],
          ),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: cor.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(AppRadii.sm),
            ),
            child: Text(
              r.simulacao && r.erros == 0 && ok > 0
                  ? 'Tudo certo: toque em Importar para gravar.'
                  : r.erros > 0
                  ? 'Corrija as linhas abaixo na planilha e envie de novo (as linhas certas podem ser reenviadas sem duplicar).'
                  : 'Registros gravados.',
              style: GoogleFonts.figtree(fontSize: 13),
            ),
          ),
          if (r.colunasIgnoradas.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              'Colunas não reconhecidas (ignoradas): ${r.colunasIgnoradas.join(', ')}',
              style: GoogleFonts.figtree(color: AppColors.warn, fontSize: 12.5),
            ),
          ],
          const SizedBox(height: 8),
          for (final l in r.linhas.take(200))
            ListTile(
              dense: true,
              contentPadding: EdgeInsets.zero,
              leading: Icon(
                l.status == 'erro'
                    ? Icons.error_outline_rounded
                    : Icons.check_circle_outline_rounded,
                color: l.status == 'erro' ? corAlto : AppColors.mint,
              ),
              title: Text(
                'Linha ${l.linha}',
                style: GoogleFonts.figtree(fontWeight: FontWeight.w700),
              ),
              subtitle: Text(
                l.mensagem,
                style: GoogleFonts.figtree(
                  color: AppColors.muted,
                  fontSize: 12.5,
                ),
              ),
            ),
          if (r.linhas.length > 200 || r.linhasTruncadas)
            Text(
              'Exibindo as primeiras linhas (erros primeiro).',
              style: GoogleFonts.figtree(color: AppColors.muted, fontSize: 12),
            ),
        ],
      ),
    );
  }
}
