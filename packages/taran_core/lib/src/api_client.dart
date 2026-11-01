import 'dart:convert';
import 'dart:io';

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

  static const _requestTimeout = Duration(seconds: 20);

  /// Unggahan berkas (hingga 5 MB) di jaringan seluler butuh waktu lebih lama.
  static const _uploadTimeout = Duration(seconds: 60);

  Uri _uri(String path, Map<String, String>? query) {
    final base = baseUrl.endsWith('/') ? baseUrl.substring(0, baseUrl.length - 1) : baseUrl;
    final uri = Uri.parse('$base$path');
    return (query == null || query.isEmpty) ? uri : uri.replace(queryParameters: query);
  }

  Future<Map<String, String>> _authHeader() async {
    final token = await tokens.read();
    return (token != null && token.isNotEmpty) ? {'Authorization': 'Bearer $token'} : const {};
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
    if (auth) h.addAll(await _authHeader());

    final request = http.Request(method, _uri(path, query));
    request.headers.addAll(h);
    if (body != null) request.body = jsonEncode(body);

    return _dispatch(
      request,
      timeout: _requestTimeout,
      networkMessage: 'Tidak dapat terhubung. Periksa koneksi internet Anda lalu coba lagi.',
      handleUnauthorized: auth,
    );
  }

  Future<JsonMap> uploadFile(
    String path, {
    required String field,
    required File file,
    Map<String, String>? fields,
  }) async {
    final request = http.MultipartRequest('POST', _uri(path, null));
    request.headers['Accept'] = 'application/json';
    request.headers.addAll(await _authHeader());
    if (fields != null) request.fields.addAll(fields);
    request.files.add(await http.MultipartFile.fromPath(field, file.path));

    return _dispatch(
      request,
      timeout: _uploadTimeout,
      networkMessage: 'Unggah gagal. Periksa koneksi internet lalu coba lagi.',
      handleUnauthorized: true,
    );
  }

  /// Satu jalur kirim, urai, dan petakan galat untuk semua jenis permintaan.
  Future<JsonMap> _dispatch(
    http.BaseRequest request, {
    required Duration timeout,
    required String networkMessage,
    required bool handleUnauthorized,
  }) async {
    final http.Response res;
    try {
      // Bound the complete request lifecycle, including response-body streaming.
      // Timing out only send() leaves Response.fromStream() able to hang forever
      // when a proxy or mobile connection stalls after response headers.
      res = await _http.send(request).then(http.Response.fromStream).timeout(timeout);
    } catch (_) {
      throw ApiException(status: 0, message: networkMessage);
    }

    final data = _decode(res);
    if (res.statusCode >= 200 && res.statusCode < 300) return data;
    if (res.statusCode == 401 && handleUnauthorized) {
      await tokens.clear();
      onUnauthorized?.call();
    }
    throw ApiException.fromResponse(res.statusCode, data);
  }

  JsonMap _decode(http.Response res) {
    if (res.bodyBytes.isEmpty) return {};
    try {
      final decoded = jsonDecode(utf8.decode(res.bodyBytes));
      if (decoded is Map<String, dynamic>) return decoded;
    } catch (_) {}
    return {};
  }
}
