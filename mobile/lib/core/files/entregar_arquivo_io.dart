import 'dart:io';
import 'dart:typed_data';

import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

Future<void> entregarArquivo(Uint8List bytes, String nome, String mime) async {
  final pasta = await getTemporaryDirectory();
  final arquivo = File(p.join(pasta.path, nome));
  await arquivo.writeAsBytes(bytes, flush: true);
  await SharePlus.instance.share(
    ShareParams(
      files: [XFile(arquivo.path, mimeType: mime, name: nome)],
      subject: nome,
    ),
  );
}
