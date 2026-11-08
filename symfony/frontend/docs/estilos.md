# Guía de estilos

Los estilos del proyecto se organizan en `src/contexts/shared/assets/styles/` y funcionan como el sistema de diseño base. Cada cliente puede sobreescribir únicamente las variables que necesite en su propio `globals.scss`.

---

## Arquitectura del sistema de estilos

```
src/contexts/shared/assets/styles/
├── globals.scss       ← Variables, custom properties CSS y estilos base
├── _breakpoints.scss  ← Breakpoints responsive
└── _fonts.scss        ← Declaraciones @font-face

src/clients/<nombre>/shared/assets/styles/
└── globals.scss       ← Override del cliente (solo lo que cambia)
```

`StylesPlugin` carga el SCSS del cliente activo en tiempo de ejecución, sobreponiendo sus variables sobre las del default.

---

## Variables SCSS

Todas las variables del default están declaradas con `!default`, lo que significa que si un cliente las redeclara antes, su valor tiene precedencia. Esto es lo que hace posible el override sin modificar el archivo base.

### Colores de estado

```scss
$color-primary: #2196f3 !default;   // Azul — acciones principales, enlaces, foco
$color-success: #4caf50 !default;   // Verde
$color-warning: #ffc107 !default;   // Amarillo
$color-danger:  #f44336 !default;   // Rojo — errores, eliminación
```

Estas cuatro son las variables que los clientes sobreescriben con más frecuencia. Cambiar `$color-primary` afecta automáticamente a botones, inputs, enlaces, scrollbar y cualquier elemento que derive de ella.

### Escala de grises (neutrales)

```scss
$color-neutral-darkest:   #0b0d0e   // Textos muy oscuros
$color-neutral-darker:    #32383e   // Texto principal
$color-neutral-dark:      #484f56   // Texto secundario
$color-neutral-mid-dark:  #878f97   // Placeholder, texto deshabilitado
$color-neutral-mid:       #ced4da   // Bordes
$color-neutral-mid-light: #dfe3e7   // Bordes suaves, fondos disabled
$color-neutral-light:     #e8ebee   // Fondos alternativos
$color-neutral-lighter:   #f0f2f4   // Fondos de sección
$color-neutral-lightest:  #ffffff   // Blanco puro
```

Los neutrales no llevan `!default` porque son parte del sistema de diseño base y no se espera que los clientes los cambien. Si un cliente necesita una paleta de grises diferente, se recomienda sobreescribir las variables semánticas (ver siguiente sección) en lugar de los neutrales.

### Variables semánticas

Estas variables usan los colores anteriores como valor base y son las que se usan en los componentes. Al sobreescribir solo estas, el cliente puede cambiar la apariencia sin necesidad de redefinir toda la escala.

```scss
$text-color:        $color-neutral-darker    !default  // Color de texto general
$link-color:        $color-primary           !default  // Color de enlaces
$bg-color:          $color-neutral-lightest  !default  // Fondo de la app
$font-primary:      fonts.$family-primary    !default  // Fuente principal (Open Sans)
$font-secondary:    fonts.$family-secondary  !default  // Fuente de títulos (Montserrat)
$font-line-height:  1.5                      !default
```

### Variables de inputs

```scss
$input-text-color:            $text-color              !default
$input-border-color:          $color-neutral-mid       !default
$input-background:            $color-neutral-lightest  !default
$input-primary:               $color-primary           !default
$input-primary-bg:            // $input-primary con +40% lightness
$input-background-disabled:   $color-neutral-mid-light !default
$input-text-disabled:         $color-neutral-dark      !default
$icon-color:                  $text-color              !default
$error-color:                 $color-danger            !default
```

### Variables de header y footer

```scss
$header-bg-color:         $color-neutral-lightest  !default
$header-hover-bg-color:   $input-primary-bg        !default
$header-hover-line-color: $color-primary           !default
$header-text-color:       $color-neutral-darkest   !default
$header-height:           4rem                     !default
$header-shadow:           0 0 2px 1px $color-neutral-mid-dark !default
$header-border-color:     $color-neutral-mid       !default

$footer-bg-color:         $color-neutral-lightest  !default
$footer-text-color:       $color-neutral-darkest   !default
$footer-link-color:       $color-primary           !default
```

