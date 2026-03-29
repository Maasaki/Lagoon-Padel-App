import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../core/api/api_client.dart';
import '../../core/config/secure_keys.dart';

class AuthState extends ChangeNotifier {
  AuthState(this._api, [FlutterSecureStorage? storage])
      : _storage = storage ??
            const FlutterSecureStorage(
              aOptions: AndroidOptions(encryptedSharedPreferences: true),
            ) {
    _load();
  }

  final ApiClient _api;
  final FlutterSecureStorage _storage;

  String? _token;
  Map<String, dynamic>? _user;
  bool _ready = false;

  bool get isReady => _ready;
  bool get isLoggedIn => _token != null && _token!.isNotEmpty;
  Map<String, dynamic>? get user => _user;
  String? get userName => _user?['name'] as String?;

  Future<void> _load() async {
    try {
      _token = await _storage.read(key: SecureKeys.jwt);
      final raw = await _storage.read(key: SecureKeys.user);
      if (raw != null && raw.isNotEmpty) {
        final m = jsonDecode(raw);
        if (m is Map<String, dynamic>) {
          _user = m;
        }
      }
    } finally {
      _ready = true;
      notifyListeners();
    }
  }

  Future<void> login(String email, String password) async {
    final res = await _api.login(email, password);
    await _persist(res.token, res.user);
  }

  Future<void> register(String name, String email, String password) async {
    final res = await _api.register(name, email, password);
    await _persist(res.token, res.user);
  }

  Future<void> _persist(String token, Map<String, dynamic> user) async {
    _token = token;
    _user = user;
    await _storage.write(key: SecureKeys.jwt, value: token);
    await _storage.write(key: SecureKeys.user, value: jsonEncode(user));
    notifyListeners();
  }

  Future<void> logout() async {
    _token = null;
    _user = null;
    await _storage.delete(key: SecureKeys.jwt);
    await _storage.delete(key: SecureKeys.user);
    notifyListeners();
  }

  Future<String?> tokenForApi() async {
    if (_token != null) return _token;
    return _storage.read(key: SecureKeys.jwt);
  }
}
