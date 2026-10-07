import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/theme/app_theme.dart';
import '../../data/repositories/staff_repository.dart';
import '../../providers/app_providers.dart';
import '../common/ui_kit.dart';
import 'cadastros/cadastro_icons.dart';
import 'compartilhar_sala.dart';
import 'criar_sala_screen.dart';

class StaffHomeScreen extends ConsumerStatefulWidget {
  const StaffHomeScreen({super.key, this.drawer, this.onAbrirCadastro});

  final Widget? drawer;
  final ValueChanged<String>? onAbrirCadastro;

  @override
  ConsumerState<StaffHomeScreen> createState() => _StaffHomeScreenState();
}

class _StaffHomeScreenState extends ConsumerState<StaffHomeScreen> {
  List<SalaResumo> _salas = const [];
  bool _loading = true;
  String? _erro;

  @override
  void initState() {
    super.initState();
    Future.microtask(_carregar);
  }

  Future<void> _carregar() async {
    setState(() {
      _loading = true;
      _erro = null;
    });
    try {
      final salas = await ref.read(staffRepositoryProvider).listarSalas();
      if (!mounted) return;
      setState(() {
        _salas = salas;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _erro = 'Não foi possível carregar as salas.';
        _loading = false;
      });
    }
  }

  Future<void> _novaSala() async {
    final criada = await Navigator.of(context).push<SalaResumo>(
      MaterialPageRoute(builder: (_) => const CriarSalaScreen()),
    );
    if (criada != null) {
      await _carregar();
      if (!mounted) return;
      await _mostrarCompartilhar(criada);
    }
  }

  Future<void> _mostrarCompartilhar(
    SalaResumo sala, {
    String titulo = 'Sala criada',
  }) => mostrarCompartilharSala(context, sala, titulo: titulo);

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(appControllerProvider).staffUser;
    final menu = ref.watch(cadastrosMenuProvider).value;
    final atalhos = [
      for (final key in const [
        'questionarios',
        'aplicacoes',
        'resultados',
        'turmas',
        'alunos',
        'usuarios',
      ])
        ?menu?.cadastros[key],
    ];
    final ativas = _salas.where((s) => s.status == 'ativa').toList();
    final concluidos = ativas.fold<int>(0, (s, sala) => s + sala.concluidos);
    final abrir = widget.onAbrirCadastro;
    return Scaffold(
      drawer: widget.drawer,
      appBar: widget.drawer == null ? null : AppBar(toolbarHeight: 52),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _novaSala,
        icon: const Icon(Icons.add_rounded),
        label: const Text('Nova sala'),
      ),
      body: SafeArea(
        top: widget.drawer == null,
        child: RefreshIndicator(
          onRefresh: _carregar,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 100),
            children: [
              if (user != null) ...[
                SaudacaoHeader(
                  nome: user.name,
                  subtitulo: user.escolas.isEmpty
                      ? user.email
                      : '${user.escolas.first.roleLabel} · ${user.escolas.first.nome}',
                  acoes: [
                    BotaoRedondo(
                      icone: Icons.refresh_rounded,
                      tooltip: 'Atualizar',
                      onPressed: _loading ? null : _carregar,
                    ),
                    BotaoRedondo(
                      icone: Icons.logout_rounded,
                      tooltip: 'Sair',
                      onPressed: () => ref.read(appControllerProvider).sair(),
                    ),
                  ],
                ),
                const SizedBox(height: 20),
              ],
              HeroCard(
                onTap: abrir == null ? null : () => abrir('painel'),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      abrir == null ? 'Suas salas' : 'Painel de indicadores',
                      style: GoogleFonts.figtree(
                        fontWeight: FontWeight.w800,
                        fontSize: 15,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(
                          _loading ? '–' : '${ativas.length}',
                          style: GoogleFonts.figtree(
                            fontSize: 46,
                            fontWeight: FontWeight.w800,
                            height: 1,
                            letterSpacing: -1.5,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Padding(
                            padding: const EdgeInsets.only(bottom: 5),
                            child: Text(
                              'sala(s) ativa(s) · $concluidos aluno(s) já concluíram',
                              style: GoogleFonts.figtree(
                                fontWeight: FontWeight.w600,
                                color: AppColors.ink.withValues(alpha: 0.75),
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                    if (abrir != null) ...[
                      const SizedBox(height: 16),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          _BotaoHero(
                            icone: Icons.insights_rounded,
                            rotulo: 'Ver painel',
                            solido: true,
                            onTap: () => abrir('painel'),
                          ),
                          _BotaoHero(
                            icone: Icons.summarize_outlined,
                            rotulo: 'Relatórios',
                            onTap: () => abrir('relatorios'),
                          ),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 24),
              if (atalhos.isNotEmpty && abrir != null) ...[
                const SecaoTitulo('Gestão'),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final meta in atalhos)
                      ActionChip(
                        avatar: Icon(
                          iconeDoCadastro(meta.key),
                          size: 18,
                          color: AppColors.mint,
                        ),
                        label: Text(meta.plural),
                        onPressed: () => abrir(meta.key),
                      ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  'Todos os cadastros ficam no menu ☰.',
                  style: GoogleFonts.figtree(
                    color: AppColors.muted,
                    fontSize: 12.5,
                  ),
                ),
                const SizedBox(height: 24),
              ],
              SecaoTitulo(
                'Suas salas',
                detalhe: _loading || _salas.isEmpty ? null : '${_salas.length}',
              ),
              if (_loading)
                const Padding(
                  padding: EdgeInsets.only(top: 48),
                  child: Center(child: CircularProgressIndicator()),
                )
              else if (_erro != null)
                Text(
                  _erro!,
                  style: GoogleFonts.figtree(color: Colors.redAccent),
                )
              else if (_salas.isEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 40),
                  child: Text(
                    'Nenhuma sala ainda. Crie uma e envie o link para a turma.',
                    textAlign: TextAlign.center,
                    style: GoogleFonts.figtree(
                      color: AppColors.muted,
                      height: 1.4,
                    ),
                  ),
                )
              else
                for (var i = 0; i < _salas.length; i++)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _SalaCard(
                      sala: _salas[i],
                      cor: AppColors.pastelFor(i),
                      onShare: () => _mostrarCompartilhar(
                        _salas[i],
                        titulo: 'Enviar link da sala',
                      ),
                      onEncerrar: _salas[i].status == 'ativa'
                          ? () async {
                              await ref
                                  .read(staffRepositoryProvider)
                                  .encerrarSala(_salas[i].id);
                              await _carregar();
                            }
                          : null,
                    ),
                  ),
            ],
          ),
        ),
      ),
    );
  }
}

