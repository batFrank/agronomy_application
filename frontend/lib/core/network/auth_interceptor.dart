import 'dart:async';

import 'package:dio/dio.dart';

import '../storage/token_storage.dart';

/// Attaches the JWT and transparently renews it on 401.
///
/// The backend rotates refresh tokens (each one is single use), so concurrent
/// 401s must share ONE refresh call: [_refreshing] is that single flight.
class AuthInterceptor extends Interceptor {
  AuthInterceptor({
    required this.dio,
    required this.refreshDio,
    required this.storage,
    required this.onSessionExpired,
  });

  static const _retriedKey = 'auth_retried';

  final Dio dio;
  final Dio refreshDio; // no interceptors: avoids refresh loops
  final TokenStorage storage;
  final void Function() onSessionExpired;

  Future<AuthTokens?>? _refreshing;

  bool _isAuthRoute(RequestOptions o) => o.path.startsWith('/auth/');

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    if (!_isAuthRoute(options)) {
      final tokens = await storage.read();
      if (tokens != null) {
        options.headers['Authorization'] = 'Bearer ${tokens.token}';
      }
    }
    handler.next(options);
  }

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final req = err.requestOptions;
    final shouldRefresh =
        err.response?.statusCode == 401 &&
        !_isAuthRoute(req) &&
        req.extra[_retriedKey] != true;
    if (!shouldRefresh) return handler.next(err);

    final tokens = await (_refreshing ??= _refresh().whenComplete(
      () => _refreshing = null,
    ));
    if (tokens == null) {
      onSessionExpired();
      return handler.next(err);
    }

    try {
      req.extra[_retriedKey] = true;
      req.headers['Authorization'] = 'Bearer ${tokens.token}';
      handler.resolve(await dio.fetch<dynamic>(req));
    } on DioException catch (e) {
      handler.next(e);
    }
  }

  Future<AuthTokens?> _refresh() async {
    final current = await storage.read();
    if (current == null) return null;
    try {
      final res = await refreshDio.post<Map<String, dynamic>>(
        '/auth/refresh',
        data: {'refresh_token': current.refreshToken},
      );
      final data = res.data!['data'] as Map<String, dynamic>;
      final fresh = AuthTokens(
        token: data['token'] as String,
        refreshToken: data['refresh_token'] as String,
      );
      await storage.write(fresh);
      return fresh;
    } on DioException {
      await storage.clear();
      return null;
    }
  }
}
