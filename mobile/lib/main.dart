import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'core/shorebird/shorebird_service.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  unawaited(ShorebirdService.verificarEmBackground());
  runApp(const ProviderScope(child: ViseApp()));
}
