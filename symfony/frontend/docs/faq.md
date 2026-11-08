# FAQ — Preguntas frecuentes

---


## ¿Cómo creo un módulo nuevo?

1. Crea la carpeta `src/contexts/<nombre>/`.
2. Define las rutas en `constants/<nombre>.constants.js`:

```js
import { ROLES } from '@/core/constants/roles.constants.js'

export const MY_ROUTES = {
  HOME: {
    path: '/my-module',
    name: 'my-module',
    view: 'MyModuleView',
    meta: { requiresAuth: true, roles: [ROLES.ADMIN] },
  },
}
```

3. Crea la vista en `views/MyModuleView.vue` (solo template y estilos).
4. Crea el composable en `composables/MyModuleView.composable.js` (toda la lógica).
5. Crea `index.js` exportando el `ModuleModel`:

```js
import ModuleModel from '@/core/models/module.model.js'
import { MY_ROUTES } from './constants/my.constants.js'

export default new ModuleModel({
  name: 'MyModule',
  routes: MY_ROUTES,
  menuItems: [],
  footerItems: [],
})
```

No hace falta registrar nada más. Al reiniciar el servidor, el módulo aparece automáticamente gracias al glob de `src/contexts/registry.js`.

---

## ¿Cómo añado y configuro un nuevo cliente?

Ejecuta el asistente interactivo:

```bash
bun run new-client
```

Esto crea la estructura base en `src/clients/<nombre>/`. Luego edita `src/clients/<nombre>/shared/assets/js/settings.js` declarando solo las claves que quieres sobreescribir respecto al default:

```js
export default {
  FOLDER_NAME: 'mi-cliente',   // obligatorio, debe coincidir con el nombre de la carpeta
  TITLE: 'Mi Cliente',
  PAGES: {
    ENABLED: { WELCOME: true },
  },
  SENTRY: {
    DSN: 'https://...',
  },
}
```

Para arrancar el dev server con ese cliente, ejecuta `bun run dev` y selecciona el cliente en el asistente interactivo. Para compilar, `bun run build` y selecciona el entorno.

---

## ¿Cómo sobreescribo una vista para un cliente?

Crea el mismo archivo de vista dentro de la carpeta del cliente, manteniendo la ruta relativa equivalente:

```
src/clients/<nombre>/contexts/<contexto>/views/<Vista>.vue
```

`LoaderService` prioriza automáticamente la vista del cliente sobre la del default. En esa vista, importa el composable original para no duplicar lógica:

```js
// src/clients/acme/contexts/challenges/views/ChallengesView.vue
import { useChallengesView } from '@/contexts/challenges/composables/ChallengesView.composable.js'

const { ... } = useChallengesView()
```

Si además el cliente necesita comportamiento diferente, crea también un composable propio en `src/clients/<nombre>/` que importe y extienda el composable base.

---

## ¿Cómo uso `useRestModel`?

`useRestModel` encapsula el CRUD completo de un recurso REST. Se usa normalmente dentro de un service del contexto:

```js
// src/contexts/products/services/products.service.js
import useRestModel from '@/core/composable/restModel.composable.js'
import ProductModel from '@/contexts/products/models/product.model.js'

export function useProductsService() {
  const { model: products, getAll, save, remove } = useRestModel('/products', ProductModel)

  async function fetchProducts(filters = {}) {
    await getAll(filters, 'Error cargando productos')
  }

  async function saveProduct(model) {
    return await save(model)  // create si no tiene id, update si tiene
  }

  async function deleteProduct(model) {
    return await remove({ model })
  }

  return { products, fetchProducts, saveProduct, deleteProduct }
}
```

Consumo desde el composable de la vista:

```js
const { products, fetchProducts, saveProduct } = useProductsService()

// products.isLoading  → boolean
// products.list       → array de ProductModel
// products.pagination → instancia de Pagination

onMounted(() => fetchProducts())
```

Para paginación activa, actívala antes de llamar a `getAll`:

