# Ledger

Open work: deferrals, known defects, follow-ups. One entry per item; remove when done.

## Component catalog (Storybook)

- **Stories for the remaining components.** Only `BaseButton`, `BaseInput` and `BaseModal` have stories.
  Pending: the other shared components (`BaseCheckbox`, `BaseInputDate`, `BaseInputFile`, `BaseRadio`,
  `BaseSelect`, `BaseSwitch`, `BaseTabs`, `BaseText`, `ErrorMessage`, `LoadContent`, `TableContainer`),
  the layout components (`AppHeader`, `AppFooter`, `MenuItem`, `ResponsiveMenu`) and the admin ones
  (`UserFormModal`, `UserTable`).
- **Router in the catalog preview.** Layout components use `<router-link>`. `.storybook/preview.js`
  installs no router yet; the real one hangs its initial navigation on the menu-ready promise. A
  memory-history router with the real routes is the likely fix, to be decided when the first layout
  story is written.
- **Stories as tests.** `@storybook/addon-vitest` (browser mode, Playwright) would run every story as a
  smoke and a11y test in the existing Vitest run. Deferred until the catalog covers more components.
- **Docgen engine before Storybook 11.** The default `vue-docgen-api` engine is deprecated and removed
  in Storybook 11. Both replacements (`features.experimentalDocgenServer` and the `vue-component-meta`
  framework option) require the `typescript` package, which this JavaScript project does not have.
  Decide whether to add it as a dev dependency when upgrading.
- **Replace `docs/componentes-shared.md` with autodocs.** The hand-written component list duplicates
  what the catalog's Docs tab now generates from `defineProps`.

## Frontend, noticed while working on the catalog

- **`tests/` directory is not run.** Vitest's `include` only matches `src/**/*.{test,spec}.js`, so the
  suites under `frontend/tests/contexts/` never execute (and some of them `return` booleans instead of
  asserting). Decide whether to move them next to their sources and fix them, or delete them.
- **Client locale files may never load.** `locales.scripts.js` writes client overrides to
  `src/core/locales/clients/<client>/<locale>.json`, but `i18n.config.js` globs one level deep
  (`../locales/*/*.json`), which does not reach them. Unverifiable without a Localise API key; check
  against a real export before changing either side.
- **Documentation language.** `frontend/docs/` and `README.md` are in Spanish; new documentation is
  written in English per the working conventions. Decide whether to translate the existing docs.
