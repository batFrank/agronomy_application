# Composables del Core

Los composables del core (`src/core/composable/`) son funciones reutilizables independientes del dominio de negocio.

---

## `useRestModel` — CRUD genérico

Es el composable más importante del core. Encapsula el ciclo completo de un recurso REST: listar, obtener por id, crear, actualizar, parchear y eliminar.

```js
const {
  model,           // { isLoading, list, pagination }
  current,         // { inProcess, error, data }
  getAll,
  getById,
  save,
  create,
  update,
  patch,
  remove,
  setPagination,
  getItemsAsOptions,
} = useRestModel(BASE_URL, DTO)
```

### Parámetros
- `BASE_URL` — prefijo de la URL del recurso (ej. `'/admin/users'`)
- `DTO` — clase que extiende `FormModel`; si se pasa, los ítems del listado se instancian con `new DTO(item)`

### Estado reactivo

`model` contiene el estado del **listado**:
```js
model.isLoading    // true mientras getAll() está en vuelo
model.list         // array de ítems (opcionalmente instancias de DTO)
model.pagination   // instancia de Pagination
```

`current` contiene el estado del **ítem individual**:
```js
current.inProcess  // true mientras getById() está en vuelo
current.error      // error si la petición falló
current.data       // ítem cargado
```

### Métodos

```js
// Listado
getAll(payload, errorMessage, url)
// → GET BASE_URL?...payload&page&page_size (si paginación activa)

getItemsAsOptions(keyField, valueField)
// → [{ key, name }] para usar en <BaseSelect>

// CRUD
getById({ id, params, config, model, errorMessage, url })
save(model)          // llama create o update según model.id
create({ model, config, errorMessage })    // POST BASE_URL
update({ model, config, errorMessage })    // PUT BASE_URL/:id
patch({ model, payload, config, errorMessage }) // PATCH BASE_URL/:id
remove({ model, config, errorMessage })    // DELETE BASE_URL/:id

setPagination(pageNumber)  // cambia página y evita duplicados
```

### Prevención de doble petición
Todos los métodos comprueban `model.isLoading` o `model.inProcess` antes de ejecutar. Si ya hay una petición en vuelo, retornan `null` inmediatamente.

### Ejemplo de uso en un servicio
```js
export function useUsersService() {
  const { model: users, getAll, create, patch } = useRestModel('/admin/users', UserModel)

  async function fetchUsers() {
    await getAll()
  }

  async function saveUser(model) {
    if (validateForm(model)) return null
    return model.id ? await patch({ model, payload: model.getPayload() }) : await create({ model })
  }

  return { users, fetchUsers, saveUser }
}
```

---

## `useModal`

Gestiona el estado abierto/cerrado de un modal y los datos que muestra.

```js
const modal = useModal()

modal.isOpen   // Ref<boolean>
modal.data     // Ref<any>

modal.open(data)   // abre el modal con data (clona si es FormModel)
modal.close()      // cierra y limpia data
modal.setData(model)
```

Si se pasa un `FormModel`, `open()` llama `.clone()` para evitar mutar el original.

---

## `useFilters`

Gestiona un panel de filtros con estado abierto/cerrado.

```js
const filters = useFilters({
  filters: { search: '', status: '' },
  onReset: () => fetchData(),
})

filters.isOpen       // boolean
filters.available    // Ref con los valores actuales de cada filtro
filters.icon         // 'Filter' | 'FilterOff' (para el botón toggle)

filters.open() / close() / toggle()
filters.getFilters()  // retorna solo los filtros con valor; los de tipo boolean siempre se incluyen aunque sean false
filters.reset()       // limpia todos los valores y llama onReset
```

---

## `useTabs`

Gestiona un conjunto de pestañas con estado de pestaña activa.

```js
const { configTabs } = useTabs(
  tabsObj,      // object cuyas values son TabModel
  defaultTab,   // TabModel activa por defecto
  onChange,     // callback al cambiar de pestaña
)

configTabs.tabs        // array de TabModel
configTabs.current     // pestaña activa (shallowRef)
configTabs.setTab(tab) // cambia la pestaña activa
```

### Patrón avanzado: vistas por tab con props y acciones

`TabModel` soporta los campos `view`, `props` y `actions`, lo que permite que cada pestaña declare su propio componente Vue junto con las props que recibe y los emits que captura. Combinado con `<component :is>`, el resultado es un sistema de pestañas donde cada tab es una mini-vista autocontenida con su contrato de entrada y salida completamente declarado en el modelo.

```js
import { reactive, markRaw } from 'vue'
import useTabs from '@/core/composable/tabs.composable.js'
import TabModel from '@/core/models/tab.model.js'
import MainTab from './components/MainTab.vue'
import SecondTab from './components/SecondTab.vue'

const TABS = reactive({
  MAIN: markRaw(new TabModel({
    key: 'MAIN',
    title: 'Principal',
    view: MainTab,
    props: {
      items: myList,         // props reactivas que recibe el componente
    },
    actions: {
      onSelect: (item) => { /* captura el emit "select" de MainTab */ },
    },
  })),
  SECOND: markRaw(new TabModel({
    key: 'SECOND',
    title: 'Detalle',
    view: SecondTab,
    props: {
      detail: selectedItem,
    },
    actions: {
      onSave: (data) => { /* captura el emit "save" de SecondTab */ },
    },
  })),
})

const { configTabs } = useTabs(TABS, TABS.MAIN)
```

En el template, `BaseTab` renderiza el selector de pestañas y `<component :is>` monta dinámicamente la vista de la pestaña activa pasándole sus props y escuchando sus emits:

```html
<BaseTab
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

> `markRaw` es necesario para evitar que Vue intente hacer reactivo el componente Vue en sí, lo que causaría warnings y overhead innecesario.

Ventajas del patrón:
- La vista padre no necesita conocer los detalles de cada tab; solo delega en `configTabs.current`.
- Añadir una nueva pestaña es declarar un nuevo `TabModel` en `TABS`, sin tocar el template.
- Las props pueden ser refs reactivas, de forma que los cambios de estado se propagan automáticamente al componente de cada tab.

---

## `useViewportComposable`

Expone el tamaño de la ventana de forma reactiva, con un único listener de `resize` compartido entre todos los componentes que lo usen (patrón subscriber). Se suscribe al montar y elimina la suscripción al desmontar; cuando no quedan suscriptores, elimina también el event listener global.

```js
const viewport = useViewportComposable()

const isMobile = computed(() => viewport.value.width < 780)
```

---

## `useDebouncedRef`

Devuelve una `ref` que puede usarse como `v-model` normal. Una vez transcurrido el delay sin nuevos cambios, ejecuta el callback con el valor actualizado. Útil para búsquedas o filtros que no deben dispararse en cada pulsación de tecla.

```js
const search = useDebouncedRef(
  (value) => fetchData(value), // se ejecuta 500ms después del último cambio
  500,                          // delay en ms (default: 600)
  '',                           // valor inicial
)
```

```html
<BaseInput v-model="search" placeholder="Buscar..." />
```

---

## `useModalObserver` (`bodyLock.composable.js`)

Observa el DOM con `MutationObserver` y bloquea el scroll del `<body>` mientras haya un elemento con la clase `BaseModal` presente. Se usa internamente en el componente `BaseModal`, no es necesario llamarlo manualmente.

```js
useModalObserver('BaseModal')
```
