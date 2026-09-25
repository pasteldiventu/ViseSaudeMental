import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/theme/app_theme.dart';
import '../../data/repositories/cadastros_repository.dart';
import '../../providers/app_providers.dart';
import '../common/brand_and_progress.dart';
import 'cadastros/cadastro_icons.dart';
import 'cadastros/cadastro_lista_screen.dart';
import 'staff_home_screen.dart';

/// Área da equipe: salas + cadastros liberados para o perfil.
/// Celular: menu em gaveta. Tablet/web largo: menu lateral fixo.
class StaffShell extends ConsumerStatefulWidget {
  const StaffShell({super.key});

  @override
  ConsumerState<StaffShell> createState() => _StaffShellState();
}

class _StaffShellState extends ConsumerState<StaffShell> {
  static const _salas = 'salas';
  String _secao = _salas;

  void _abrir(String secao) => setState(() => _secao = secao);

  @override
  Widget build(BuildContext context) {
    final menu = ref.watch(cadastrosMenuProvider);
    final largo = MediaQuery.sizeOf(context).width >= 900;
    final meta = menu.value?.cadastros[_secao];
    final secao = meta == null ? _salas : _secao;

    final menuLateral = _MenuLateral(
      menu: menu,
      selecionado: secao,
      onSelecionar: _abrir,
      onRecarregar: () => ref.invalidate(cadastrosMenuProvider),
    );
    final gaveta = largo ? null : Drawer(backgroundColor: AppColors.surface, child: menuLateral);

    final conteudo = secao == _salas
        ? StaffHomeScreen(drawer: gaveta, onAbrirCadastro: _abrir)
        : CadastroListaScreen(key: ValueKey(secao), meta: meta!, drawer: gaveta);

    if (!largo) return conteudo;
    return Row(
      children: [
        SizedBox(
          width: 290,
          child: Material(color: AppColors.surface, child: SafeArea(child: menuLateral)),
        ),
        const VerticalDivider(width: 1, color: AppColors.border),
        Expanded(child: conteudo),
      ],
    );
  }
}

class _MenuLateral extends ConsumerWidget {
  const _MenuLateral({
    required this.menu,
    required this.selecionado,
    required this.onSelecionar,
    required this.onRecarregar,
  });

  final AsyncValue<MenuCadastros> menu;
  final String selecionado;
  final ValueChanged<String> onSelecionar;
  final VoidCallback onRecarregar;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(appControllerProvider).staffUser;
    void selecionar(String key) {
      Scaffold.maybeOf(context)?.closeDrawer();
      onSelecionar(key);
    }

    return ListView(
      padding: const EdgeInsets.fromLTRB(12, 20, 12, 24),
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8),
          child: Row(
            children: [
              const ViseBrandMark(compact: true),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      user?.name ?? '',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.figtree(fontWeight: FontWeight.w800, fontSize: 16),
                    ),
                    Text(
                      user == null
                          ? ''
                          : user.isSuperuser
                          ? 'Admin geral'
                          : user.escolas.isEmpty
                          ? user.email
                          : user.escolas.map((e) => e.roleLabel).toSet().join(' · '),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.figtree(color: AppColors.muted, fontSize: 12.5),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        _Item(
          icone: Icons.meeting_room_outlined,
          label: 'Salas',
          selecionado: selecionado == 'salas',
          onTap: () => selecionar('salas'),
        ),
        ...menu.when(
          data: (dados) => [
            for (final grupo in dados.grupos) ...[
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 18, 12, 6),
                child: Text(
                  grupo.grupo.toUpperCase(),
                  style: GoogleFonts.figtree(
                    color: AppColors.muted,
                    fontSize: 11.5,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 1.1,
                  ),
                ),
              ),
              for (final item in grupo.itens)
                _Item(
                  icone: iconeDoCadastro(item.key),
                  label: item.plural,
                  selecionado: selecionado == item.key,
                  onTap: () => selecionar(item.key),
                ),
            ],
          ],
          loading: () => const [
            Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: CircularProgressIndicator()),
            ),
          ],
          error: (error, _) => [
            Padding(
              padding: const EdgeInsets.all(12),
              child: Text(
                mensagemDeErro(error, 'Não foi possível carregar o menu.'),
                style: GoogleFonts.figtree(color: AppColors.muted),
              ),
            ),
            TextButton(onPressed: onRecarregar, child: const Text('Tentar de novo')),
          ],
        ),
        const SizedBox(height: 18),
        const Divider(color: AppColors.border),
        _Item(
          icone: Icons.logout_rounded,
          label: 'Sair',
          selecionado: false,
          onTap: () => ref.read(appControllerProvider).sair(),
        ),
      ],
    );
  }
}

class _Item extends StatelessWidget {
  const _Item({
    required this.icone,
    required this.label,
    required this.selecionado,
    required this.onTap,
  });

  final IconData icone;
  final String label;
  final bool selecionado;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 2),
      child: ListTile(
        dense: true,
        selected: selecionado,
        selectedColor: AppColors.mint,
        selectedTileColor: AppColors.mintSoft,
        iconColor: AppColors.muted,
        textColor: AppColors.text,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadii.sm)),
        leading: Icon(icone, size: 22),
        title: Text(label, style: GoogleFonts.figtree(fontWeight: FontWeight.w700, fontSize: 14.5)),
        onTap: onTap,
      ),
    );
  }
}
