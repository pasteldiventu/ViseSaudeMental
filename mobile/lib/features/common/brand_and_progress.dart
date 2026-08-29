import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/theme/app_theme.dart';

class ViseBrandMark extends StatelessWidget {
  const ViseBrandMark({super.key, this.compact = false});

  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Vise',
          style: GoogleFonts.figtree(
            fontSize: compact ? 28 : 44,
            fontWeight: FontWeight.w800,
            height: 0.95,
            color: AppColors.mint,
            letterSpacing: -1.2,
          ),
        ),
        Text(
          'Saúde Mental',
          style: GoogleFonts.figtree(
            fontSize: compact ? 14 : 17,
            fontWeight: FontWeight.w600,
            color: AppColors.text,
            height: 1.2,
          ),
        ),
      ],
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
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 18),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(AppRadii.lg),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            eyebrow.toUpperCase(),
            style: GoogleFonts.figtree(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              letterSpacing: 1.4,
              color: AppColors.mint,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            title,
            style: GoogleFonts.figtree(
              fontSize: 26,
              fontWeight: FontWeight.w800,
              color: AppColors.text,
              height: 1.15,
              letterSpacing: -0.6,
            ),
          ),
          const SizedBox(height: 14),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: LinearProgressIndicator(
              value: value.clamp(0.0, 1.0),
              minHeight: 8,
              backgroundColor: Colors.white12,
              color: AppColors.mint,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            progressLabel,
            style: GoogleFonts.figtree(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: AppColors.muted,
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
      color: AppColors.surfaceSoft,
      borderRadius: BorderRadius.circular(AppRadii.lg),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadii.lg),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 22, 18, 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: GoogleFonts.figtree(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  height: 1.2,
                  letterSpacing: -0.4,
                ),
              ),
              const SizedBox(height: 18),
              Align(
                alignment: Alignment.bottomRight,
                child: Text(
                  actionLabel,
                  style: GoogleFonts.figtree(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: AppColors.mint,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
