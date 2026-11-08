# Core — Núcleo del Framework

La carpeta `src/core` contiene toda la infraestructura reutilizable del proyecto: configuración, plugins, servicios, modelos base, composables genéricos y utilidades.

---

## Configuraciones (`src/core/configs/`)

### `site.config.js`
Aplica `<title>` y meta-tags al documento HTML al arrancar la aplicación.

```js
initSiteData()
// Lee window.$settings.TITLE, META.TAGS, META.DESCRIPTION
```

### `axios.config.js`
Crea y exporta la instancia Axios configurada con:
- `baseURL` tomada de `VITE_API_URL` (o `${origin}/api` por defecto).
- **Interceptor de request**: adjunta `Authorization: Bearer <token>` excepto en `PUBLIC_ENDPOINTS`.
- **Interceptor de response**:
  - `401` → intenta refresh token una vez; si falla, hace logout.
  - `404` → lanza `ConnectionError`.
  - `>= 500` → lanza `ServerError`.
  - Otros errores → lanza `ApiError`.

### `i18n.config.js`
Expone un glob de archivos JSON de traducciones:
- `LOCALES.default` → `src/core/locales/*.json`
- `LOCALES.clients` → `src/core/locales/*/*.json`

### `loader.config.js`
Expone globs de vistas, imágenes y componentes de layout para el sistema de carga dinámica:
- `VIEWS.default` / `VIEWS.clients`
- `IMAGES.default` / `IMAGES.clients`
- `APP_COMPONENT` (`AppHeader`, `AppFooter`)

### `icons.config.js`
Configuración de FontAwesome (importación de iconos solid y regular).

### `mocks.config.js`
Configura MirageJS para interceptar peticiones API en desarrollo.

---

## Plugins (`src/core/plugins/`)

Cada plugin expone un método `install(app)` siguiendo el patrón Vue.

| Plugin | Responsabilidad |
|---|---|
| `router.plugin.js` | Instala Vue Router y retorna la instancia |
| `store.plugin.js` | Instala Pinia |
| `i18n.plugin.js` | Carga mensajes y activa vue-i18n |
| `styles.plugin.js` | Carga dinámicamente el SCSS del cliente activo |
| `icons.plugin.js` | Registra el componente `FontAwesomeIcon` globalmente |
| `toast.plugin.js` | Configura vue3-toastify |
| `datetime.plugin.js` | Registra `VueDatePicker` globalmente |
| `sentry.plugin.js` | Inicializa Sentry con DSN de `window.$settings.SENTRY` |
| `mocks.plugin.js` | Levanta el servidor MirageJS (solo si `START_MOCK_SERVER`) |
| `directives.plugin.js` | Registra directivas Vue personalizadas |
| `ui.plugins.js` | `installUiPlugins(app)`: icons, toast, datetime y directivas en un solo lugar, compartido por `main.js` y el catálogo de componentes (Storybook) |

---

## Servicios (`src/core/services/`)

### `ApiService`
Servicio estático para todas las peticiones HTTP. Envuelve la instancia Axios y normaliza las respuestas.

```js
// Respuesta siempre con forma: { error, data, metadata, ... }
ApiService.get(url, params, config)
ApiService.post(url, data, config)
ApiService.put(url, data, config)
ApiService.patch(url, data, config)
ApiService.delete(url, config)
```

Si hay un error de red o del servidor, `error` contendrá una instancia de `ConnectionError`, `ServerError` o `ApiError`. El código consumidor solo necesita chequear `if (error)`.

### `StorageService`
Gestiona tokens y preferencias en **cookies** (tokens JWT) y `localStorage` (locale).

```js
StorageService.setToken(value)
StorageService.getToken()
StorageService.setRefreshToken(value)
StorageService.getRefreshToken()
StorageService.setLocale(value)
StorageService.getLocale()
```

Las cookies de token se crean con `path=/` y expiración de 1 día (`ONE_DAY_IN_MS`).

### `LoaderService`
Resuelve vistas, componentes e imágenes dinámicamente teniendo en cuenta el cliente activo.

```js
LoaderService.importView('AdminView')     // Resuelve la vista correcta para el cliente activo
LoaderService.importComponent('AppHeader')
LoaderService.importImage('images/logo')
```

La lógica de resolución prioriza el override del cliente sobre el default, y dentro del default usa `findLast` para que un path más específico tenga precedencia.

### `moduleLoader.service.js`
Orquesta el registro de módulos en el router y la construcción del menú:

```js
buildMenu(app, router, currentUser, onReady)
// → itera MODULE_REGISTRY
// → añade rutas al router (filtradas por rol)
// → llama plugin.install(app) de cada módulo
// → retorna { menu, footer } listos para renderizar
```

### `menuLoader.services.js`
Construye los arrays del menú y footer aplicando filtrado por roles y ordenamiento.

