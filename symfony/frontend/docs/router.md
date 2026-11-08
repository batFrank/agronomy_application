# Router y Navegación

## Configuración base (`src/core/router/index.js`)

El router se crea con `createWebHistory` usando `BASE_DIR` como prefijo de URL:

```js
createRouter({
  history: createWebHistory(`/${window.$settings.BASE_DIR}/`),
  routes: [{ path: '/:pathMatch(.*)*', name: 'catchAll', component: ... }]
})
```

Inicialmente solo existe la ruta `catchAll`. Las rutas de cada módulo se añaden dinámicamente durante `buildMenu()` al arrancar la aplicación, mediante `router.addRoute()`.

---

## Carga dinámica de rutas

Las rutas no se declaran en un archivo central. Cada módulo define sus propias rutas en su archivo de constantes y las expone en su `ModuleModel`:

```js
// src/contexts/admin/constants/admin.constants.js
export const ADMIN_ROUTES = {
  HOME: {
    path: '/admin',
    name: 'admin',
    view: 'AdminView',       // nombre de la vista a resolver con LoaderService
    meta: {
      requiresAuth: true,
      roles: [ROLES.ADMIN, ROLES.MANAGER],
      hideFooter: true,
    },
  },
}
```

Durante `buildMenu()`, para cada ruta del módulo se llama `routeFactory(route)`, que:
1. Resuelve la vista con `LoaderService.importView(view)`.
2. Decide si incluir header/footer según `window.$settings.SHOW_HEADER/FOOTER` y `meta.hideHeader/hideFooter`.
3. Devuelve un objeto de ruta con `components` nombrados (`default`, `header`, `footer`).

Solo se añaden al router las rutas para las que el usuario tiene los roles requeridos (`validateAccess`).

---

## Meta propiedades de ruta

| Propiedad | Tipo | Descripción |
|---|---|---|
| `isPublic` | `boolean` | La ruta es accesible sin autenticar (por defecto `false`) |
| `requiresAuth` | `boolean` | Redirige al ENTRY_PAGE si no está autenticado |
| `hideHeader` | `boolean` | Oculta el header en esta ruta |
| `hideFooter` | `boolean` | Oculta el footer en esta ruta |
| `roles` | `string[]` | Roles que pueden acceder; vacío = todos |
| `withHeader` | `boolean` | Calculado automáticamente por `routeFactory` |
| `withFooter` | `boolean` | Calculado automáticamente por `routeFactory` |

---

## Guard de navegación (`src/core/router/guards.js`)

El guard `beforeEach` ejecuta `initValidations(to, router)`:

```
¿La ruta es 'catchAll'?
├─ ¿El menú aún no está listo? → espera menuReady y redirige a to.fullPath
├─ ¿No autenticado? → redirige a ENTRY_PAGE
└─ ¿Autenticado? → redirige a currentUser.authDefault

¿AUTH_ACTIVE está desactivado o la ruta es pública (isPublic)?
└─ Permite el acceso sin más comprobaciones

¿No autenticado?
└─ requiresAuth → redirige a ENTRY_PAGE
└─ no requiresAuth → permite (es una ruta pública como login)

¿Autenticado y la ruta no requiere auth (ej. /login)?
└─ Redirige a currentUser.authDefault
```

Este guard garantiza que usuarios autenticados no accedan a páginas de login, y que usuarios sin autenticar no accedan a rutas protegidas.

---

## Layout con componentes nombrados

El layout principal (`DefaultLayout.vue`) usa `<router-view name="header">` y `<router-view name="footer">` para renderizar las partes del layout condicionalmente. Esto permite que cada ruta declare si quiere header y footer o no, sin necesidad de wrappers adicionales.

---

## Rutas registradas por defecto

| Nombre | Path | Protegida | Roles |
|---|---|---|---|
| `login` | `/login` | No | — |
| `home` | `/home` | Sí | Todos los roles |
| `admin` | `/admin` | Sí | ADMIN, MANAGER |
| `password-recovery` | (ver constantes) | No | — |
| `password-reset/:token` | (ver constantes) | No | — |
| `welcome` | (ver constantes) | No | — |
