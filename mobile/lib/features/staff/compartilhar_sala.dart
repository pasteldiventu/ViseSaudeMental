import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:share_plus/share_plus.dart';

import '../../core/theme/app_theme.dart';
import '../../data/repositories/staff_repository.dart';

/// Código da sala com as opções de copiar e compartilhar o link para os alunos.
Future<void> mostrarCompartilharSala(
  BuildContext context,
  SalaResumo sala, {
  String titulo = 'Sala criada',
}) {
  return showModalBottomSheet<void>(
    context: context,
    builder: (ctx) {
      return Padding(
        padding: const EdgeInsets.fromLTRB(24, 0, 24, 32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              titulo,
              style: GoogleFonts.figtree(
                fontSize: 22,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              '${sala.questionarioNome} · envie o link ou o código para os alunos.',
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
