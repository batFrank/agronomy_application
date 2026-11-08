# DEMO APP
El principal motivo de este repositorio es servir de base para proyectos futuros,
contiene características por defecto para trabajar de una manera más ágil, está inicializado con
Vite + Vue3 + Bun

### Comandos disponibles
```bun install```&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Instalar paquetes
<br>
```bun dev```&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Iniciar servidor de desarrollo
<br>
```bun compile```&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Compilar proyecto
<br>
```bun format```&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Aplicar revisiones de Eslint + Prettier
<br>
```bun new-client```&nbsp;Crear un nuevo usuario con sus configuraciones por defecto
<br>
```bun tests```&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Ejecuta todos los tests disponibles
<br>
```bun run storybook```&nbsp;Component catalog (Storybook), ver docs/component-catalog.md

### Características por defecto
✅ Eslint<br>
✅ Prettier<br>
✅ Vue Router<br>
✅ Pinia Store<br>
✅ Soporte dinámico para multiples clientes<br>
✅ I18n (Con soporte para textos personalizados por clientes)<br>
✅ Joi Validator<br>
✅ Toasts<br>
✅ Axios<br>
✅ Vitest + ejemplos básicos <br>
✅ Auth Service<br>
✅ Api Service<br>
✅ Componentes Base<br>
✅ Storybook (component catalog)<br>

### Distribución de archivos [src]
Este repositorio trabaja basado en la arquitectura scream, con ciertas adaptaciones para facilitar el dinamismo y
el trabajo colectivo en donde nuestro código estará principalmente dentro del dominio. Sin embargo, es importante
comprender que hay detrás de esta estructura base y se explicara a continuación:

```
- core
  - configs [En este apartado almacenamos las configuraciones principales tales como los iconos, la configuración del sitio, las vistas disponibles, etc]
  - plugins [En este apartado definimos los conectores que son necesarios para implementarlos en nuestra app, conecta vue3 con otras características complementarias]
  - router [En este apartado definimos las rutas que tendrá nuestro proyecto y configuraremos todo lo necesario al manejo de rutas]
  - scripts [En este apartado tenemos los scripts necesarios para la ejecución del proyecto]
  - services [En este apartado tendremos los servicios necesarios para el funcionamiento del proyecto]
  - utils [En este apartado almacenaremos las utilidades generales que utilizaremos en el core]
  - main.js [Archivo encargado de orquestar todo y montar la app]
```

Como podemos observar, el core contiene todo el código para el correcto funcionamiento del proyecto, es código que
editaremos muy poco a lo largo de la vida del proyecto, solo que agreguemos nuevos plugins, actualicemos las rutas
o editemos el listado de iconos disponibles, todo lo que está relacionado directamente con el funcionamiento general
del proyecto lo encontraremos aquí

```
- contexts
  - home [* opcional, es solo para ejemplo]
  - login [* opcional, es solo para ejemplo]
  - shared [carpeta requerida]
```

La carpeta contexts es el corazón de la app, donde encontraremos todo lo relacionado al proyecto que el cliente quiere,
con una estructura dividida en dominios pequeños para facilitar el trabajo en colectivo, en ella encontramos una carpeta
especial llamada "shared" que debe existir si o si (las demás son variables), con la siguiente estructura, cada una con
su propósito específico

```
- shared
    - assets
      - images [Contiene las imagenes generales de la app tales como logos, fondo que se utilizan en varios dominios, etc]
      - js [Contiene las variables de configuración de cada cliente dentro del archivo settings.js]
      - styles [Contiene la configuración visual del cliente, colores, fuentes, etc dentro del globals.scss]
    - views [Contiene la vista general de entrada App.Vue]
    - components [Contiene los componentes que pueden ser reutilizados en todos los dominios]
    - composables [* opcional, es solo para ejemplo, de igual manera para ser compartido en todos los dominios]
    - utils [* opcional, es solo para ejemplo, de igual manera para ser compartido en todos los dominios]
    - constants [* opcional, es solo para ejemplo, de igual manera para ser compartido en todos los dominios]
    - stores [* opcional, es solo para ejemplo, de igual manera para ser compartido en todos los dominios]
```

Nota: cada dominio debe, con base a cada necesidad, contener sus propios stores, views, components, constants, etc.
en shared debería estar únicamente lo que se comparte a lo largo de todos los dominios, por ejemplo, en components:
Inputs, selectors, switches, dropdowns, etc.

```
- clients (* opcional, si es una app de un solo cliente, no es necesaria esta carpeta)
  - clientX [este nombre debe coincidir con el folderName de su settings.js]
      - assets
          - images [Contiene los logos del cliente, sus fondos, etc]
          - js [Contiene la configuración del cliente dentro de su archivo settings.js]
          - styles [Contiene sus estilos personalizados dentro de su globals.scss]
```
La carpeta de clients, a como su nombre lo indica, almacenará las configuraciones personalizadas de los clientes,
debe tener la misma estructura del dominio, en donde la variante será dentro de su propio shared, ya que de ahi se cargaran
los logos, la configuración del cliente y los cambios visuales, en casos más complejos, en donde se necesite cambiar la plantilla
de un dominio, bastaría con crear el dominio que deseamos cambiar, luego crear la carpeta "view" y dentro la vista que queremos
editar para ese cliente con los cambios solicitados, si esto no es necesario, cada cliente estará completado con solamente
su carpeta shared y sus archivos de configuración.

