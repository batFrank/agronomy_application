# Sistema de Módulos

## Registro automático

Los módulos se descubren automáticamente en tiempo de build gracias a `import.meta.glob`:

```js
// src/contexts/registry.js
const modules = import.meta.glob('./*/index.js', { eager: true })

export const MODULE_REGISTRY = {}
Object.values(modules).forEach((module) => {
  const plugin = module.default
  if (plugin?.name) MODULE_REGISTRY[plugin.name] = plugin
})
```

Cualquier carpeta dentro de `src/contexts/` que contenga un `index.js` exportando un `ModuleModel` con `name` queda registrada sin ninguna configuración manual adicional.

---

## Estructura de un módulo

Cada módulo sigue esta estructura de carpetas:

```
src/contexts/<nombre>/
├── index.js              ← Registro del módulo (obligatorio)
├── constants/
│   └── <nombre>.constants.js   ← Definición de rutas
├── views/
│   └── <Nombre>View.vue
├── composables/
│   └── <Nombre>View.composable.js
├── services/             ← (si tiene llamadas a API)
│   └── <nombre>.service.js
├── models/               ← (si tiene FormModel propio)
│   └── <nombre>.model.js
└── schemas/              ← (si tiene validaciones Joi)
    └── <nombre>.schema.js
```

### `index.js` — ejemplo mínimo

```js
import ModuleModel from '@/core/models/module.model.js'
import { MY_ROUTES } from './constants/my.constants.js'
import { MenuItemModel } from '@/contexts/layout/models/MenuItem.model.js'

export default new ModuleModel({
  name: 'MyModule',          // Debe ser único
  routes: MY_ROUTES,
  menuItems: [
    new MenuItemModel({
      id: MY_ROUTES.HOME.name,
      title: 'Mi Módulo',
      link: MY_ROUTES.HOME.path,
      roles: MY_ROUTES.HOME.meta.roles,
      order: 1,
    }),
  ],
  footerItems: [],
  install: (app) => {
    // Opcional: instalar componentes, providers, etc.
  },
})
```

---

## Módulos existentes

### `Login`
- **Ruta**: `GET /login`
- **Vista**: `LoginView.vue`
- **Meta**: pública, sin header ni footer
- **Composable**: `useLoginView` — gestiona el formulario de login, validación y redirección post-login.
- No registra ítems en el menú ni en el footer.

### `PasswordRecovery`
- **Ruta**: (ver `passwordRecovery.constants.js`)
- **Vista**: `PasswordRecoveryView.vue`
- **Meta**: pública
- **Composable**: `usePasswordRecoveryView` — formulario de solicitud de recuperación con flujo de dos estados (`recover` → `success`).

### `PasswordReset`
- **Ruta**: incluye parámetro `:token`
- **Vista**: `PasswordResetView.vue`
- **Meta**: pública
- **Composable**: `usePasswordResetView` — verifica el token al montar, gestiona el formulario de nueva contraseña. Estados: `reset`, `success`, `invalid`.

### `Welcome`
- **Vista**: `WelcomeView.vue`
- Se habilita mediante `window.$settings.PAGES.ENABLED.WELCOME = true`.
- Sirve como pantalla de bienvenida antes de la autenticación.

### `Home`
- **Ruta**: `GET /home` — protegida, todos los roles
- **Vista**: `HomeView.vue`
- **Composable**: `useHomeView`
- Registra un ítem de menú con subítems de ejemplo.

### `Admin`
- **Ruta**: `GET /admin` — protegida, solo `ROLE_ADMIN` y `ROLE_MANAGER`
- **Vista**: `AdminView.vue`
- **Composable**: `useAdminView`
- Incluye gestión de usuarios con tabla, paginación y modal de creación/edición.

#### Servicio de usuarios (`useUsersService`)
```js
const { users, fetchUsers, setPage, saveUser } = useUsersService()
```
- `users.list` — array de `UserModel`
- `fetchUsers()` — `GET /admin/users`
- `saveUser(model)` — crea (`POST`) o actualiza (`PATCH`) según si el modelo tiene `id`
- `setPage(n)` — cambia de página y recarga

#### `UserModel`
Extiende `FormModel`. Campos: `id`, `email`, `roles`, `password`, `password_confirmation`.

Schemas de validación:
- **Creación**: email + password (mín. 6 chars) + confirmación + roles (mín. 1)
- **Edición**: email + roles (sin campos de contraseña)

### `Layout` (no es un módulo de negocio)
No se registra en `MODULE_REGISTRY`. Contiene los componentes estructurales de la app:
- `DefaultLayout.vue` — layout raíz con slots para header, default y footer
- `AppHeader.vue` — barra de navegación superior
- `AppFooter.vue` — pie de página
- `ResponsiveMenu.vue` / `MenuItem.vue` — menú responsivo con soporte de subítems

---

## Crear un nuevo módulo — pasos

1. Crear la carpeta `src/contexts/<nombre>/`.
2. Crear `constants/<nombre>.constants.js` con la definición de rutas.
3. Crear `views/<Nombre>View.vue`.
4. Crear `composables/<Nombre>View.composable.js` (lógica de la vista).
5. Crear `index.js` exportando un `new ModuleModel({ name, routes, menuItems, ... })`.
6. **No hace falta registrar nada más** — el glob lo detecta automáticamente al reiniciar el servidor.

---

## MenuItemModel

```js
new MenuItemModel({
  id,          // number|string — debe ser único entre todos los módulos
  title,       // string — texto visible
  link,        // string — path interno o URL externa
  icon,        // string — nombre del icono FontAwesome
  order,       // number — orden en el menú (menor = primero)
  roles,       // string[] — roles que ven este ítem
  subItems,    // MenuItemModel[] — ítems hijo
})
```

Si `subItems` está vacío, el ítem es una hoja (`isFolder = false`). Si hay subítems, actúa como carpeta desplegable.

### Fusión de subítems entre módulos

Cuando dos o más módulos declaran un `MenuItemModel` con el mismo `id`, `buildNavMenu` los fusiona en un único ítem padre consolidando todos sus `subItems`. Esto permite que módulos independientes contribuyan entradas a una misma sección del menú sin conocerse entre sí. Ver el ejemplo completo en [faq.md](./faq.md#puedo-agrupar-ítems-de-menú-de-distintos-módulos-bajo-una-misma-sección).

## FooterItemModel

```js
new FooterItemModel({
  id, title, link,
  roles,   // filtrado igual que MenuItemModel
  order,
})
```