- `buildNavMenu(allPluginMenus, userRoles)` → soporta ítems con `subItems`, deduplica por `id`, filtra carpetas vacías.
- `buildFooterMenu(allPluginFooters, userRoles)` → igual pero sin subítems.

### `loader.service.js`
Ver sección Configuraciones > `loader.config.js`.

---

## Modelos base (`src/core/models/`)

### `FormModel`
Clase base que deben extender todos los modelos de formulario.

```js
class FormModel {
  id           // string|number
  key          // string único generado automáticamente
  inProcess    // boolean — previene doble submit
  errors       // object { campo: mensaje }
  originalData // datos originales del constructor

  setContent(data)         // override: mapea campos del DTO
  getPayload()             // override: retorna objeto para enviar a la API
  getValidationSchema()    // override: retorna schema Joi
  clone()                  // crea nueva instancia con originalData
  setErrors(errors)        // asigna errores despues de las validaciones
  getFirstError()          // obtener el primer error disponible
}
```

### `Pagination`
Encapsula el estado de paginación de un listado.

```js
new Pagination({ page, limit, total, total_pages, numberOfChips, active })

pagination.getPayloadData()   // → { page, page_size }
pagination.setCurrentPage(n)
pagination.getNextPage()
pagination.getPrevPage()
```

### `CurrentUserModel`
Modelo del usuario autenticado, inicializado leyendo el payload del JWT almacenado en cookie.

```js
currentUser.id
currentUser.username
currentUser.roles        // string[]
currentUser.isAuth       // boolean
currentUser.role         // rol principal según ROLE_HIERARCHY
currentUser.authDefault  // nombre de ruta post-login según settings

currentUser.isGranted(roles)  // guard de UI (no sustituye al backend)
currentUser.initData()         // re-lee el JWT (útil tras login/refresh)
```

### `ModuleModel`
Estructura que debe exportar cada módulo para registrarse en la app.

```js
new ModuleModel({
  name,        // string — identificador único
  routes,      // object con rutas { KEY: routeConfig }
  menuItems,   // MenuItemModel[]
  footerItems, // FooterItemModel[]
  install,     // función opcional que recibe (app) para instalar cosas extra
})
```

### `error.model.js`
Clases de error tipadas para el sistema HTTP:

- `ApiError` — error de negocio devuelto por la API (4xx)
- `ConnectionError` — error de red o 404
- `ServerError` — error interno del servidor (5xx)
- `ValidationError` — error de validación de formulario (incluye `errorKey`)

---

## Constantes (`src/core/constants/`)

### `roles.constants.js`
```js
ROLES = { ADMIN: 'ROLE_ADMIN', MANAGER: 'ROLE_MANAGER', USER: 'ROLE_USER' }
ROLE_HIERARCHY = [ROLE_ADMIN, ROLE_MANAGER, ROLE_USER]  // de mayor a menor privilegio
```

### `general.constant.js`
```js
API_URL            // Resuelto desde VITE_API_URL o ${origin}/api
BASE_LOCATION      // Origen de la aplicación
REFRESH_TOKEN_URL  // '/auth/refresh'
PUBLIC_ENDPOINTS   // ['/auth/login', '/auth/logout', '/auth/refresh']
ONE_DAY_IN_MS      // 86400
HTACCESS_TEMPLATE  // Plantilla .htaccess para despliegues Apache
```

---

## Utilidades (`src/core/utils/`)

### `misc.utils.js`
```js
objToGetString(params)           // Convierte objeto a query string ?k=v&…
stringToJson(data, defaultValue) // JSON.parse seguro con fallback
getBrowserLocales()              // Detecta idiomas del navegador
deepMerge(target, source)        // Merge profundo inmutable
deepObjectReplace(base, partial) // Merge profundo mutando base
validateAccess(requiredRoles, userRoles) // true si el usuario tiene acceso
```

### `validator.utils.js` (core)
```js
validateForm(model, abortEarly)
// Ejecuta getValidationSchema() + getPayload() del FormModel
// Si hay errores, los aplica con model.setErrors()
// Retorna true si hay errores
```

### `storage.utils.js`
Operaciones sobre `localStorage` con prefijo de aplicación.

### `number.utils.js`
Utilidades de formateo numérico.

### `compile.utils.js` / `server.utils.js`
Utilidades usadas en los scripts de build y dev server.

---

## Directivas (`src/core/directives/`)

### `clickOutside.js`
Directiva `v-click-outside` que ejecuta un callback cuando el usuario hace clic fuera del elemento.

---

## Estado global de menú (`src/core/state/menu.state.js`)

```js
// Promesa que se resuelve cuando el menú termina de construirse
export const menuReady   // Promise
export const menuState   // { isReady: boolean }
export const resolveMenu // función que resuelve la promesa
```

Usado por el guard del router (`catchAll`) para esperar a que las rutas estén registradas antes de redirigir.