### Funcionamiento del core
La lógica que persigue este proyecto con esta estructura es facilitar la escalabilidad y el mantenimiento de este proyecto y
el soporte a multiples clientes, en caso de no ser necesaria esta característica se puede omitir la carpeta "clients".

El core se encarga de entre muchas cosas, cargar dinámicamente los archivos necesarios para la app, todas las vistas,
estilos, imágenes y configuraciones, siguiendo la siguiente regla, el dominio es el proyecto por defecto en la app, en caso
de existir multiples clientes, lo que definamos dentro de clientes va a sustituir a la app principal, es decir,
cada cliente puede tener sus propios estilos, logos, fondos, etc. Y también podríamos, por ejemplo, tener un login para todos por defecto,
y si por algún motivo algún cliente necesita algo completamente diferente que no baste con cambiar fondos, bastaría con crear una nueva
plantilla que remplace la plantilla por defecto, y asi mismo con cada vista que sea necesario alterar, si no es necesario y todo lo que
el cliente necesita es cambio de estilos y logos dentro de shared, no es necesario crear ninguna otra carpeta dentro del cliente.

### Configuraciones por defecto del proyecto
De igual manera que los demás assets, la configuración de un cliente sobreescribe la configuración por defecto (domain/**/settings.js),
en caso de trabajar con soporte para multi clientes, el settings.js no es necesario que lleve todas las keys, puede crearse un archivo
settings.js dentro del cliente con lo mínimo necesario y el proyecto debería funcionar correctamente, a continuación detallaré el
archivo settings.js y dejaré marcado los opcionales (🟢) y los requeridos (🔴)
```
{
  FOLDER_NAME: 'default', // 🔴 Nombre de la carpeta de donde se cargaran los recursos
  TITLE: 'Vue App Title', // 🔴 Nombre del cliente, este tambien sirve de titulo en la pestaña del navegador
  DEFAULT_LOCALE: 'en', // 🟢 Idioma por defecto
  
  // 🟢 Meta-data del proyecto 
  META: {
    DESCRIPTION: 'Sample Vue3 app',
    TAGS: ['Vue3', 'App'],
  },
  
  STORAGE_PREFIX: '__default', // 🟢 Prefijo para las keys de las cookies
  
  AUTH_ACTIVE: true, // 🟢 Activa o desactiva la verificación de autenticación
  
  // 🟢 En caso de ser activa la autenticación se debe asignar las páginas por defecto para 
  PAGES: {
    ENTRY_PAGE: 'login', // Pagina por defecto en caso de no estar autenticado
    AUTH_DEFAULT: 'home', // Pagina por defecto en caso de estar autenticado
  },
}
```

Un ejemplo de una configuración de cliente mínima puede ser:
```
{
  // El texto debe coincidir con el nombre de carpeta que esta dentro de clients "/src/clients/clienteX"
  FOLDER_NAME: 'clienteX', 
  // El título puede ser el nombre del cliente
  TITLE: 'Cliente X',
}
```

Solamente que se necesite cambiar el contenido de las demás variables es necesario agregarlas al settings.js del cliente

Nota importante: <br>
El script de ```bun new-client``` ya creará los archivos por defecto, lo único que hará falta es agregar las configuraciones
extras como colores o fuentes en el globals.scss, cambiar las imágenes en images/ y solamente si es necesario, editar el settings.js

Con respecto a la documentación de los componentes y configuraciones del core, se hará uso de la wiki del proyecto

## Servidor offline
El servidor offline puede ser ejecutado cuando sea necesario, es una opción más en ```bun dev```, en caso de ejecutarse,
el servidor buscara dentro de la carpeta ```test/*``` cualquier archivo .mock.js para cargar las rutas disponibles.

Recomendaciones y pasos a seguir:
1. Dentro de tests/contexts buscar el contexto de acuerdo a las rutas que se quieren agregar, por ejemplo, en la carpeta shared,
tenemos la store de auth, que contiene 3 rutas, por ende vamos a crear una carpeta nueva llamada mocks y creamos los archivos 
mocks necesarios. (Un solo archivo puede tener 1 o varias rutas)
2. Creamos un archivo, por ejemplo ```myNewRoutes.mock.js``` o, editamos uno ya existente
3. Agregamos las rutas dentro del archivo de mocks, con los siguientes parametros:
``` 
{
     url: '/my_endpoint',
     callback: () => {}, // dentro de la función retornamos lo que necesitemos
     method: 'get', // metodo de la petición [get, post, put, patch]
}
```
Nota: Se debe tener en cuenta que la url debe coincidir con la url de la store, una alternativa para manejar esto de una mejor manera, 
es crear constantes dentro de cada contexto para almacenar las urls de las apis y no escribir en ningun otro lugar, solamente cargar la referencia.

### Flujo de trabajo recomendado:
https://coda.io/d/Documentacion-Gestionet_d5xvbgWmyB5/Flujo-de-trabajo_surMfl2E

### Guía general para el uso de Vue 3 [Buenas practicas, flujos, consejos y trucos]:
https://coda.io/d/Documentacion-Gestionet_d5xvbgWmyB5/Recomendaciones-generales-Vue-3_suUn24M4