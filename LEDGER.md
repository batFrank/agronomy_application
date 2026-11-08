# Ledger

Open work: deferrals, known defects, follow-ups. One entry per item; remove when done.

## Flutter migration

- **Flutter app beyond login.** `frontend/` has the JWT login skeleton (`features/auth`, `core/network`); home is a placeholder. Not yet exercised against the live API from a running UI.
- **No i18n yet.** UI strings are hard-coded in Spanish.
- **Token storage on web.** `flutter_secure_storage` on web uses localStorage-backed WebCrypto; decide if that is acceptable for the web target.
- **`scripts/deploy/` is still Vue/Vite-specific.** `deploy.js`, `utils.js`, `deploy.config.example.json` and its README build with Bun/Vite and write an SPA `.htaccess` for `/app/`. Rewrite for `flutter build web` (or drop the frontend sync) once the target is decided.
- **`scripts/setup-project.sh` still patches frontend container/ports and `vite.config.js`.** Remove those parts.
- **`.gitlab-ci.yml`** (referenced by the README) is not in this repo; add a Flutter job (`flutter analyze`, `flutter test`) when it is created.
