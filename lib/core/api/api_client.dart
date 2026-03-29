import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../config/app_config.dart';
import 'api_exception.dart';

typedef TokenReader = Future<String?> Function();

class ApiClient {
  ApiClient({TokenReader? tokenReader})
      : _tokenReader = tokenReader,
        _dio = Dio(
          BaseOptions(
            baseUrl: AppConfig.apiBaseUrl,
            connectTimeout: const Duration(seconds: 15),
            receiveTimeout: const Duration(seconds: 20),
            headers: {'Accept': 'application/json'},
          ),
        ) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _tokenReader?.call();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
        onError: (e, handler) {
          final msg = _messageFromDio(e);
          handler.reject(
            DioException(
              requestOptions: e.requestOptions,
              response: e.response,
              type: e.type,
              error: ApiException(
                msg,
                statusCode: e.response?.statusCode,
                code: _codeFromBody(e.response?.data),
              ),
            ),
          );
        },
      ),
    );
  }

  final Dio _dio;
  final TokenReader? _tokenReader;

  Dio get dio => _dio;

  static String _messageFromDio(DioException e) {
    final data = e.response?.data;
    if (data is Map && data['error'] is String) {
      return data['error'] as String;
    }
    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout) {
      return 'Délai dépassé. Vérifiez votre connexion.';
    }
    if (e.type == DioExceptionType.connectionError) {
      return 'Impossible de joindre le serveur. Vérifiez l’URL de l’API.';
    }
    return e.message ?? 'Une erreur est survenue.';
  }

  static String? _codeFromBody(dynamic data) {
    if (data is Map && data['code'] is String) {
      return data['code'] as String;
    }
    return null;
  }

  Future<List<Map<String, dynamic>>> getTerrains() async {
    final r = await _dio.get<Map<String, dynamic>>('/terrains');
    final data = r.data?['data'];
    if (data is! List) return [];
    return data.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  Future<({Map<String, dynamic> terrain, String date, int priceXpf, List<Map<String, dynamic>> slots})>
      getSlots(int terrainId, String dateIso) async {
    final r = await _dio.get<Map<String, dynamic>>(
      '/terrains/$terrainId/slots',
      queryParameters: {'date': dateIso},
    );
    final m = r.data!;
    final terrain = Map<String, dynamic>.from(m['terrain'] as Map);
    final slots = (m['slots'] as List)
        .map((e) => Map<String, dynamic>.from(e as Map))
        .toList();
    return (
      terrain: terrain,
      date: m['date'] as String,
      priceXpf: (m['price_xpf'] as num).toInt(),
      slots: slots,
    );
  }

  Future<({String token, Map<String, dynamic> user})> login(
    String email,
    String password,
  ) async {
    final r = await _dio.post<Map<String, dynamic>>(
      '/login',
      data: {'email': email, 'password': password},
    );
    return _authFromResponse(r.data!);
  }

  Future<({String token, Map<String, dynamic> user})> register(
    String name,
    String email,
    String password,
  ) async {
    final r = await _dio.post<Map<String, dynamic>>(
      '/register',
      data: {'name': name, 'email': email, 'password': password},
    );
    return _authFromResponse(r.data!);
  }

  ({String token, Map<String, dynamic> user}) _authFromResponse(
    Map<String, dynamic> m,
  ) {
    final token = m['token'] as String;
    final user = Map<String, dynamic>.from(m['user'] as Map);
    return (token: token, user: user);
  }

  Future<Map<String, dynamic>> createReservation({
    required int terrainId,
    required String date,
    required String startTime,
    required String endTime,
  }) async {
    final r = await _dio.post<Map<String, dynamic>>(
      '/reservations',
      data: {
        'terrain_id': terrainId,
        'date': date,
        'start_time': startTime,
        'end_time': endTime,
      },
    );
    return Map<String, dynamic>.from(r.data!);
  }

  Future<List<Map<String, dynamic>>> myReservations() async {
    final r = await _dio.get<Map<String, dynamic>>('/reservations/me');
    final data = r.data?['data'];
    if (data is! List) return [];
    return data.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  Future<void> deleteReservation(int id) async {
    await _dio.delete<void>('/reservations/$id');
  }
}

/// Convertit les erreurs Dio (avec [ApiException] dans `error`) en message lisible.
String dioErrorMessage(Object e) {
  if (e is DioException && e.error is ApiException) {
    return (e.error! as ApiException).message;
  }
  if (e is ApiException) return e.message;
  debugPrint('Api error: $e');
  return 'Une erreur est survenue.';
}
