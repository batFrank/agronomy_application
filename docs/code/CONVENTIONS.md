# Conventions

Source of truth for the agents in `.claude/`. Keep it short; extend as decisions are made.

## Backend (Symfony 7.3, `backend/`)

Hexagonal / DDD with CQRS. Details and naming tables live in `.claude/skills/architecture/SKILL.md`.

- Layers: `src/Domain` (no framework), `src/Application` (Commands/Queries/Handlers/DTOs), `src/Infrastructure` (controllers, persistence, external services).
- `declare(strict_types=1)` in every PHP file. PHPStan level 8, PHP-CS-Fixer.
- Soft deletes via `deletedAt`; `markAsUpdated()` only in the persistence layer.
- Tests: AAA, DataProviders as generators, Mother pattern. See `.claude/skills/testing/SKILL.md`.

Quality gate: `make test`, `make phpstan`, `make fix-code-project`.

## Frontend (Flutter, `frontend/`)

See `.claude/skills/flutter/SKILL.md`. Gate: `dart format`, `flutter analyze`, `flutter test`.

## Language

- Code, identifiers and new documentation: English.
- Agent replies to the team and GitLab issue/MR text: Spanish (castellano), as configured in the agents.
