import 'dart:convert';

import 'package:http/http.dart' as http;

import 'api_exception.dart';
import 'token_store.dart';

typedef JsonMap = Map<String, dynamic>;

class ApiClient {
  ApiClient({
    required this.baseUrl,
    required this.tokens,
    http.Client? httpClient,
    this.onUnauthorized,
  }) : _http = httpClient ?? http.Client();

  final String baseUrl;
  final TokenStore tokens;
  final http.Client _http;
  void Function()? onUnauthorized;

  Uri _uri(String path, Map<String, String>? query) {
    final base = baseUrl.endsWith('/') ? baseUrl.substring(0, baseUrl.length - 1) : baseUrl;
    final uri = Uri.parse('$base$path');
    return (query == null || query.isEmpty) ? uri : uri.replace(queryParameters: query);
  }

  Future<JsonMap> send(
    String method,
    String path, {
    Map<String, String>? query,
    Object? body,
    Map<String, String>? headers,
    bool auth = true,
  }) async {
    final h = <String, String>{'Accept': 'application/json'};
    if (body != null) h['Content-Type'] = 'application/json';
    if (headers != null) h.addAll(headers);
    if (auth) {
      final token = await tokens.read();
      if (token != null && token.isNotEmpty) h['Authorization'] = 'Bearer $token';
    }
    final request = http.Request(method, _uri(path, query));
    request.headers.addAll(h);
    if (body != null) request.body = jsonEncode(body);

    final http.Response res;
    try {
      final streamed = await _http.send(request).timeout(const Duration(seconds: 20));
      res = await http.Response.fromStream(streamed);
    } catch (_) {
      throw const ApiException(
        status: 0,
        message: 'Tidak dapat terhubung. Periksa koneksi internet Anda lalu coba lagi.',
      );
    }

    JsonMap data = {};
    if (res.bodyBytes.isNotEmpty) {
      try {
        final decoded = jsonDecode(utf8.decode(res.bodyBytes));
        if (decoded is Map<String, dynamic>) data = decoded;
      } catch (_) {}
    }
    if (res.statusCode >= 200 && res.statusCode < 300) return data;
    if (res.statusCode == 401 && auth) {
      await tokens.clear();
      onUnauthorized?.call();
    }
    throw ApiException.fromResponse(res.statusCode, data);
  }
}