class _BotaoHero extends StatelessWidget {
  const _BotaoHero({
    required this.icone,
    required this.rotulo,
    required this.onTap,
    this.solido = false,
  });

  final IconData icone;
  final String rotulo;
  final VoidCallback onTap;
  final bool solido;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: solido ? AppColors.ink : Colors.white.withValues(alpha: 0.22),
      shape: const StadiumBorder(),
      child: InkWell(
        customBorder: const StadiumBorder(),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                icone,
                size: 18,
                color: solido ? AppColors.mint : AppColors.ink,
              ),
              const SizedBox(width: 8),
              Text(
                rotulo,
                style: GoogleFonts.figtree(
                  fontWeight: FontWeight.w800,
                  color: solido ? AppColors.text : AppColors.ink,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SalaCard extends StatelessWidget {
  const _SalaCard({
    required this.sala,
    required this.cor,
    required this.onShare,
    this.onEncerrar,
  });

  final SalaResumo sala;
  final Color cor;
  final VoidCallback onShare;
  final VoidCallback? onEncerrar;

  @override
  Widget build(BuildContext context) {
    final ativa = sala.status == 'ativa';
    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadii.lg),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 16, 12, 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: ativa ? cor : AppColors.locked,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Icon(
                    Icons.meeting_room_outlined,
                    color: ativa ? AppColors.ink : AppColors.muted,
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        sala.questionarioNome,
                        style: GoogleFonts.figtree(
                          fontWeight: FontWeight.w800,
                          fontSize: 16,
                          height: 1.2,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        [
                          if (sala.turmaNome != null) 'Turma ${sala.turmaNome}',
                          if (sala.alvoTipo == 'escola') 'Toda a escola',
                        ].join(' · '),
                        style: GoogleFonts.figtree(
                          color: AppColors.muted,
                          fontSize: 13,
                        ),
                      ),
                    ],
                  ),
                ),
                Selo(
                  ativa ? 'Ativa' : 'Encerrada',
                  cor: ativa ? AppColors.mint : AppColors.muted,
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceRaised,
                    borderRadius: BorderRadius.circular(99),
                  ),
                  child: Text(
                    sala.codigo,
                    style: GoogleFonts.figtree(
                      color: AppColors.mint,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 2,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    '${sala.respondentes} respondendo · ${sala.concluidos} concluíram',
                    style: GoogleFonts.figtree(
                      color: AppColors.muted,
                      fontSize: 12.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
                BotaoRedondo(
                  icone: Icons.ios_share_rounded,
                  tooltip: 'Compartilhar link',
                  onPressed: onShare,
                ),
                if (onEncerrar != null) ...[
                  const SizedBox(width: 6),
                  BotaoRedondo(
                    icone: Icons.stop_circle_outlined,
                    tooltip: 'Encerrar sala',
                    onPressed: onEncerrar,
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}
