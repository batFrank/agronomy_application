class User {
  const User({required this.id, required this.email, required this.roles});

  final String id;
  final String email;
  final List<String> roles;

  bool get isAdmin => roles.contains('ROLE_ADMIN');
}
