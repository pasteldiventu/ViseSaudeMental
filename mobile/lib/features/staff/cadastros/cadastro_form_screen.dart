import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';

import '../../../core/theme/app_theme.dart';
import '../../../data/repositories/cadastros_repository.dart';
import '../../../providers/app_providers.dart';

/// Formulário montado a partir da descrição de campos enviada pela API.
/// Retorna o id gravado ao fechar com sucesso.
class CadastroFormScreen extends ConsumerStatefulWidget {
  const CadastroFormScreen({
    super.key,
    required this.cadastroKey,
    this.id,
    this.preencher = const {},
  });

  final String cadastroKey;
  final int? id;
  final Map<String, String> preencher;

  @override
  ConsumerState<CadastroFormScreen> createState() => _CadastroFormScreenState();
}

const _textuais = {
  'text',
  'email',
  'textarea',
  'int',
  'decimal',
  'password',
  'color',
};

const _paleta = [
  '#14B8A6',
  '#38BDF8',
  '#4ECB8C',
  '#F59E0B',
  '#F43F5E',
  '#A78BFA',
  '#FB923C',
  '#64748B',
];

class _CadastroFormScreenState extends ConsumerState<CadastroFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _controllers = <String, TextEditingController>{};
  final _valores = <String, dynamic>{};
  Formulario? _form;
  String? _erro;
  bool _salvando = false;

  @override
  void initState() {
    super.initState();
    Future.microtask(_carregar);
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _carregar() async {
    try {
      final form = await ref
          .read(cadastrosRepositoryProvider)
          .formulario(
            widget.cadastroKey,
            id: widget.id,
            preencher: widget.preencher,
          );
      for (final campo in form.campos) {
        final valor = campo.valor;
        if (_textuais.contains(campo.tipo)) {
          _controllers[campo.name] =
              TextEditingController(text: valor?.toString() ?? '')
                ..addListener(() {
                  if (campo.tipo == 'color') setState(() {});
                });
        } else if (campo.tipo == 'bool') {
          _valores[campo.name] = valor == true;
        } else if (campo.tipo == 'select' || campo.tipo == 'fk') {
          final texto = valor?.toString();
          final existe = campo.opcoes?.any((o) => o.valor == texto) ?? false;
          _valores[campo.name] = existe ? texto : null;
        } else {
          _valores[campo.name] = valor?.toString();
        }
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).hideCurrentSnackBar();
      setState(() => _form = form);
    } catch (error) {
      if (!mounted) return;
      setState(
        () => _erro = mensagemDeErro(
          error,
          'Não foi possível abrir o formulário.',
        ),
      );
    }
  }

  Future<void> _salvar() async {
    final form = _form;
    if (form == null || !_formKey.currentState!.validate()) return;
    final dados = <String, dynamic>{};
    for (final campo in form.campos) {
      dados[campo.name] = _textuais.contains(campo.tipo)
          ? _controllers[campo.name]!.text
          : _valores[campo.name];
    }
    setState(() {
      _salvando = true;
      _erro = null;
    });
    try {
      final result = await ref
          .read(cadastrosRepositoryProvider)
          .salvar(widget.cadastroKey, dados, id: widget.id);
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(result.mensagem)));
      Navigator.of(context).pop(result.id);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _salvando = false;
        _erro = mensagemDeErro(error, 'Não foi possível salvar.');
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final form = _form;
    return Scaffold(
      appBar: AppBar(title: Text(form?.titulo ?? 'Carregando…')),
      body: form == null
          ? Center(
              child: _erro == null
                  ? const CircularProgressIndicator()
                  : Padding(
                      padding: const EdgeInsets.all(24),
                      child: Text(_erro!, textAlign: TextAlign.center),
                    ),
            )
          : Form(
              key: _formKey,
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 40),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (final campo in form.campos) ...[
                      _campo(campo),
                      const SizedBox(height: 14),
                    ],
                    if (_erro != null) ...[
                      Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: const Color(0x33F43F5E),
                          borderRadius: BorderRadius.circular(AppRadii.sm),
                        ),
                        child: Text(
                          _erro!,
                          style: GoogleFonts.figtree(
                            color: const Color(0xFFFFB4B4),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                      const SizedBox(height: 14),
                    ],
                    FilledButton(
                      onPressed: _salvando ? null : _salvar,
                      child: _salvando
                          ? const SizedBox.square(
                              dimension: 22,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.4,
                              ),
                            )
                          : const Text('Salvar'),
                    ),
                  ],
                ),
              ),
            ),
    );
  }

  String _rotulo(CampoFormulario campo) =>
      campo.obrigatorio ? '${campo.label} *' : campo.label;

  String? _obrigatorio(CampoFormulario campo, Object? valor) {
    if (!campo.obrigatorio) return null;
    final vazio = valor == null || (valor is String && valor.trim().isEmpty);
    return vazio ? 'Preencha ${campo.label.toLowerCase()}.' : null;
  }

  Widget _campo(CampoFormulario campo) {
    switch (campo.tipo) {
      case 'bool':
        return SwitchListTile(
          contentPadding: const EdgeInsets.symmetric(horizontal: 4),
          title: Text(campo.label),
          subtitle: campo.ajuda == null ? null : Text(campo.ajuda!),
          value: _valores[campo.name] == true,
          onChanged: (v) => setState(() => _valores[campo.name] = v),
        );
      case 'select':
      case 'fk':
        final opcoes = campo.opcoes ?? const <OpcaoCampo>[];
        return DropdownButtonFormField<String>(
          initialValue: _valores[campo.name] as String?,
          isExpanded: true,
          decoration: InputDecoration(
            labelText: _rotulo(campo),
            helperText: campo.ajuda,
            helperMaxLines: 3,
          ),
          items: [
            if (!campo.obrigatorio)
              const DropdownMenuItem<String>(value: null, child: Text('—')),
            for (final opcao in opcoes)
              DropdownMenuItem<String>(
                value: opcao.valor,
                child: Text(opcao.label, overflow: TextOverflow.ellipsis),
              ),
          ],
          validator: (v) => _obrigatorio(campo, v),
          onChanged: (v) => setState(() => _valores[campo.name] = v),
        );
      case 'date':
      case 'datetime':
        return _campoData(campo);
      default:
        return _campoTexto(campo);
    }
  }

  Widget _campoTexto(CampoFormulario campo) {
    final controller = _controllers[campo.name]!;
    final numerico = campo.tipo == 'int' || campo.tipo == 'decimal';
    Color? cor;
    if (campo.tipo == 'color') {
      final hex = controller.text.replaceAll('#', '');
      final parsed = hex.length == 6 ? int.tryParse(hex, radix: 16) : null;
      if (parsed != null) cor = Color(0xFF000000 | parsed);
    }
    final field = TextFormField(
      controller: controller,
      obscureText: campo.tipo == 'password',
      maxLines: campo.tipo == 'textarea' ? 5 : 1,
      minLines: campo.tipo == 'textarea' ? 3 : 1,
      maxLength: campo.max != null && campo.max! > 60 ? campo.max : null,
      keyboardType: switch (campo.tipo) {
        'email' => TextInputType.emailAddress,
        'int' => const TextInputType.numberWithOptions(signed: true),
        'decimal' => const TextInputType.numberWithOptions(
          decimal: true,
          signed: true,
        ),
        'textarea' => TextInputType.multiline,
        _ => TextInputType.text,
      },
      inputFormatters: [
        if (campo.max != null) LengthLimitingTextInputFormatter(campo.max),
        if (campo.tipo == 'int')
          FilteringTextInputFormatter.allow(RegExp(r'^-?\d*')),
        if (campo.tipo == 'decimal')
          FilteringTextInputFormatter.allow(RegExp(r'^-?[\d.,]*')),
      ],
      decoration: InputDecoration(
        labelText: _rotulo(campo),
        helperText: campo.ajuda,
        helperMaxLines: 3,
        alignLabelWithHint: campo.tipo == 'textarea',
        prefixIcon: campo.tipo == 'color'
            ? Padding(
                padding: const EdgeInsets.all(14),
                child: CircleAvatar(
                  radius: 10,
                  backgroundColor: cor ?? AppColors.locked,
                ),
              )
            : null,
      ),
      validator: (v) {
        if (campo.tipo == 'password' && widget.id != null) return null;
        final erro = _obrigatorio(campo, v);
        if (erro != null) return erro;
        if (numerico &&
            v != null &&
            v.trim().isNotEmpty &&
            num.tryParse(v.replaceAll(',', '.')) == null) {
          return 'Informe um número.';
        }
        return null;
      },
    );
    if (campo.tipo != 'color') return field;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        field,
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final hex in _paleta)
              InkWell(
                borderRadius: BorderRadius.circular(20),
                onTap: () => controller.text = hex,
                child: CircleAvatar(
                  radius: 16,
                  backgroundColor: Color(
                    0xFF000000 | int.parse(hex.substring(1), radix: 16),
                  ),
                  child: controller.text.toUpperCase() == hex
                      ? const Icon(Icons.check, size: 16, color: Colors.white)
                      : null,
                ),
              ),
          ],
        ),
      ],
    );
  }

  Widget _campoData(CampoFormulario campo) {
    final valor = _valores[campo.name] as String?;
    final comHora = campo.tipo == 'datetime';
    String exibicao = '';
    final data = valor == null
        ? null
        : DateTime.tryParse(valor.replaceFirst(' ', 'T'));
    if (data != null) {
      exibicao = DateFormat(
        comHora ? 'dd/MM/yyyy HH:mm' : 'dd/MM/yyyy',
      ).format(data);
    }
    return FormField<String>(
      initialValue: valor,
      validator: (_) => _obrigatorio(campo, _valores[campo.name]),
      builder: (state) => InkWell(
        borderRadius: BorderRadius.circular(AppRadii.md),
        onTap: () async {
          final agora = DateTime.now();
          final dia = await showDatePicker(
            context: context,
            initialDate: data ?? agora,
            firstDate: DateTime(1950),
            lastDate: DateTime(agora.year + 10),
            locale: const Locale('pt', 'BR'),
          );
          if (dia == null || !mounted) return;
          var escolhido = dia;
          if (comHora) {
            final hora = await showTimePicker(
              context: context,
              initialTime: TimeOfDay.fromDateTime(data ?? agora),
            );
            if (hora == null) return;
            escolhido = DateTime(
              dia.year,
              dia.month,
              dia.day,
              hora.hour,
              hora.minute,
            );
          }
          final texto = DateFormat(
            comHora ? 'yyyy-MM-dd HH:mm' : 'yyyy-MM-dd',
          ).format(escolhido);
          setState(() => _valores[campo.name] = texto);
          state.didChange(texto);
        },
        child: InputDecorator(
          decoration: InputDecoration(
            labelText: _rotulo(campo),
            helperText: campo.ajuda,
            errorText: state.errorText,
            prefixIcon: Icon(
              comHora ? Icons.event_outlined : Icons.calendar_today_outlined,
            ),
            suffixIcon: valor == null || campo.obrigatorio
                ? null
                : IconButton(
                    tooltip: 'Limpar',
                    icon: const Icon(Icons.close),
                    onPressed: () {
                      setState(() => _valores[campo.name] = null);
                      state.didChange(null);
                    },
                  ),
          ),
          isEmpty: exibicao.isEmpty,
          child: Text(exibicao),
        ),
      ),
    );
  }
}
