import 'package:agronomy_app/features/auth/data/user_dto.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('maps the GET /me payload to a User', () {
    final user = UserDto.fromJson({
      'id': '01a1bcd7-63d7-72af-8c19-27d50afe815e',
      'email': 'soporte@grupogestionet.com',
      'roles': ['ROLE_ADMIN', 'ROLE_USER'],
      'created_at': '2026-11-08 20:26:35',
      'updated_at': null,
    }).toEntity();

    expect(user.id, '01a1bcd7-63d7-72af-8c19-27d50afe815e');
    expect(user.email, 'soporte@grupogestionet.com');
    expect(user.roles, ['ROLE_ADMIN', 'ROLE_USER']);
    expect(user.isAdmin, isTrue);
  });
}
