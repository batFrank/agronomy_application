# Base Front — Documentación General

> Versión del proyecto: `1.0.0` | Stack: Vue 3 · Vite · Pinia · Vue Router · Axios

## ¿Qué es este proyecto?

**Base Front** es una plantilla de aplicación web SPA (Single Page Application) construida con Vue 3. Está diseñada para ser reutilizada en múltiples clientes/productos mediante un sistema de **multi-cliente** que permite sobreescribir configuración, vistas y estilos sin tocar el código base.

La arquitectura separa el **núcleo del framework** (`src/core`) de la **lógica de negocio** (`src/contexts`) y las **personalizaciones por cliente** (`src/clients`), lo que facilita el mantenimiento y la extensión del proyecto.

---

## Estructura de carpetas

```
base front/
├── src/
│   ├── core/           # Framework interno: configuración, servicios, modelos, composables
│   ├── contexts/       # Módulos de la aplicación (auth, admin, home, layout, shared…)
│   └── clients/        # Configuraciones y overrides por cliente (ej. easyLearning)
├── public/             # Recursos estáticos públicos
├── docs/               # Esta documentación
├── vite.config.js      # Configuración de Vite + multi-cliente
├── package.json
└── index.html
```

---

## Documentación por sección

| Archivo | Contenido |
|---|---|
| [arquitectura.md](./arquitectura.md) | Visión general de capas y flujo de arranque |
| [core.md](./core.md) | Núcleo: configs, plugins, servicios, modelos, utils |
| [router.md](./router.md) | Sistema de rutas, guards y carga dinámica |
| [autenticacion.md](./autenticacion.md) | JWT, refresh token, guards y flujo de login |
| [modulos.md](./modulos.md) | Sistema de módulos y registro (`contexts/registry`) |
| [composables.md](./composables.md) | Composables reutilizables del core |
| [componentes-shared.md](./componentes-shared.md) | Componentes UI compartidos |
| [multi-cliente.md](./multi-cliente.md) | Sistema de configuración y overrides por cliente |
| [estilos.md](./estilos.md) | Sistema de variables SCSS, custom properties CSS y override por cliente |
| [component-catalog.md](./component-catalog.md) | Storybook component catalog: running, writing stories, shipping it per client |
| [faq.md](./faq.md) | Preguntas frecuentes y recetas de uso común |

---

## Tecnologías principales

| Librería | Versión | Uso |
|---|---|---|
| Vue 3 | ^3.5 | Framework UI |
| Vite | ^8.0 | Bundler y dev server |
| Vue Router | ^5.1 | Navegación SPA |
| Pinia | ^3.0 | Estado global |
| Axios | ^1.16 | Cliente HTTP |
| vue-i18n | ^11.4 | Internacionalización |
| Joi | ^18.2 | Validación de formularios |
| vue3-toastify | ^0.2 | Notificaciones toast |
| @sentry/vue | ^10.56 | Monitoreo de errores |
| MirageJS | ^0.1 | Mock server para desarrollo |
| FontAwesome | ^7.2 | Iconos |

---

## Scripts disponibles

```bash
bun run dev          # Asistente interactivo → selecciona cliente, entorno y si activar mock server; luego levanta el dev server
bun run build        # Compila para producción; acepta --client=<nombre> --mode=<production|dev> o pregunta lo que falte
bun run new-client   # Asistente interactivo para crear un nuevo cliente
bun run locales      # Gestión de archivos de traducciones
bun run tests        # Ejecuta los tests con Vitest
bun run storybook    # Component catalog (Storybook) en http://localhost:6006, ver component-catalog.md
bun run format       # Aplica ESLint + Prettier
```

---

## Variables de entorno

| Variable | Descripción |
|---|---|
| `VITE_CLIENT` | Nombre del cliente/carpeta dentro de `src/clients/` |
| `VITE_BASE_URL` | Prefijo base de la URL (ej. `app`) |
| `VITE_API_URL` | URL base de la API REST |
| `START_MOCK_SERVER` | `"true"` activa MirageJS en desarrollo |
