import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/env.dart';
import '../storage/token_storage.dart';
import 'auth_interceptor.dart';

final tokenStorageProvider = Provider<TokenStorage>(
  (_) => SecureTokenStorage(),
);

/// Bumped when the refresh token is rejected; the auth controller listens to it
/// and sends the user back to the login screen.
final sessionExpiredProvider = NotifierProvider<SessionExpiredNotifier, int>(
  SessionExpiredNotifier.new,
);

class SessionExpiredNotifier extends Notifier<int> {
  @override
  int build() => 0;
  void expire() => state++;
}

BaseOptions _baseOptions() => BaseOptions(
  baseUrl: Env.apiBaseUrl,
  connectTimeout: const Duration(seconds: 10),
  receiveTimeout: const Duration(seconds: 20),
  headers: {'Accept': 'application/json'},
);

final dioProvider = Provider<Dio>((ref) {
  final dio = Dio(_baseOptions());
  dio.interceptors.add(
    AuthInterceptor(
      dio: dio,
      refreshDio: Dio(_baseOptions()),
      storage: ref.watch(tokenStorageProvider),
      onSessionExpired: () =>
          ref.read(sessionExpiredProvider.notifier).expire(),
    ),
  );
  return dio;
});
