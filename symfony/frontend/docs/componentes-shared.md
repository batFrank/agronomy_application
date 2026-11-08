# Componentes UI Compartidos

Los componentes de la capa `shared` (`src/contexts/shared/components/`) son los bloques de construcción reutilizables de la interfaz. Todos siguen el prefijo `Base` para distinguirlos de los componentes específicos de módulo.

---

## Componentes de formulario

### `BaseInput`
Input de texto estándar. Soporta v-model, estados de error, label, placeholder e ícono.

### `BaseInputDate`
Selector de fecha que envuelve `@vuepic/vue-datepicker`. Integrado con el sistema de validación.

### `BaseInputFile`
Input de tipo `file` estilizado. Permite adjuntar archivos con preview del nombre seleccionado.

### `BaseSelect`
Select con búsqueda basado en `vue3-select-component`. Recibe un array de opciones en formato `{ key, name }` (compatible con `getItemsAsOptions()` de `useRestModel`).

### `BaseCheckbox`
Checkbox estilizado con soporte de v-model booleano.

### `BaseRadio`
Radio button estilizado con soporte de v-model.

### `BaseSwitch`
Toggle switch on/off con v-model booleano.

### `BaseText`
Área de texto (textarea) estilizada.

### `ErrorMessage`
Componente para mostrar mensajes de error de validación debajo de un campo de formulario. Recibe el mensaje como prop.

---

## Componentes de layout y estructura

### `BaseModal`
Modal genérico con overlay. Usa `useModalObserver` internamente para bloquear el scroll del body mientras está abierto. Expone slots para header, body y footer del modal.

Props principales: `isOpen` (controla visibilidad), emit `close` al cerrar.

### `BaseTabs`
Renderiza un conjunto de pestañas a partir de un objeto `configTabs` producido por `useTabs`. Emite eventos al cambiar de pestaña.

### `BaseButton`
Botón estilizado con variantes (primario, secundario, peligro, etc.) y estado de carga (`loading`).

### `TableContainer`
Wrapper para tablas con scroll horizontal en móvil y estilos consistentes.

### `LoadContent`
Componente de estado de carga. Muestra un spinner o skeleton mientras `isLoading` es `true`, y renderiza el slot por defecto cuando los datos están disponibles.

---

## Componentes de layout global

Estos componentes viven en `src/contexts/layout/components/`:

### `AppHeader`
Barra de navegación superior. Recibe `menuItems` via `inject('menuItems')` y los renderiza mediante `MenuItem.vue` y `ResponsiveMenu.vue`.

### `AppFooter`
Pie de página. Recibe `footerItems` via `inject('footerItems')`.

### `MenuItem`
Ítem de menú individual con soporte para:
- Enlace interno (vue-router) o externo (href)
- Submenú desplegable
- Icono FontAwesome
- Highlight de ruta activa

### `ResponsiveMenu`
Contenedor del menú que gestiona el comportamiento responsive: menú hamburguesa en móvil, barra horizontal en escritorio.

---

## Estilos compartidos (`src/contexts/shared/assets/styles/`)

### `globals.scss`
Estilos globales de la aplicación: reset CSS, tipografía base, variables de colores, utilidades.

### `_breakpoints.scss`
Mixins SCSS para breakpoints responsivos:
```scss
@include breakpoint(md) { ... }  // tablet y superiores
@include breakpoint(lg) { ... }  // escritorio
```

### `_fonts.scss`
Declaraciones `@font-face` para las fuentes del proyecto (Open Sans en múltiples pesos, Montserrat Bold).

---

## Store de autenticación (`shared/stores/auth.store.js`)

Aunque es un store, se ubica en `shared` porque lo usan múltiples módulos. Ver [autenticacion.md](./autenticacion.md) para el detalle completo.

---

## Schemas de autenticación compartidos (`shared/schemas/auth.schema.js`)

```js
loginSchema         // email + password (mín. 5 chars)
emailSchema         // solo email
passwordResetSchema // new_password (mín. 6 chars)
```
