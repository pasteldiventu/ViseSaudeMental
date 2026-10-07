import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';

import '../../core/theme/app_theme.dart';
import '../../providers/app_providers.dart';
import '../common/avatar_bubble.dart';
import '../common/brand_and_progress.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _cpf = TextEditingController();
  final _birthDate = TextEditingController();
  DateTime? _nascimento;

  @override
  void initState() {
    super.initState();
    ref.read(appControllerProvider).limparErro();
  }

  @override
  void dispose() {
    _cpf.dispose();
    _birthDate.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final nascimento = _nascimento;
    if (nascimento == null) return;
    final success = await ref
        .read(appControllerProvider)
        .entrar(_cpf.text, DateFormat('yyyy-MM-dd').format(nascimento));
    if (!mounted) return;
    if (success) {
      Navigator.of(context).popUntil((route) => route.isFirst);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            ref.read(appControllerProvider).erro ??
                'É necessária uma conexão no primeiro acesso.',
          ),
        ),
      );
    }
  }

  Future<void> _pickDate() async {
    final date = await showDatePicker(
      context: context,
      initialDate: _nascimento ?? DateTime(2009, 10, 21),
      firstDate: DateTime(1990),
      lastDate: DateTime.now(),
      locale: const Locale('pt', 'BR'),
    );
    if (date != null) {
      setState(() {
        _nascimento = date;
        _birthDate.text = DateFormat('dd/MM/yyyy').format(date);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final controller = ref.watch(appControllerProvider);
    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              Color(0xFF132830),
              AppColors.background,
              Color(0xFF0C181C),
            ],
          ),
        ),
        child: SafeArea(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(24, 28, 24, 32),
            children: [
              const ViseBrandMark(),
              const SizedBox(height: 28),
              Text(
                'Sua mente,\nno seu ritmo.',
                style: GoogleFonts.figtree(
                  fontSize: 40,
                  fontWeight: FontWeight.w800,
                  height: 1.05,
                  letterSpacing: -1.4,
                  color: AppColors.text,
                ),
              ),
              const SizedBox(height: 14),
              Text(
                'Um espaço seguro para responder com calma e sinceridade.',
                style: GoogleFonts.figtree(
                  color: AppColors.muted,
                  fontSize: 16,
                  height: 1.45,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 26),
              const AvatarBubble(
                message:
                    'Olá! Informe seu CPF e sua data de nascimento para começar.',
              ),
              const SizedBox(height: 28),
              Form(
                key: _formKey,
                child: Column(
                  children: [
                    TextFormField(
                      controller: _cpf,
                      keyboardType: TextInputType.number,
                      textInputAction: TextInputAction.next,
                      autofillHints: const [AutofillHints.username],
                      decoration: const InputDecoration(
                        labelText: 'CPF',
                        prefixIcon: Icon(Icons.badge_outlined),
                      ),
                      validator: (value) =>
                          (value ?? '').replaceAll(RegExp(r'\D'), '').length ==
                              11
                          ? null
                          : 'Informe um CPF com 11 números.',
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _birthDate,
                      readOnly: true,
                      onTap: _pickDate,
                      decoration: const InputDecoration(
                        labelText: 'Data de nascimento',
                        hintText: '21/10/2009',
                        prefixIcon: Icon(Icons.calendar_today_outlined),
                      ),
                      validator: (_) =>
                          _nascimento == null ? 'Selecione a data.' : null,
                    ),
                    if (controller.erro != null) ...[
                      const SizedBox(height: 14),
                      Text(
                        controller.erro!,
                        textAlign: TextAlign.center,
                        style: GoogleFonts.figtree(
                          color: const Color(0xFFFFB4B4),
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                    const SizedBox(height: 22),
                    _PillCta(
                      loading: controller.carregando,
                      onPressed: controller.carregando ? null : _submit,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              Text(
                'Precisa de ajuda? Fale com um professor ou com a equipe da sua escola.',
                textAlign: TextAlign.center,
                style: GoogleFonts.figtree(
                  color: AppColors.muted,
                  height: 1.45,
                  fontSize: 14,
                ),
              ),
              if (ref.watch(appControllerProvider).codigoSalaPendente !=
                  null) ...[
                const SizedBox(height: 12),
                Text(
                  'Sala ${ref.watch(appControllerProvider).codigoSalaPendente} será aberta após o login.',
                  textAlign: TextAlign.center,
                  style: GoogleFonts.figtree(
                    color: AppColors.mint,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _PillCta extends StatelessWidget {
  const _PillCta({required this.loading, required this.onPressed});

  final bool loading;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      height: 52,
      child: FilledButton(
        onPressed: onPressed,
        style: FilledButton.styleFrom(
          backgroundColor: Colors.white,
          foregroundColor: AppColors.ink,
          disabledBackgroundColor: Colors.white24,
          shape: const StadiumBorder(),
          textStyle: GoogleFonts.figtree(
            fontSize: 16,
            fontWeight: FontWeight.w800,
          ),
        ),
        child: loading
            ? const SizedBox.square(
                dimension: 22,
                child: CircularProgressIndicator(
                  strokeWidth: 2.4,
                  color: AppColors.ink,
                ),
              )
            : const Text('Começar'),
      ),
    );
  }
}
