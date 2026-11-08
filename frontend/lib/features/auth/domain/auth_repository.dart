import 'user.dart';

abstract interface class AuthRepository {
  /// Authenticates and stores the session. Throws `ApiException` on failure.
  Future<User> login({required String email, required String password});

  /// The current user if a stored session is still valid, otherwise null.
  Future<User?> restoreSession();

  Future<void> logout();
}
