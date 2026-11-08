import 'package:dio/dio.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/storage/token_storage.dart';
import '../domain/auth_repository.dart';
import '../domain/user.dart';
import 'user_dto.dart';

class AuthRepositoryImpl implements AuthRepository {
  AuthRepositoryImpl({required this.dio, required this.storage});

  final Dio dio;
  final TokenStorage storage;

  @override
  Future<User> login({required String email, required String password}) async {
    try {
      final res = await dio.post<Map<String, dynamic>>(
        '/auth/login',
        data: {'email': email, 'password': password},
      );
      final data = res.data!['data'] as Map<String, dynamic>;
      await storage.write(
        AuthTokens(
          token: data['token'] as String,
          refreshToken: data['refresh_token'] as String,
        ),
      );
      return await _fetchMe();
    } on DioException catch (e) {
      await storage.clear();
      throw ApiException.fromDio(e);
    }
  }

  @override
  Future<User?> restoreSession() async {
    if (await storage.read() == null) return null;
    try {
      return await _fetchMe();
    } on DioException {
      // 401 here already went through the refresh attempt in the interceptor.
      return null;
    }
  }

  @override
  Future<void> logout() async {
    final tokens = await storage.read();
    await storage.clear();
    if (tokens == null) return;
    try {
      await dio.post<void>(
        '/auth/logout',
        data: {'refresh_token': tokens.refreshToken},
      );
    } on DioException {
      // Best effort: the local session is already gone.
    }
  }

  Future<User> _fetchMe() async {
    final res = await dio.get<Map<String, dynamic>>('/me');
    return UserDto.fromJson(res.data!['data'] as Map<String, dynamic>)
        .toEntity();
  }
}
