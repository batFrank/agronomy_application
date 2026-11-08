# Sistema Multi-Cliente

El proyecto incluye un mecanismo para compilar y servir la misma base de código como aplicaciones distintas para diferentes clientes, cada una con su propia configuración, estilos y vistas.

---

## Cómo funciona

### 1. Selección del cliente y entorno

Al ejecutar `bun run dev` o `bun run build`, se lanza un **asistente interactivo** en la terminal:

- **`bun run dev`** — pregunta: cliente a usar, entorno (ej. development/staging) y si activar el servidor de mocks (MirageJS). Una vez confirmadas las opciones levanta el servidor de desarrollo.
- **`bun run build`** — compila para producción. Acepta `--client=<nombre>` y `--mode=<production|dev>`; pregunta lo que falte.

Internamente el asistente establece la variable `VITE_CLIENT` con el cliente seleccionado. También puede pasarse directamente sin el asistente:

```bash
VITE_CLIENT=easyLearning bun run dev
VITE_CLIENT=easyLearning bun run build
```

Si `VITE_CLIENT` no está definida, la aplicación usa la configuración `default` de `src/contexts/shared/`.

### 2. Fusión de configuración

En `vite.config.js`, antes de compilar, se ejecuta `loadClientConfig(folderName)`:

```js
// src/core/scripts/clients.scripts.js
export async function loadClientConfig(folderName) {
  // 1. Importa el settings.js por defecto (src/contexts/shared/assets/js/settings.js)
  // 2. Busca en glob: src/clients/*/shared/assets/js/settings.js
  // 3. Si existe el del cliente, lo importa
  // 4. deepMerge(defaultConfig, clientConfig) → solo sobreescribe lo declarado
  return deepMerge(mainConfig.default, clientConfig.default)
}
```

La configuración fusionada se inyecta como constante global `__APP_SETTINGS__`, accesible en runtime como `window.$settings`.

### 3. Override de vistas

`LoaderService.importView(viewName)` busca la vista en este orden:
1. `src/clients/<FOLDER_NAME>/` — si existe una vista con ese nombre para el cliente activo, la usa.
2. `src/contexts/` — fallback al default.

Esto permite que un cliente tenga su propia versión de `LoginView.vue` sin tocar el original.

### 4. Override de imágenes

`LoaderService.importImage(imagePath)` sigue la misma lógica de prioridad:
1. `src/clients/<FOLDER_NAME>/shared/assets/images/`
2. `src/contexts/shared/assets/images/`

### 5. Override de estilos

`StylesPlugin` carga dinámicamente el SCSS del cliente activo, sobreponiendo los estilos sobre los globales.

---

## Estructura de un cliente

```
src/clients/<nombre>/
└── shared/
    └── assets/
        ├── js/
        │   └── settings.js    ← Overrides de configuración
        └── styles/
            └── globals.scss   ← Estilos específicos del cliente
```

Un cliente solo necesita declarar las claves que quiere sobreescribir en `settings.js`. El `deepMerge` en `loadClientConfig` se encarga de combinarlas con el default.

---

## Configuración por defecto (`src/contexts/shared/assets/js/settings.js`)

```js
export default {
  FOLDER_NAME: 'default',       // No cambiar
  TITLE: 'Vue App Title',
  DEFAULT_LOCALE: 'en',
  LOCALE_LIST_URL: '',
  META: {
    DESCRIPTION: 'Sample Vue3 app',
    TAGS: ['Vue3', 'App'],
  },
  STORAGE_PREFIX: '__default',
  AUTH_ACTIVE: true,
  AUTH: {
    PASSWORD_RECOVERY: true,
  },
  PAGES: {
    ENTRY_PAGE: 'login',         // Nombre de ruta para no autenticados
    AUTH_DEFAULT: {              // Ruta post-login por rol
      ROLE_USER: 'home',
      ROLE_MANAGER: 'admin',
      ROLE_ADMIN: 'admin',
    },
    ENABLED: {
      WELCOME: false,            // Activar pantalla de bienvenida
    },
  },
  SHOW_HEADER: true,
  SHOW_FOOTER: true,
  BUILD_COMPONENT_CATALOG: false, // Emit the Storybook catalog in dist/storybook on build
}
```

---

## Ejemplo: cliente `easyLearning`

`src/clients/easyLearning/shared/assets/js/settings.js`:

```js
export default {
  FOLDER_NAME: 'easyLearning',
  TITLE: 'easyLearning',
  PAGES: {
    AUTH_DEFAULT: {
      ROLE_USER: 'home',
      ROLE_MANAGER: 'admin',
      ROLE_ADMIN: 'admin',
    },
    ENABLED: {
      WELCOME: true,             // Activa la pantalla de bienvenida
    },
  },
  BUILD_COMPONENT_CATALOG: true, // Ships the Storybook catalog at /app/storybook/
  SENTRY: {
    DSN: 'https://...',
    TRACES_SAMPLE_RATE: 1.0,
  },
}
```

Este cliente sobreescribe solo `FOLDER_NAME`, `TITLE`, `PAGES.ENABLED.WELCOME`, `BUILD_COMPONENT_CATALOG` y `SENTRY`. El resto hereda del default.

---

## Crear un nuevo cliente

```bash
bun run new-client
```

El script `clientsFactory.scripts.js` lanza un asistente interactivo (usando `enquirer`) que:
1. Solicita el nombre del cliente.
2. Crea la estructura de carpetas en `src/clients/<nombre>/`.
3. Genera un `settings.js` base para personalizar.

---

## `window.$settings` en runtime

En tiempo de ejecución, toda la configuración está disponible globalmente en `window.$settings`. Las claves más usadas son:

| Clave | Uso |
|---|---|
| `$settings.FOLDER_NAME` | Identificar el cliente activo en `LoaderService` |
| `$settings.AUTH_ACTIVE` | Activar/desactivar el sistema de autenticación |
| `$settings.PAGES.ENTRY_PAGE` | Ruta de entrada para no autenticados |
| `$settings.PAGES.AUTH_DEFAULT` | Mapa rol → ruta de aterrizaje post-login |
| `$settings.SHOW_HEADER` | Mostrar/ocultar header globalmente |
| `$settings.SHOW_FOOTER` | Mostrar/ocultar footer globalmente |
| `$settings.SENTRY.DSN` | DSN de Sentry para el cliente |
| `$settings.BASE_DIR` | Prefijo de URL (inyectado por Vite desde `VITE_BASE_URL`) |
| `$settings.START_MOCK_SERVER` | `"true"` activa MirageJS |
