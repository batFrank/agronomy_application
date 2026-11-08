# agronomy_application

Symfony 7.3 API (`backend/`, hexagonal/DDD + CQRS) with a Flutter client (`frontend/`). Dockerized; see `Makefile` and `README.md`.

## Agents and skills

Defined in `.claude/` (see `.claude/AGENTS.md`): `product-owner`, `pair-programmer` (coordinates `navigator` + `driver`), `code-reviewer`.
Skills: architecture, code-analysis, testing, flutter, glab, plus one per agent.
Conventions: `docs/code/CONVENTIONS.md`.

## Commands

- `make install` / `make start` / `make stop` - Docker environment (PHP container: `symfony-base-php`; API at http://localhost:8011/api/v1)
- `make test`, `make phpstan`, `make fix-code-project` - backend quality gate
- Flutter: `cd frontend && flutter analyze && flutter test`

## Notes

- The Vue frontend was removed. `frontend/` is reserved for the Flutter app (not created yet). See `LEDGER.md` for pending cleanup (`scripts/deploy`, `setup-project.sh`).
- `.claude/AGENTS.md` was copied from smart-city; its `Agent({subagent_type})` examples map to the agents in `.claude/agents/`.
