import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../features/common/avatar_bubble.dart';
import '../../providers/app_providers.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _cpf = TextEditingController();
  final _birthDate = TextEditingController();

  @override
  void dispose() {
    _cpf.dispose();
    _birthDate.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final success = await ref
        .read(appControllerProvider)
        .entrar(_cpf.text, _birthDate.text);
    if (!success && mounted) {
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
      initialDate: DateTime(2010, 1, 1),
      firstDate: DateTime(1990),
      lastDate: DateTime.now(),
    );
    if (date != null) {
      _birthDate.text = DateFormat('yyyy-MM-dd').format(date);
    }
  }

  @override
  Widget build(BuildContext context) {
    final controller = ref.watch(appControllerProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('ENTRAR')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(24, 18, 24, 28),
          children: [
            const SizedBox(height: 22),
            const AvatarBubble(
              message: 'Olá! Informe seu CPF e sua data de nascimento.',
            ),
            const SizedBox(height: 42),
            Form(
              key: _formKey,
              child: Column(
                children: [
                  TextFormField(
                    controller: _cpf,
                    keyboardType: TextInputType.number,
                    autofillHints: const [AutofillHints.username],
                    decoration: const InputDecoration(
                      labelText: 'CPF',
                      prefixIcon: Icon(Icons.badge_outlined),
                    ),
                    validator: (value) =>
                        (value ?? '').replaceAll(RegExp(r'\D'), '').length == 11
                        ? null
                        : 'Informe um CPF com 11 números.',
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _birthDate,
                    readOnly: true,
                    onTap: _pickDate,
                    decoration: const InputDecoration(
                      labelText: 'Data de nascimento',
                      hintText: 'AAAA-MM-DD',
                      prefixIcon: Icon(Icons.calendar_today_outlined),
                    ),
                    validator: (value) => value == null || value.isEmpty
                        ? 'Selecione a data.'
                        : null,
                  ),
                  const SizedBox(height: 26),
                  FilledButton(
                    onPressed: controller.carregando ? null : _submit,
                    child: controller.carregando
                        ? const SizedBox.square(
                            dimension: 22,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Entrar'),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 26),
            const Text(
              'Precisa de ajuda para entrar? Procure um professor ou a equipe da sua escola.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.white60, height: 1.4),
            ),
          ],
        ),
      ),
    );
  }
}