```js
products.pagination.active = true
await getAll({ search: 'abc' })
// la petición incluirá automáticamente page y page_size
```

---

## ¿Cuándo uso un store en lugar de un composable?

Usa un **composable** cuando el estado pertenece a una vista o a un flujo concreto y no necesita sobrevivir fuera de él.

Usa un **store de Pinia** cuando el estado debe ser compartido entre contextos o componentes no relacionados en el árbol de componentes, y necesita persistir mientras la aplicación está activa. Ejemplos válidos: sesión del usuario autenticado, idioma seleccionado, notificaciones globales.

---

## ¿Por qué mi ruta no aparece después de añadirla?

Las rutas se registran dinámicamente al arrancar la aplicación. Comprueba estos puntos en orden:

1. El `index.js` del módulo exporta un `ModuleModel` con `name` definido.
2. La ruta está declarada en el objeto `routes` del `ModuleModel`.
3. El campo `view` de la ruta coincide exactamente con el nombre del archivo de vista (sin extensión).
4. El usuario autenticado tiene los roles requeridos en `meta.roles` (si está vacío, todos tienen acceso).
5. Si usas el dev server, reinícialo después de crear archivos nuevos para que el glob los detecte.

---

## ¿Cómo añado un ítem al menú?

En el `index.js` del módulo, añade instancias de `MenuItemModel` en el array `menuItems`:

```js
import { MenuItemModel } from '@/contexts/layout/models/MenuItem.model.js'

menuItems: [
  new MenuItemModel({
    id: 'my-module',       // debe ser único entre todos los módulos
    title: 'Mi Módulo',
    link: MY_ROUTES.HOME.path,
    icon: 'house',         // nombre de icono FontAwesome
    order: 1,              // menor número = aparece antes
    roles: [ROLES.ADMIN],  // vacío = visible para todos
  }),
]
```

Para un ítem con submenú desplegable, usa `subItems`:

```js
new MenuItemModel({
  id: 'mi-grupo',
  title: 'Grupo',
  icon: 'bars',
  order: 2,
  subItems: [
    new MenuItemModel({ id: 'sub-1', title: 'Opción 1', link: '/ruta-1' }),
    new MenuItemModel({ id: 'sub-2', title: 'Opción 2', link: '/ruta-2' }),
  ],
})
```

---

## ¿Puedo agrupar ítems de menú de distintos módulos bajo una misma sección?

Sí. `buildNavMenu` fusiona automáticamente los ítems que comparten el mismo `id`: si ya existe un ítem padre con ese id, los `subItems` del nuevo se añaden a los del existente en lugar de crear un duplicado.

Esto permite que varios módulos independientes contribuyan sus propias entradas a una sección visual compartida del menú, sin que ningún módulo conozca a los demás.

El patrón recomendado es declarar la configuración del ítem padre en un objeto compartido y extenderlo en cada módulo:

```js
// Puede vivir en src/contexts/shared o en cada módulo si se prefiere
const CATALOG_MENU_ITEM = {
  id: 'catalogs',
  title: 'Catálogos',
  icon: 'book',
  order: 2,
}

// context/superadmin/index.js
new MenuItemModel({
  ...CATALOG_MENU_ITEM,
  subItems: [
    new MenuItemModel({
      id: 'superadmin-config',
      title: 'Configuraciones Superadmin',
      link: '/superadmin/configuraciones',
      roles: [ROLES.ADMIN],
    }),
  ],
})

// context/products/index.js
new MenuItemModel({
  ...CATALOG_MENU_ITEM,
  subItems: [
    new MenuItemModel({ id: 'products', title: 'Productos', link: '/products' }),
  ],
})

// context/users/index.js
new MenuItemModel({
  ...CATALOG_MENU_ITEM,
  subItems: [
    new MenuItemModel({ id: 'users', title: 'Usuarios', link: '/users' }),
  ],
})
```

El resultado en el menú será una única sección "Catálogos" con los tres subítems consolidados:

