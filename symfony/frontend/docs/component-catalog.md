# Component catalog (Storybook)

The shared UI components are showcased and documented in a [Storybook](https://storybook.js.org) catalog.
It is a **separate static site**, never part of the application bundle: the app never imports it and its
size does not affect the app.

---

## Running it locally

```bash
VITE_CLIENT=easyLearning bun run storybook   # dev server on http://localhost:6006
VITE_CLIENT=default bun run build-storybook  # static site in storybook-static/
make frontend-storybook                      # same dev server, from the Docker container
```

The catalog reuses `vite.config.js`, so it is built **per client** exactly like the app: `VITE_CLIENT`
selects the client whose settings and `globals.scss` the components render with. Without it, the
`default` theme is used.

Locale messages come from `bun run locales`, the same precondition the dev server has.

---

## Shipping it with a client build

A client opts in through its `settings.js`:

```js
BUILD_COMPONENT_CATALOG: true, // default: false
```

When the flag is on, `bun run build` emits the catalog into `dist/storybook/` after building the app.
Apache aliases `dist/` to `/app/`, so the catalog is served at **`/app/storybook/`** as a sibling of
the app, with no change to the app itself.

Both builds share one output directory, and `vite build` empties it first. Building a client whose flag
is off therefore removes a catalog built earlier for another client. `/app/storybook/` then returns a
real **404** rather than the app shell, because the vhosts disable the SPA fallback for that path:

```apache
<Location "/app/storybook">
    FallbackResource disabled
</Location>
```

Without it the missing catalog would fall through to `index.html`, and the router would bounce the
visitor to their landing page, which looks like a redirect bug instead of a catalog that was never
built. Rebuild with a client that enables the flag to get it back.

Most clients never enable it. When one does, keep in mind that the folder is served publicly like the
rest of `dist/`: it exposes component names and mock data, not real data. Gate it at the web server
(basic auth, IP allowlist) if that matters for the deployment.

---

## The build command

`bun run build` is the single build entry point; the Makefile target `frontend-build` and CI go through
it too. It accepts flags and prompts for whatever is missing:

```bash
bun run build                                        # interactive: asks client, mode, confirmation
bun run build --client=easyLearning --mode=production # non-interactive, no prompts
```

Steps, in order: favicon, locales, `vite build`, `dist/version.json`, and the catalog when the client's
flag is on. Any failing step exits with a non-zero code.

---

## Writing stories

Stories live next to their component as `<Component>.stories.js` (CSF 3). Each file exports a `meta`
default with `title`, `component`, `args` and `argTypes`, and one named export per state worth showing.
Slot content is passed through a dedicated arg and rendered by a `render` function, since Storybook
controls cannot target slots directly.

Every story page gets an automatic **Docs** tab (`tags: ['autodocs']` in `.storybook/preview.js`) with the
prop table extracted from the component's `defineProps`, plus an **Accessibility** panel from
`@storybook/addon-a11y`.

Components that need the app's globals get them from `.storybook/preview.js`, which installs the same
plugin set as `src/core/main.js` through `installUiPlugins()` in `src/core/plugins/ui.plugins.js`, plus
Pinia, i18n and the client stylesheet. **Add any new global component or directive there**, not in
`main.js` alone, or stories will stop rendering.

---

## CI

- `build-storybook` runs on every merge request touching `frontend/`. A story that no longer compiles
  fails the pipeline.
- `pages` publishes the `default` client's catalog to GitLab Pages on every merge to the default branch,
  for the team. It documents the base template; client builds use the flag above instead.

Both jobs run on the `oven/bun:1` image, where `node` resolves to Bun. The Storybook build was verified
to work under that runtime.

---

## Decisions

| Decision | Why |
|---|---|
| Storybook, not a hand-rolled catalog route in the app | Industry standard for Vue; controls, autodocs, a11y and interaction testing come for free. A route inside the app would have to reimplement all of that. |
| Sibling static site, not bundled | Storybook is its own app with its own runtime. Bundling it would bloat every client build and expose tooling inside the product. |
| Flag in `settings.js`, read at build time | Client config is the project's one mechanism for per-client behaviour. Being merged into `window.$settings` also lets the app link to the catalog later. |
| Built per client via `VITE_CLIENT` | Same mechanism as the app, no second theming system. A runtime theme switcher is a possible later addition. |
| Storybook packages pinned to one exact version | Storybook requires all its packages to share a version; caret ranges can drift them apart. |
| Pages builds `default`, not a client | Pages documents the template for the team. Clients receive the catalog through their own build. |
