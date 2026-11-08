import '../domain/user.dart';

class UserDto {
  const UserDto({required this.id, required this.email, required this.roles});

  /// Parses the `data` object of `GET /me`.
  factory UserDto.fromJson(Map<String, dynamic> json) => UserDto(
    id: json['id'] as String,
    email: json['email'] as String,
    roles: (json['roles'] as List<dynamic>).cast<String>(),
  );

  final String id;
  final String email;
  final List<String> roles;

  User toEntity() => User(id: id, email: email, roles: roles);
}
