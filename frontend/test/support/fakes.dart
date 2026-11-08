import 'package:agronomy_app/core/storage/token_storage.dart';
import 'package:agronomy_app/features/auth/domain/auth_repository.dart';
import 'package:agronomy_app/features/auth/domain/user.dart';
import 'package:agronomy_app/core/network/api_exception.dart';

class InMemoryTokenStorage implements TokenStorage {
  AuthTokens? tokens;

  @override
  Future<AuthTokens?> read() async => tokens;
  @override
  Future<void> write(AuthTokens t) async => tokens = t;
  @override
  Future<void> clear() async => tokens = null;
}

class FakeAuthRepository implements AuthRepository {
  FakeAuthRepository({this.restored});

  User? restored;
  int loginCalls = 0;

  static const admin = User(
    id: '1',
    email: 'soporte@grupogestionet.com',
    roles: ['ROLE_ADMIN', 'ROLE_USER'],
  );

  @override
  Future<User> login({required String email, required String password}) async {
    loginCalls++;
    if (password != '12345678') throw const ApiException('Bad credentials.');
    return admin;
  }

  @override
  Future<User?> restoreSession() async => restored;

  @override
  Future<void> logout() async {}
}