```
Catálogos
├── Configuraciones Superadmin  (solo ROLE_ADMIN)
├── Productos
└── Usuarios
```

Cada subítem puede tener sus propios `roles`, de forma que el filtrado por rol se aplica individualmente. Si un usuario no tiene acceso a ningún subítem de la sección, la carpeta padre desaparece del menú automáticamente.

---

## ¿Cómo desactivo el header o el footer en una ruta concreta?

En la definición de la ruta, añade `hideHeader: true` y/o `hideFooter: true` en `meta`:

```js
export const MY_ROUTES = {
  HOME: {
    path: '/my-page',
    name: 'my-page',
    view: 'MyView',
    meta: {
      requiresAuth: false,
      hideHeader: true,
      hideFooter: true,
    },
  },
}
```

`routeFactory` lee estas flags y decide si incluir o no los componentes `header` y `footer` en la ruta. El valor de `window.$settings.SHOW_HEADER` y `SHOW_FOOTER` actúa como interruptor global: si está en `false`, el header o footer no se muestra en ninguna ruta independientemente de `meta`.

---

## ¿Cómo uso el composable de modales (`useModal`)?

```js
import { useModal } from '@/core/composable/modal.composable.js'

const userModal = useModal()

// Abrir con datos (si es un FormModel, se clona automáticamente)
userModal.open(new UserModel(data))

// Acceder a los datos dentro del modal
userModal.data   // el objeto pasado a open()
userModal.isOpen // boolean reactivo

// Cerrar y limpiar
userModal.close()
```

En el template:

```html
<BaseModal :is-open="userModal.isOpen" @close="userModal.close">
  <UserForm :model="userModal.data" @submit="submitData" />
</BaseModal>
```

---

## ¿Cómo uso el composable de pestañas (`useTabs`)?

### Uso básico

```js
import useTabs from '@/core/composable/tabs.composable.js'
import TabModel from '@/core/models/tab.model.js'

const TABS = {
  INFO: new TabModel({ key: 'INFO', title: 'Información' }),
  HISTORY: new TabModel({ key: 'HISTORY', title: 'Historial' }),
}

const { configTabs } = useTabs(TABS, TABS.INFO)
```

```html
<BaseTabs :current="configTabs.current" :tabs="configTabs.tabs" @change="configTabs.setTab" />

<div v-if="configTabs.current.key === 'INFO'">...</div>
<div v-if="configTabs.current.key === 'HISTORY'">...</div>
```

### Uso avanzado: componente por pestaña con props y acciones

Cada tab puede declarar su propio componente Vue, las props que recibe y los emits que captura. Esto elimina los `v-if` del template y hace que cada pestaña sea completamente autocontenida:

```js
import { reactive, markRaw } from 'vue'
import TabModel from '@/core/models/tab.model.js'
import useTabs from '@/core/composable/tabs.composable.js'
import InfoTab from './components/InfoTab.vue'
import HistoryTab from './components/HistoryTab.vue'

const TABS = reactive({
  INFO: markRaw(new TabModel({
    key: 'INFO',
    title: 'Información',
    view: InfoTab,
    props: { item: selectedItem },       // props reactivas → se actualizan automáticamente
    actions: {
      onEdit: (data) => openEditModal(data),  // captura emit "edit" de InfoTab
    },
  })),
  HISTORY: markRaw(new TabModel({
    key: 'HISTORY',
    title: 'Historial',
    view: HistoryTab,
    props: { itemId: selectedItem.id },
    actions: {},
  })),
})

const { configTabs } = useTabs(TABS, TABS.INFO)
```

```html
<BaseTabs
  :current="configTabs.current"
  :tabs="configTabs.tabs"
  @change="configTabs.setTab"
/>
<component
  :is="configTabs.current.view"
  v-bind="configTabs.current.props"
  v-on="configTabs.current.actions"
/>
```

> Usa siempre `markRaw` al envolver componentes Vue en un objeto reactivo para evitar que Vue los haga reactivos internamente.
