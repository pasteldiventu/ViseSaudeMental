import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/theme/app_theme.dart';

/// Botão com “espessura” inferior (sensação tátil), sem parecer brinquedo.
class TactileButton extends StatefulWidget {
  const TactileButton({
    super.key,
    required this.onPressed,
    required this.child,
    this.color = AppColors.mint,
    this.depthColor = AppColors.mintDeep,
    this.foregroundColor = const Color(0xFF062016),
    this.height = 54,
    this.enabled = true,
  });

  final VoidCallback? onPressed;
  final Widget child;
  final Color color;
  final Color depthColor;
  final Color foregroundColor;
  final double height;
  final bool enabled;

  @override
  State<TactileButton> createState() => _TactileButtonState();
}

class _TactileButtonState extends State<TactileButton> {
  bool _pressed = false;

  bool get _active => widget.enabled && widget.onPressed != null;

  @override
  Widget build(BuildContext context) {
    final depth = _pressed || !_active ? 0.0 : 5.0;
    return Opacity(
      opacity: _active ? 1 : 0.55,
      child: GestureDetector(
        onTapDown: _active ? (_) => setState(() => _pressed = true) : null,
        onTapCancel: () => setState(() => _pressed = false),
        onTapUp: _active
            ? (_) {
                setState(() => _pressed = false);
                widget.onPressed?.call();
              }
            : null,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 80),
          transform: Matrix4.translationValues(0, _pressed ? 4 : 0, 0),
          height: widget.height + 5,
          child: Stack(
            children: [
              Positioned(
                left: 0,
                right: 0,
                bottom: 0,
                height: widget.height,
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    color: widget.depthColor,
                    borderRadius: BorderRadius.circular(16),
                  ),
                ),
              ),
              Positioned(
                left: 0,
                right: 0,
                top: depth,
                height: widget.height,
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    color: _active ? widget.color : AppColors.locked,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: DefaultTextStyle(
                    style: GoogleFonts.figtree(
                      color: widget.foregroundColor,
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                    ),
                    child: IconTheme(
                      data: IconThemeData(
                        color: widget.foregroundColor,
                        size: 22,
                      ),
                      child: Center(child: widget.child),
                    ),
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

class TactileOption extends StatefulWidget {
  const TactileOption({
    super.key,
    required this.label,
    required this.letter,
    required this.selected,
    required this.onPressed,
    this.enabled = true,
  });

  final String label;
  final String letter;
  final bool selected;
  final VoidCallback? onPressed;
  final bool enabled;

  @override
  State<TactileOption> createState() => _TactileOptionState();
}

class _TactileOptionState extends State<TactileOption> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    final selected = widget.selected;
    final border = selected ? AppColors.mint : AppColors.border;
    final fill = selected ? AppColors.mintSoft : AppColors.surfaceRaised;
    final depth = selected ? AppColors.mintDeep : const Color(0xFF152028);
    final dy = _pressed ? 3.0 : 0.0;

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: GestureDetector(
        onTapDown: widget.enabled
            ? (_) => setState(() => _pressed = true)
            : null,
        onTapCancel: () => setState(() => _pressed = false),
        onTapUp: widget.enabled
            ? (_) {
                setState(() => _pressed = false);
                widget.onPressed?.call();
              }
            : null,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 80),
          transform: Matrix4.translationValues(0, dy, 0),
          child: Container(
            decoration: BoxDecoration(
              color: depth,
              borderRadius: BorderRadius.circular(18),
            ),
            padding: EdgeInsets.only(bottom: _pressed ? 0 : 4),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
              decoration: BoxDecoration(
                color: fill,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: border, width: selected ? 2.2 : 1.5),
              ),
              child: Row(
                children: [
                  Container(
                    width: 36,
                    height: 36,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: selected ? AppColors.mint : Colors.white10,
                      shape: BoxShape.circle,
                    ),
                    child: Text(
                      widget.letter,
                      style: GoogleFonts.figtree(
                        fontWeight: FontWeight.w800,
                        color: selected
                            ? const Color(0xFF062016)
                            : AppColors.text,
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Text(
                      widget.label,
                      style: GoogleFonts.figtree(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                        height: 1.3,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
