import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Wellness escuro + pastéis (SYNQ) + progressão clara (Duolingo),
/// sem XP/ranking e sem paleta roxa genérica.
class AppColors {
  const AppColors._();

  static const background = Color(0xFF0E1A20);
  static const surface = Color(0xFF16252D);
  static const surfaceRaised = Color(0xFF1D3039);
  static const surfaceSoft = Color(0xFF243A44);
  static const border = Color(0xFF35515C);
  static const pathLine = Color(0xFF2B414B);

  static const mint = Color(0xFF4ECB8C);
  static const mintDeep = Color(0xFF2FA06A);
  static const mintSoft = Color(0xFF1A3D2F);

  static const text = Color(0xFFF4F7F9);
  static const muted = Color(0xFF9BB0BA);
  static const ink = Color(0xFF132028);

  static const bubble = Color(0xFFF7FAFC);
  static const bubbleText = Color(0xFF152029);

  static const warn = Color(0xFFE8A54B);
  static const locked = Color(0xFF3A4B55);

  /// Pastéis das etapas (inspiração wellness / SYNQ).
  static const pastelSky = Color(0xFFD6EEF6);
  static const pastelPeach = Color(0xFFFFE5D8);
  static const pastelMint = Color(0xFFD9F3E4);
  static const pastelSand = Color(0xFFF3EEDF);
  static const pastelRose = Color(0xFFF6E2EA);
  static const pastelLime = Color(0xFFEAF5D2);

  static const pastels = [
    pastelSky,
    pastelPeach,
    pastelMint,
    pastelSand,
    pastelRose,
    pastelLime,
  ];

  static Color pastelFor(int index) => pastels[index % pastels.length];

  /// Cartão de destaque (herói): menta → menta profundo.
  static const heroGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [mint, mintDeep, Color(0xFF1E7A50)],
    stops: [0, 0.65, 1],
  );
}

class AppRadii {
  const AppRadii._();

  static const sm = 14.0;
  static const md = 20.0;
  static const lg = 28.0;
  static const xl = 36.0;
}

class AppTheme {
  const AppTheme._();

  static ThemeData get dark {
    final textTheme = GoogleFonts.figtreeTextTheme(
      ThemeData(brightness: Brightness.dark).textTheme,
    ).apply(bodyColor: AppColors.text, displayColor: AppColors.text);

    final scheme = ColorScheme.dark(
      surface: AppColors.surface,
      primary: AppColors.mint,
      onPrimary: AppColors.ink,
      secondary: AppColors.mintDeep,
      onSurface: AppColors.text,
      outline: AppColors.border,
    );

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: scheme,
      scaffoldBackgroundColor: AppColors.background,
      textTheme: textTheme,
      appBarTheme: AppBarTheme(
        backgroundColor: Colors.transparent,
        surfaceTintColor: Colors.transparent,
        foregroundColor: AppColors.text,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        toolbarHeight: 64,
        titleTextStyle: GoogleFonts.figtree(
          fontSize: 21,
          fontWeight: FontWeight.w800,
          letterSpacing: -0.4,
          color: AppColors.text,
        ),
      ),
      cardTheme: CardThemeData(
        color: AppColors.surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadii.lg),
        ),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: AppColors.surfaceRaised,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadii.lg),
        ),
        titleTextStyle: GoogleFonts.figtree(
          fontSize: 20,
          fontWeight: FontWeight.w800,
          color: AppColors.text,
        ),
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: AppColors.surface,
        surfaceTintColor: Colors.transparent,
        showDragHandle: true,
        dragHandleColor: AppColors.border,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(
            top: Radius.circular(AppRadii.xl),
          ),
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: AppColors.surfaceRaised,
        selectedColor: AppColors.mint,
        disabledColor: AppColors.locked,
        side: BorderSide.none,
        shape: const StadiumBorder(),
        showCheckmark: false,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        labelStyle: GoogleFonts.figtree(
          fontWeight: FontWeight.w700,
          color: WidgetStateColor.resolveWith(
            (states) => states.contains(WidgetState.selected)
                ? AppColors.ink
                : AppColors.text,
          ),
        ),
        secondaryLabelStyle: GoogleFonts.figtree(
          fontWeight: FontWeight.w800,
          color: AppColors.ink,
        ),
        iconTheme: const IconThemeData(color: AppColors.mint, size: 18),
      ),
      floatingActionButtonTheme: FloatingActionButtonThemeData(
        backgroundColor: AppColors.mint,
        foregroundColor: AppColors.ink,
        elevation: 2,
        highlightElevation: 4,
        shape: const StadiumBorder(),
        extendedTextStyle: GoogleFonts.figtree(
          fontWeight: FontWeight.w800,
          fontSize: 15,
        ),
      ),
      listTileTheme: ListTileThemeData(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadii.sm),
        ),
        iconColor: AppColors.muted,
      ),
      switchTheme: SwitchThemeData(
        thumbColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected)
              ? AppColors.ink
              : AppColors.muted,
        ),
        trackColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected)
              ? AppColors.mint
              : AppColors.surfaceSoft,
        ),
        trackOutlineColor: WidgetStateProperty.all(Colors.transparent),
      ),
      dividerTheme: const DividerThemeData(
        color: AppColors.pathLine,
        thickness: 1,
        space: 1,
      ),
      popupMenuTheme: PopupMenuThemeData(
        color: AppColors.surfaceRaised,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadii.md),
        ),
        textStyle: GoogleFonts.figtree(
          fontWeight: FontWeight.w600,
          color: AppColors.text,
        ),
      ),
      drawerTheme: const DrawerThemeData(
        backgroundColor: AppColors.surface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.horizontal(
            right: Radius.circular(AppRadii.xl),
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.surfaceRaised,
        labelStyle: const TextStyle(color: AppColors.muted),
        hintStyle: TextStyle(color: AppColors.muted.withValues(alpha: 0.7)),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 18,
          vertical: 18,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadii.md),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadii.md),
          borderSide: BorderSide(
            color: AppColors.border.withValues(alpha: 0.45),
          ),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadii.md),
          borderSide: const BorderSide(color: AppColors.mint, width: 2),
        ),
      ),
      checkboxTheme: CheckboxThemeData(
        fillColor: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) return AppColors.mint;
          return Colors.transparent;
        }),
        checkColor: WidgetStateProperty.all(AppColors.ink),
        side: const BorderSide(color: AppColors.border, width: 2),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.mint,
          foregroundColor: AppColors.ink,
          disabledBackgroundColor: AppColors.locked,
          disabledForegroundColor: AppColors.muted,
          minimumSize: const Size.fromHeight(54),
          elevation: 0,
          shadowColor: Colors.transparent,
          shape: const StadiumBorder(),
          textStyle: GoogleFonts.figtree(
            fontSize: 17,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.text,
          minimumSize: const Size(0, 48),
          side: const BorderSide(color: AppColors.border, width: 1.4),
          shape: const StadiumBorder(),
          padding: const EdgeInsets.symmetric(horizontal: 18),
          textStyle: GoogleFonts.figtree(
            fontSize: 15,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.mint,
          shape: const StadiumBorder(),
          textStyle: GoogleFonts.figtree(fontWeight: FontWeight.w700),
        ),
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: AppColors.mint,
        linearTrackColor: AppColors.pathLine,
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: AppColors.surfaceRaised,
        contentTextStyle: GoogleFonts.figtree(color: AppColors.text),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadii.sm),
        ),
      ),
    );
  }
}
