import 'package:flutter_test/flutter_test.dart';
import 'package:vise_sma_app/core/network/api_client.dart';

void main() {
  test('listFrom unwraps {data: [...]} even when a named key is missing', () {
    final result = ApiClient.listFrom({
      'data': [
        {'id': 1, 'nome': 'Q'},
      ],
    }, 'aplicacoes');
    expect(result, [
      {'id': 1, 'nome': 'Q'},
    ]);
  });

  test('listFrom uses the named key when present', () {
    final result = ApiClient.listFrom({
      'categorias': [
        {'id': 2},
      ],
    }, 'categorias');
    expect(result.single['id'], 2);
  });

  test('listFrom copies List<dynamic> maps without a typed toList cast', () {
    final raw = <dynamic>[
      <dynamic, dynamic>{'id': 3, 'nome': 'A'},
    ];
    final result = ApiClient.listFrom(raw);
    expect(result, isA<List<Map<String, dynamic>>>());
    expect(result.single['nome'], 'A');
  });
}
