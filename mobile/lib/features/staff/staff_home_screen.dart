import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:share_plus/share_plus.dart';

import '../../core/theme/app_theme.dart';
import '../../data/repositories/staff_repository.dart';
import '../../providers/app_providers.dart';
import '../common/brand_and_progress.dart';
import 'criar_sala_screen.dart';

class StaffHomeScreen extends ConsumerStatefulWidget {
  const StaffHomeScreen({super.key});

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

  Future<void> _mostrarCompartilhar(SalaResumo sala) async {
    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: AppColors.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) {
        return Padding(
          padding: const EdgeInsets.fromLTRB(24, 20, 24, 32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Sala criada',
                style: GoogleFonts.figtree(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                sala.questionarioNome,
                style: GoogleFonts.figtree(color: AppColors.muted),
              ),
              const SizedBox(height: 18),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppColors.surfaceRaised,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Column(
                  children: [
                    Text(
                      'Código',
                      style: GoogleFonts.figtree(color: AppColors.muted),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      sala.codigo,
                      style: GoogleFonts.figtree(
                        fontSize: 32,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 4,
                        color: AppColors.mint,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: () async {
                  await Clipboard.setData(ClipboardData(text: sala.link));
                  if (ctx.mounted) {
                    ScaffoldMessenger.of(ctx).showSnackBar(
                      const SnackBar(content: Text('Link copiado.')),
                    );
                  }
                },
                icon: const Icon(Icons.link),
                label: const Text('Copiar link'),
              ),
              const SizedBox(height: 10),
              OutlinedButton.icon(
                onPressed: () {
                  SharePlus.instance.share(
                    ShareParams(
                      text:
                          'Entre na sala VISE-MT com o código ${sala.codigo}\n${sala.link}',
                      subject: 'Sala ${sala.questionarioNome}',
                    ),
                  );
                },
                icon: const Icon(Icons.ios_share),
                label: const Text('Compartilhar'),
              ),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(appControllerProvider).staffUser;
    return Scaffold(
      appBar: AppBar(
        title: const Text('SALAS'),
        actions: [
          IconButton(
            tooltip: 'Atualizar',
            onPressed: _loading ? null : _carregar,
            icon: const Icon(Icons.refresh_rounded),
          ),
          IconButton(
            tooltip: 'Sair',
            onPressed: () => ref.read(appControllerProvider).sair(),
            icon: const Icon(Icons.logout_rounded),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _novaSala,
        icon: const Icon(Icons.add),
        label: const Text('Nova sala'),
      ),
      body: RefreshIndicator(
        onRefresh: _carregar,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 100),
          children: [
            const ViseBrandMark(compact: true),
            const SizedBox(height: 16),
            if (user != null) ...[
              Text(
                user.name,
                style: GoogleFonts.figtree(
                  fontSize: 24,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                user.escolas.isEmpty
                    ? user.email
                    : '${user.escolas.first.roleLabel} · ${user.escolas.first.nome}',
                style: GoogleFonts.figtree(color: AppColors.muted),
              ),
              const SizedBox(height: 22),
            ],
            if (_loading)
              const Padding(
                padding: EdgeInsets.only(top: 48),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_erro != null)
              Text(_erro!, style: GoogleFonts.figtree(color: Colors.redAccent))
            else if (_salas.isEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 40),
                child: Text(
                  'Nenhuma sala ainda. Crie uma e envie o link para a turma.',
                  textAlign: TextAlign.center,
                  style: GoogleFonts.figtree(color: AppColors.muted, height: 1.4),
                ),
              )
            else
              ..._salas.map(
                (sala) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _SalaCard(
                    sala: sala,
                    onShare: () => _mostrarCompartilhar(sala),
                    onEncerrar: sala.status == 'ativa'
                        ? () async {
                            await ref
                                .read(staffRepositoryProvider)
                                .encerrarSala(sala.id);
                            await _carregar();
                          }
                        : null,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _SalaCard extends StatelessWidget {
  const _SalaCard({
    required this.sala,
    required this.onShare,
    this.onEncerrar,
  });

  final SalaResumo sala;
  final VoidCallback onShare;
  final VoidCallback? onEncerrar;

  @override
  Widget build(BuildContext context) {
    final ativa = sala.status == 'ativa';
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surfaceRaised,
        borderRadius: BorderRadius.circular(AppRadii.md),
        border: Border.all(color: AppColors.border.withValues(alpha: 0.5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  sala.questionarioNome,
                  style: GoogleFonts.figtree(
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: ativa ? AppColors.mintSoft : AppColors.locked,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  ativa ? 'Ativa' : 'Encerrada',
                  style: GoogleFonts.figtree(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: ativa ? AppColors.mint : AppColors.muted,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            [
              if (sala.turmaNome != null) 'Turma ${sala.turmaNome}',
              if (sala.alvoTipo == 'escola') 'Toda a escola',
              'Código ${sala.codigo}',
            ].join(' · '),
            style: GoogleFonts.figtree(color: AppColors.muted, fontSize: 13),
          ),
          const SizedBox(height: 8),
          Text(
            '${sala.respondentes} respondendo · ${sala.concluidos} concluíram',
            style: GoogleFonts.figtree(
              color: AppColors.muted,
              fontSize: 13,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              TextButton.icon(
                onPressed: onShare,
                icon: const Icon(Icons.share_outlined, size: 18),
                label: const Text('Link'),
              ),
              if (onEncerrar != null)
                TextButton(
                  onPressed: onEncerrar,
                  child: const Text('Encerrar'),
                ),
            ],
          ),
        ],
      ),
    );
  }
}
