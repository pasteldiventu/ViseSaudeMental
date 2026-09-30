import 'dart:typed_data';

import 'entregar_arquivo_io.dart'
    if (dart.library.js_interop) 'entregar_arquivo_web.dart'
    as impl;

const mimeXlsx = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

/// Entrega um arquivo gerado pelo servidor: download no navegador;
/// no Android abre a folha de compartilhamento (salvar no Drive, WhatsApp, e-mail...).
Future<void> entregarArquivo(Uint8List bytes, String nome, {String mime = mimeXlsx}) =>
    impl.entregarArquivo(bytes, nome, mime);
