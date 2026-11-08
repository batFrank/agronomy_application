import 'package:agronomy_app/app.dart';
import 'package:agronomy_app/features/auth/presentation/auth_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../support/fakes.dart';

Future<void> _pumpApp(WidgetTester tester, FakeAuthRepository repo) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [authRepositoryProvider.overrideWithValue(repo)],
      child: const App(),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('without a session the login screen is shown', (tester) async {
    await _pumpApp(tester, FakeAuthRepository());

    expect(find.text('Iniciar sesión'), findsOneWidget);
  });

  testWidgets('valid credentials lead to the home screen', (tester) async {
    final repo = FakeAuthRepository();
    await _pumpApp(tester, repo);

    await tester.enterText(
      find.widgetWithText(TextFormField, 'Email'),
      'soporte@grupogestionet.com',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Contraseña'),
      '12345678',
    );
    await tester.tap(find.text('Entrar'));
    await tester.pumpAndSettle();

    expect(repo.loginCalls, 1);
    expect(
      find.text('Sesión iniciada como soporte@grupogestionet.com'),
      findsOneWidget,
    );
  });

  testWidgets('bad credentials show the backend message', (tester) async {
    await _pumpApp(tester, FakeAuthRepository());

    await tester.enterText(
      find.widgetWithText(TextFormField, 'Email'),
      'x@x.com',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Contraseña'),
      'wrong',
    );
    await tester.tap(find.text('Entrar'));
    await tester.pumpAndSettle();

    expect(find.text('Bad credentials.'), findsOneWidget);
    expect(find.text('Iniciar sesión'), findsOneWidget);
  });

  testWidgets('a stored valid session skips the login', (tester) async {
    await _pumpApp(
      tester,
      FakeAuthRepository(restored: FakeAuthRepository.admin),
    );

    expect(find.text('Iniciar sesión'), findsNothing);
    expect(find.textContaining('Sesión iniciada como'), findsOneWidget);
  });

  testWidgets('logout returns to the login screen', (tester) async {
    await _pumpApp(
      tester,
      FakeAuthRepository(restored: FakeAuthRepository.admin),
    );

    await tester.tap(find.byTooltip('Cerrar sesión'));
    await tester.pumpAndSettle();

    expect(find.text('Iniciar sesión'), findsOneWidget);
  });
}
