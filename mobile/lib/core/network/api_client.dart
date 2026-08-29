import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../config/env.dart';

class ApiClient {
  ApiClient(this.storage)
    : dio = Dio(
        BaseOptions(
          baseUrl: Env.baseUrl,
          connectTimeout: const Duration(seconds: 8),
          receiveTimeout: const Duration(seconds: 15),
          headers: {'Accept': 'application/json'},
        ),
      ) {
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await storage.read(key: tokenKey);
          if (token != null) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
      ),
    );
  }

  static const tokenKey = 'auth_token';
  final FlutterSecureStorage storage;
  final Dio dio;

  static List<Map<String, dynamic>> listFrom(dynamic data, [String? key]) {
    var source = data;
    if (source is Map) {
      if (key != null && source[key] != null) {
        source = source[key];
      } else if (source['data'] != null) {
        source = source['data'];
      }
    }
    if (source is Map && source['data'] != null) {
      source = source['data'];
    }
    if (source is! Iterable) return const [];
    final result = <Map<String, dynamic>>[];
    for (final item in source) {
      if (item is Map) {
        result.add(Map<String, dynamic>.from(item));
      }
    }
    return result;
  }
}
