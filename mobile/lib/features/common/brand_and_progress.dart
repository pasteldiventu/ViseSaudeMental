import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/theme/app_theme.dart';
import 'ui_kit.dart';

class ViseBrandMark extends StatelessWidget {
  const ViseBrandMark({super.key, this.compact = false});

  static const assetPath = 'assets/branding/logo.jpeg';

  final bool compact;

  @override
  Widget build(BuildContext context) {
    final height = compact ? 72.0 : 120.0;
    return Semantics(
      label: 'VISE-MT — Vigilância e Monitoramento em Saúde do Escolar',
      image: true,
      child: Align(
        alignment: Alignment.centerLeft,
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(AppRadii.lg),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.18),
                blurRadius: 18,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(AppRadii.lg),
            child: Image.asset(
              assetPath,
              height: height,
              fit: BoxFit.contain,
              filterQuality: FilterQuality.high,
            ),
          ),
        ),
      ),
    );
  }
}

class ProgressBanner extends StatelessWidget {
  const ProgressBanner({
    super.key,
    required this.eyebrow,
    required this.title,
    required this.progressLabel,
    required this.value,
  });

  final String eyebrow;
  final String title;
  final String progressLabel;
  final double value;

  @override
  Widget build(BuildContext context) {
    final pct = value.clamp(0.0, 1.0);
    return HeroCard(
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.25),
                    borderRadius: BorderRadius.circular(99),
                  ),
                  child: Text(
                    eyebrow,
                    style: GoogleFonts.figtree(
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  title,
                  style: GoogleFonts.figtree(
                    fontSize: 23,
                    fontWeight: FontWeight.w800,
                    height: 1.15,
                    letterSpacing: -0.6,
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  progressLabel,
                  style: GoogleFonts.figtree(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ink.withValues(alpha: 0.7),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 14),
          SizedBox.square(
            dimension: 84,
            child: Stack(
              fit: StackFit.expand,
              children: [
                CircularProgressIndicator(
                  value: pct,
                  strokeWidth: 9,
                  strokeCap: StrokeCap.round,
                  backgroundColor: AppColors.ink.withValues(alpha: 0.14),
                  color: AppColors.ink,
                ),
                Center(
                  child: Text(
                    '${(pct * 100).round()}%',
                    style: GoogleFonts.figtree(
                      fontSize: 19,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class SoftPromptCard extends StatelessWidget {
  const SoftPromptCard({
    super.key,
    required this.title,
    required this.actionLabel,
    this.onTap,
  });

  final String title;
  final String actionLabel;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadii.lg),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadii.lg),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 14, 16),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: const BoxDecoration(
                  color: AppColors.mintSoft,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.play_arrow_rounded,
                  color: AppColors.mint,
                  size: 28,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: GoogleFonts.figtree(
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                        height: 1.2,
                        letterSpacing: -0.3,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      actionLabel,
                      style: GoogleFonts.figtree(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: AppColors.mint,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Container(
                width: 40,
                height: 40,
                decoration: const BoxDecoration(
                  color: AppColors.surfaceRaised,
                  shape: BoxShape.circle,
                ),
                child: Transform.rotate(
                  angle: -0.785,
                  child: const Icon(Icons.arrow_forward_rounded, size: 20),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
