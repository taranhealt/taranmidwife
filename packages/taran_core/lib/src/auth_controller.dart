import 'package:flutter/foundation.dart';

import 'api_client.dart';
import 'api_exception.dart';
import 'models.dart';
import 'token_store.dart';

enum AuthStatus { unknown, signedOut, signedIn }

class AuthController extends ChangeNotifier {
  AuthController(this.api, this.tokens) {
    api.onUnauthorized = _onUnauthorized;
  }

  final ApiClient api;
  final TokenStore tokens;
  AuthStatus _status = AuthStatus.unknown;
  UserInfo? _user;

  AuthStatus get status => _status;
  UserInfo? get user => _user;

  void _onUnauthorized() {
    _user = null;
    _set(AuthStatus.signedOut);
  }

  Future<void> bootstrap() async {
    final token = await tokens.read();
    if (token == null || token.isEmpty) {
      _set(AuthStatus.signedOut);
      return;
    }
    try {
      final r = await api.send('GET', '/me');
      _user = UserInfo.fromJson(r['user'] as Map<String, dynamic>);
      _set(AuthStatus.signedIn);
    } on ApiException {
      _user = null;
      _set(AuthStatus.signedOut);
    }
  }

  Future<void> login({required String email, required String password}) async {
    final r = await api.send(
      'POST',
      '/auth/login',
      body: {'email': email, 'password': password},
      auth: false,
    );
    await _accept(r);
  }

  Future<void> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? phone,
    String role = 'patient',
  }) async {
    final r = await api.send(
      'POST',
      '/auth/register',
      body: {
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
        'role': role,
        if (phone != null) 'phone': phone,
      },
      auth: false,
    );
    await _accept(r);
  }

  Future<void> _accept(Map<String, dynamic> r) async {
    final token = r['token'];
    if (token is! String || token.isEmpty) {
      throw const ApiException(status: 500, message: 'Respons server tidak lengkap.');
    }
    await tokens.write(token);
    _user = UserInfo.fromJson(r['user'] as Map<String, dynamic>);
    _set(AuthStatus.signedIn);
  }

  Future<void> logout() async {
    try {
      await api.send('POST', '/auth/logout');
    } on ApiException {
      // Token mungkin sudah tidak berlaku; tetap keluar secara lokal.
    }
    await _clear();
  }

  Future<void> deleteAccount(String password) async {
    await api.send('DELETE', '/me', body: {'password': password});
    await _clear();
  }

  Future<void> _clear() async {
    await tokens.clear();
    _user = null;
    _set(AuthStatus.signedOut);
  }

  void _set(AuthStatus s) {
    _status = s;
    notifyListeners();
  }
}
