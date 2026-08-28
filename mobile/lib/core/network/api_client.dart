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
    dynamic source = data;
    if (source is Map && source['data'] is Map) source = source['data'];
    if (key != null && source is Map) source = source[key];
    final list = source is Map && source['data'] is List
        ? source['data']
        : source is List
        ? source
        : const [];
    return list
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }
}
