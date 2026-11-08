---
name: flutter
description: Flutter frontend conventions (architecture, state, networking against the Symfony API, testing, quality gate). Use when creating or reviewing anything under frontend/ or the mobile/web client.
license: MIT
---

## What I do

- Guide implementation of the Flutter client that consumes the Symfony API (`backend/`)
- Keep a feature-first, layered structure that mirrors the backend's Domain/Application/Infrastructure split
- Enforce the quality gate (`flutter analyze`, `dart format`, `flutter test`)

## When to use me

- Creating screens, widgets, state, repositories or API clients in Flutter
- Reviewing Flutter code in a merge request
- Planning (Navigator) or implementing (Driver) any frontend task

## Project layout (feature-first)

```
frontend/
├── lib/
│   ├── main.dart
│   ├── core/                # config, theme, router, http client, error types, l10n
│   └── features/<feature>/
│       ├── domain/          # entities, repository interfaces (pure Dart, no Flutter imports)
│       ├── data/            # DTOs (fromJson/toJson), API data sources, repository impls
│       └── presentation/    # screens, widgets, state (controllers / notifiers)
└── test/                    # mirrors lib/; fakes instead of mocks where possible
```

Rules:
- `domain/` imports nothing from Flutter, `dio`/`http`, or `data/`.
- `presentation/` depends on `domain/` interfaces, never on DTOs.
- DTO -> entity mapping happens in `data/`.
- One public widget/class per file, `snake_case.dart` filenames, `UpperCamelCase` types.

## Backend contract

- Base URL comes from config (`--dart-define=API_BASE_URL=...`), never hard-coded. Local API: `http://localhost:8011/api/v1` (Android emulator: `http://10.0.2.2:8011/api/v1`).
- Auth (verified against the live API): `POST /auth/login` `{email, password}` -> `{data:{token, refresh_token}}`; errors are `{error, message}` (401 on bad credentials). `GET /me` -> `{data:{id,email,roles,...}}`. `POST /auth/refresh` and `/auth/logout` take `{refresh_token}`.
- **Refresh tokens are single use (rotated).** Concurrent 401s must share one refresh call; see `lib/core/network/auth_interceptor.dart`. Never refresh twice in parallel.
- Tokens live in `flutter_secure_storage`; the interceptor attaches `Authorization: Bearer <token>` and skips `/auth/*`.
- Read the real routes in `backend/config/routes/*.yaml` and `backend/src/Infrastructure/Controller/` before writing a client; do not guess endpoints or payloads.
- Entities use UUID ids and `createdAt/updatedAt/deletedAt`; model them as nullable/immutable fields.

## State and tooling (defaults, change only with team agreement)

- State management: Riverpod 3 (`flutter_riverpod`, already in `pubspec.yaml`).
- HTTP: `dio`. DTOs are hand-written for now. Routing: `go_router` with auth `redirect`.
- Reference implementation: `features/auth` (login, session restore, logout).
- Every async state exposes loading / data / error explicitly; no silent catches.

## Testing

- Unit tests for domain and data mappers; widget tests for screens with fake repositories.
- Follow AAA (Arrange, Act, Assert); name tests by behaviour.
- Use inline expected data, not helpers that hide it.

## Commands

```bash
cd frontend
flutter pub get
dart format --set-exit-if-changed .
flutter analyze
flutter test
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8011/api/v1
```

## Quality gate (before committing)

- [ ] `dart format --set-exit-if-changed .` clean
- [ ] `flutter analyze` has no issues
- [ ] `flutter test` passes
- [ ] No hard-coded URLs, secrets or tokens
- [ ] Layer rules above respected
