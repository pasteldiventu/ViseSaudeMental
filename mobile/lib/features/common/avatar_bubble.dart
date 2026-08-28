import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

class AvatarBubble extends StatelessWidget {
  const AvatarBubble({super.key, required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Container(
          width: 74,
          height: 74,
          decoration: BoxDecoration(
            color: AppColors.mint.withValues(alpha: .14),
            shape: BoxShape.circle,
            border: Border.all(color: AppColors.mint, width: 2),
          ),
          child: const Icon(
            Icons.sentiment_satisfied_alt_rounded,
            color: AppColors.mint,
            size: 48,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(18),
                topRight: Radius.circular(18),
                bottomRight: Radius.circular(18),
              ),
              boxShadow: const [
                BoxShadow(color: Colors.black26, blurRadius: 10),
              ],
            ),
            child: Text(
              message,
              style: const TextStyle(
                color: Color(0xFF16232B),
                fontSize: 16,
                height: 1.35,
              ),
            ),
          ),
        ),
      ],
    );
  }
}