---

## Custom properties CSS (variables CSS en `:root`)

Todas las variables SCSS se exponen también como custom properties CSS en `:root`. Esto permite usarlas directamente en los componentes Vue con `var(--nombre)` sin necesidad de importar SCSS.

```css
var(--color-primary)
var(--color-primary-hover)     /* color-primary con -10% lightness */
var(--color-success)
var(--color-success-hover)
var(--color-warning)
var(--color-warning-hover)
var(--color-danger)
var(--color-danger-hover)

var(--color-neutral-darkest)
var(--color-neutral-darker)
var(--color-neutral-dark)
var(--color-neutral-mid-dark)
var(--color-neutral-mid)
var(--color-neutral-mid-light)
var(--color-neutral-light)
var(--color-neutral-lighter)
var(--color-neutral-lightest)

var(--text-color)
var(--link-color)
var(--bg-color)
var(--font-primary)
var(--font-secondary)

var(--input-text-color)
var(--input-border-color)
var(--icon-color)
var(--input-background)
var(--input-primary)
var(--input-primary-bg)
var(--input-background-disabled)
var(--input-text-disabled)
var(--error-color)

var(--header-bg-color)
var(--header-hover-bg-color)
var(--header-hover-line-color)
var(--header-text-color)
var(--header-height)
var(--header-shadow)
var(--header-border-color)

var(--footer-bg-color)
var(--footer-text-color)
var(--footer-link-color)
```

---

## Override de estilos en un cliente

Para cambiar la apariencia de un cliente se usa `@use ... with (...)` en su `globals.scss`, que es la forma correcta en las versiones modernas de SCSS para pasar valores a las variables `!default` del módulo importado. Solo es necesario declarar las que cambian:

```scss
// src/clients/acme/shared/assets/styles/globals.scss

@use '@/contexts/shared/assets/styles/globals' with (
  $color-primary:    #e91e63,
  $font-primary:     'Roboto', sans-serif,
  $header-bg-color:  #1a1a2e,
  $header-text-color: #ffffff,
);
```

Al sobreescribir `$color-primary`, todos los elementos que derivan de ella (inputs, botones, enlaces, scrollbar, hover del header…) se actualizan automáticamente sin tocar ningún otro archivo.

---

## Breakpoints (`_breakpoints.scss`)

```scss
$xs-min: 480px    // Móvil pequeño
$sm-min: 576px    // Móvil
$md-min: 768px    // Tablet
$lg-min: 992px    // Escritorio pequeño
$xl-min: 1300px   // Escritorio grande
```

Uso en componentes SCSS:

```scss
@media #{$breakpoint-md} {
  // Estilos para pantallas <= 768px
}
```

---

## Tipografía

**Fuente principal — Open Sans** (pesos disponibles: 300, 400, 500, 600, 700, 800, en normal e italic)
Usada para el texto general de la aplicación.

**Fuente secundaria — Montserrat Bold** (700)
Usada para títulos (`h1`–`h6` y `.title`).

Ambas fuentes se sirven localmente desde `src/contexts/shared/assets/fonts/` y se declaran en `_fonts.scss`, por lo que no requieren conexión a Google Fonts ni CDN externo.

---

## Estilos base globales

El `globals.scss` también establece los estilos reset y base de la aplicación:

- `box-sizing: border-box` aplicado globalmente.
- `html` con `overflow: hidden` y `body` con `overflow-y: auto` y `height: 100dvh`, garantizando que el scroll esté siempre en el body y no en el html.
- `min-width: 340px` en body como límite mínimo de ancho soportado.
- `.container` con `max-width: 1400px` y centrado automático.
- Scrollbar personalizada usando `$color-primary`.
- Reset de márgenes y padding en `html` y `body`.
