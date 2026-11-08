# Arquitectura General

## Capas de la aplicación

El proyecto se organiza en tres capas bien diferenciadas:

```
┌─────────────────────────────────────────────────────┐
│  src/clients/<nombre>/          ← Override por cliente│
│  (vistas, estilos, settings)                         │
├─────────────────────────────────────────────────────┤
│  src/contexts/                  ← Lógica de negocio  │
│  (módulos: admin, login, home, layout, shared…)      │
├─────────────────────────────────────────────────────┤
│  src/core/                      ← Framework interno  │
│  (router, servicios, composables, modelos, utils)    │
└─────────────────────────────────────────────────────┘
```

- **`src/core`** no conoce nada de negocio. Provee las herramientas genéricas.
- **`src/contexts`** contiene los módulos de negocio. Cada módulo se registra automáticamente.
- **`src/clients`** permite personalizar cualquier contexto sin modificar el código base.

---

## Flujo de arranque (`main.js`)

El arranque de la aplicación sigue estos pasos en orden:

```
1. settings-init.js
   └─ Expone window.$settings desde la constante __APP_SETTINGS__ (inyectada por Vite)

2. initSiteData()
   └─ Aplica title y meta-tags al documento HTML

3. initMockServer()  [condicional]
   └─ Si START_MOCK_SERVER === 'true', levanta MirageJS

4. createApp(App)

5. Instalación de plugins:
   RouterPlugin → SentryPlugin → StorePlugin → IconsPlugin
   ToastPlugin → vueDatePicker → I18nPlugin → StylesPlugin → Directives

6. buildMenu(app, router, currentUser, resolveMenu)
   └─ Itera MODULE_REGISTRY
   └─ Añade rutas al router (con control de roles)
   └─ Construye menú y footer filtrados por rol

7. app.provide() → expone currentUser, menuItems, footerItems a toda la app

8. app.mount('#app')
```

---

## Configuración en tiempo de build (`vite.config.js`)

Vite inyecta la configuración del cliente como constante global `__APP_SETTINGS__`:

```js
// vite.config.js
define: {
  __APP_SETTINGS__: { ...clientConfig, BASE_DIR, START_MOCK_SERVER }
}
```

La función `loadClientConfig(folderName)` (en `src/core/scripts/clients.scripts.js`) carga el `settings.js` del cliente indicado en `VITE_CLIENT` y lo fusiona (deep merge) con el `settings.js` por defecto de `src/contexts/shared/assets/js/settings.js`.

Esto significa que el cliente solo necesita declarar las claves que quiere sobreescribir.

---

## Sistema de módulos (registro automático)

Los módulos se auto-descubren mediante `import.meta.glob`:

```js
// src/contexts/registry.js
const modules = import.meta.glob('./*/index.js', { eager: true })
```

Cualquier carpeta dentro de `src/contexts/` que tenga un `index.js` exportando un `ModuleModel` con `name` definido queda registrada automáticamente en `MODULE_REGISTRY`.

Ver [modulos.md](./modulos.md) para el detalle completo.

---

## Diagrama de dependencias de servicios

```
components / views
      │
      ▼
 composables (useRestModel, useModal, useFilters…)
      │
      ▼
  ApiService
      │
      ▼
  axios (axiosInstance)
      │
   interceptors
   ├─ request  → adjunta Bearer token desde StorageService
   └─ response → maneja 401 (refresh) y errores tipados
```

---

## Principios de diseño por contexto

Cada contexto debe ser **autocontenido**: agrupa sus propias vistas, composables, servicios, modelos y constantes sin depender de los internos de otro contexto. La única dependencia permitida hacia fuera es `src/core` y `src/contexts/shared`.

Dentro de cada contexto las responsabilidades se reparten así:

**Vistas (`views/`)**
Las vistas son exclusivamente plantilla y estilos. No deben contener lógica de negocio, gestión de estado ni llamadas a la API. Todo eso vive en el composable asociado. La vista instancia el composable y conecta sus datos y acciones al template.

**Composables (`composables/`)**
Contienen toda la lógica de la vista: estado reactivo, llamadas a servicios, transformaciones de datos y manejo de eventos. Separarlos de la vista es lo que hace posible la personalización por cliente sin duplicar lógica.

Cuando un cliente necesita que una pantalla se vea diferente, se crea una nueva vista en `src/clients/<nombre>/` que sustituye a la original solo para ese cliente. Esa vista nueva importa el composable del default directamente, de forma que la lógica se reutiliza íntegra y solo cambia la capa visual.

```
src/contexts/challenges/
├── views/ChallengesView.vue            ← Vista por defecto (todos los clientes)
└── composables/ChallengesView.composable.js

src/clients/acme/
└── contexts/challenges/
    └── views/ChallengesView.vue        ← Override visual solo para "acme"
        │  import { useChallengesView } from
        └─ '@/contexts/challenges/composables/ChallengesView.composable.js'
```

Si además el cliente necesita un comportamiento distinto, puede crear su propio composable que importe y amplíe el default, sin tocar el original.

**Servicios (`services/`)**
Encapsulan todas las llamadas a la API del contexto. Un componente o composable nunca debe llamar a `ApiService` directamente; lo hace a través del servicio del contexto. Esto concentra en un solo lugar las URLs, los parámetros y la gestión de errores de cada recurso.

**Modelos (`models/`)**
Los modelos de servicio actúan como frontera de limpieza entre el backend y el frontend. Cuando la respuesta de la API llega, el modelo la normaliza: asigna valores por defecto, renombra campos si es necesario y descarta lo que no se usa. Si el backend cambia la forma de un campo, el ajuste se hace únicamente en el modelo y el resto del frontend no se ve afectado.

**Stores (`stores/`)**
Se usan únicamente cuando un estado necesita ser compartido y persistido en memoria entre varios contextos o componentes no relacionados en el árbol de componentes. Para el estado local de una vista o de un flujo concreto, el composable es suficiente.

**Constantes (`constants/`)**
Cada contexto puede declarar las constantes que necesite: rutas, claves de configuración, valores de enumeración, textos fijos, etc. No están limitadas a la definición de rutas. Centralizar estos valores en `constants/` evita literales dispersos por el código y facilita los cambios.

---

## Convenciones de nombrado

| Tipo | Convención | Ejemplo |
|---|---|---|
| Vistas Vue | `PascalCase` + sufijo `View` | `AdminView.vue` |
| Componentes Vue | `PascalCase` + prefijo `Base` (si es genérico) | `BaseInput.vue` |
| Composables | `camelCase` + sufijo `.composable.js` | `restModel.composable.js` |
| Servicios | `camelCase` + sufijo `.service.js` | `api.service.js` |
| Modelos | `PascalCase` + sufijo `.model.js` | `form.model.js` |
| Stores (Pinia) | `camelCase` + sufijo `.store.js` | `auth.store.js` |
| Constantes | `camelCase` + sufijo `.constants.js` | `admin.constants.js` |
| Schemas (Joi) | `camelCase` + sufijo `.schema.js` | `user.schema.js` |
