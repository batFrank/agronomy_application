# Script de despliegue

Guía de referencia para configurar y ejecutar el script de despliegue del proyecto. Si es tu
primer despliegue, sigue las secciones en orden: no asumen ningún conocimiento previo del script.

## 1. Qué es y qué no es

`deploy.js` es un script Node.js/CommonJS interactivo que se ejecuta **en tu máquina local**, no
dentro de ningún contenedor. Habla por SSH con el servidor de destino, sincroniza ficheros con
`rsync` y ejecuta comandos remotos; no hay ninguna parte del proceso que corra en el propio VPS
salvo los comandos puntuales que el script lanza por SSH. Para el resto de comandos del proyecto
(entorno local de desarrollo, tests, etc.) consulta el [`README.md`](../../README.md) de la raíz.

## 2. Requisitos locales

- **Node.js**, para ejecutar el propio script.
- **`rsync` y `sshpass`**, obligatorios: el script comprueba su presencia al arrancar, una a una, y
  si falta cualquiera de las dos termina con `exit 1` diciendo **cuál** de ellas falta y cómo
  instalarla (`deploy.js:762-770`).
- **Docker**, porque el build del frontend y la acción `backup-db-local` se ejecutan dentro de
  contenedores.
- **VPN activa**, si la base de datos o el propio servidor solo son accesibles a través de ella.

Para instalar las dependencias del script:

```bash
make deploy-install
```

que internamente ejecuta `npm ci`. Para lanzar el script en modo interactivo:

```bash
make deploy
```

## 3. Requisitos del servidor

El servidor necesita PHP >= 8.5 con las extensiones que declara `backend/composer.json` (`ctype`,
`curl`, `iconv`, `intl`, `openssl`) más las que el proyecto necesita en tiempo de ejecución:
`pdo_pgsql`, `mbstring`, `xml` y `zip` (el contenedor local las instala en
`docker/php/Dockerfile:28`). Antes de desplegar por primera vez, conviene comprobarlo por SSH con
este comando:

```bash
for t in rsync tar openssl setfacl pg_dump; do printf "%s: " "$t"; command -v $t || echo "AUSENTE"; done
php8.5 -m | grep -iE "pdo_pgsql|intl|mbstring"
```

Es habitual que este comando reporte dos ausencias, y **conviene tratarlas como el escenario por
defecto, no como una excepción**:

- **Falta `setfacl`.** Cuando no está disponible, la acción `permissions` cae automáticamente a
  `chmod 0777` (`deploy.js:603-605`). Esto es un parche, no una solución: lo correcto es pedir a
  sistemas que instale el paquete `acl` o que iguale la propiedad de los ficheros con la del
  usuario de PHP-FPM.
- **Falta `pg_dump` en el servidor.** En ese caso la acción `backup-db` no funciona. Por eso
  `backup-db-local` debe tratarse como la opción de copia de seguridad **por defecto**, no como
  una alternativa secundaria; requiere Docker en tu máquina y, si la base de datos está detrás de
  VPN, la VPN activa.

## 4. Layout esperado en el servidor

El script asume esta estructura de directorios en el servidor, con `paths.backend` como raíz del
proyecto:

```
<base>/                      ← paths.backend: proyecto Symfony en la base del espacio asignado
├── public/                  ← DocumentRoot del vhost
│   ├── index.php            ← front controller; sirve la API en /api
│   ├── app/                 ← paths.frontend: SPA (VITE_BASE_URL=/app/) + .htaccess generado
│   └── uploads/             ← filesystem PUBLIC_LOCAL; excluido de rsync
├── Storage/                 ← filesystem PRIVATE_LOCAL; excluido
├── var/{cache,log,chunks,tmp/files}   ← excluido
├── config/jwt/{private,public}.pem    ← excluido; lo genera jwt-keys
├── tmp/                     ← upload_tmp_dir de PHP-FPM; excluido
├── backups/                 ← excluido
└── .env.local               ← lo genera env-file; excluido
```

En el VPS de referencia usado para verificar este layout, el home del usuario SSH coincide
directamente con `paths.backend`, y los directorios `public/` y `tmp/` ya venían creados por el
proveedor de hosting.

## 5. Requisitos de vhost y de PostgreSQL

El vhost del servidor debe cumplir:

- `DocumentRoot` apuntando a `<base>/public`, con `mod_rewrite` activo.
- **`AllowOverride All` (o al menos `FileInfo`) en el directorio donde vive la SPA.** Si no lo
  está, el `.htaccess` que genera la acción `sync-frontend` (línea 481) se ignora y los deep links
  de la SPA devuelven 404. Si el hosting fuerza `AllowOverride None`, la alternativa es pedir un
  `FallbackResource` equivalente directamente en el vhost.
- `AllowOverride All` también en `public/`, para que funcione el `.htaccess` propio de Symfony.
- **`SetEnvIf Authorization "^.+$" HTTP_AUTHORIZATION=$0`**, imprescindible para la autenticación
  JWT: sin esta directiva, PHP-FPM pierde la cabecera `Authorization` y los `Bearer` no llegan a
  la aplicación.
- Un bloque `<FilesMatch \.php$> SetHandler "proxy:fcgi://…"` que apunte al pool de PHP-FPM
  correcto.

> **Aviso importante:** `docker/apache/virtualhost.conf` es la configuración del entorno **local**
> y no debe usarse como plantilla para el vhost del servidor. En local, el `DocumentRoot` es
> `/var/www/html/public` y la SPA se publica con `Alias /app /var/www/frontend`, **fuera** de
> `public/`, con `AllowOverride None` y `FallbackResource /app/index.html`: es decir, en local el
> `.htaccess` que genera el script se ignora y el fallback de rutas lo resuelve directamente el
> vhost. En el servidor, en cambio, la SPA vive **dentro** de `<base>/public/app`, y es el
> `.htaccess` generado el que se encarga del fallback.

En cuanto a la base de datos, se necesita PostgreSQL 17 con la extensión `pdo_pgsql`. La base de
datos puede vivir fuera del propio servidor web y ser accesible solo por VPN (de ahí que
`backup-db-local` use `--network host`). **Limitación a tener en cuenta: el script no da soporte a
MySQL/MariaDB ni a `mysqldump`**; las dos acciones de copia de seguridad usan `pg_dump` sin
alternativa.

## 6. Puesta en marcha

El primer paso es copiar la plantilla de configuración. Este comando, como todos los de esta guía
salvo que se indique lo contrario, se ejecuta desde la raíz del repositorio:

```bash
cp scripts/deploy/deploy.config.example.json scripts/deploy/deploy.config.json
```

`deploy.config.json` está deliberadamente excluido del repositorio (`.gitignore:20-24`), y es
importante saber por qué: a diferencia del `.example`, contendrá contraseñas reales en claro. Si
el fichero no existe, el script lo detecta y avisa al arrancar (`deploy.js:169-174`).

Antes de rellenar el campo `fpmUser` (ver §7) conviene averiguar cuál es el usuario real bajo el
que corre PHP-FPM en el servidor, en vez de adivinarlo. Un método verificado en campo es subir
temporalmente un fichero PHP que lo revele y consultarlo por HTTP:

```bash
printf '<?php echo get_current_user();' > public/_ver.php
curl https://<url>/_ver.php && rm public/_ver.php
```

El `printf` y el `rm` se ejecutan **en el servidor** (por SSH, dentro de `<base>`); solo el `curl`
es local. Por eso este método no está disponible aún en un primer despliegue: deja `fpmUser` sin
rellenar al empezar, y complétalo después de `sync-backend` (§12, paso 3) y antes de ejecutar
`permissions` (paso 9). Sin `fpmUser`, `permissions` cae al fallback de `chmod 0777`.

Si no haces esta comprobación, el despliegue te avisará: la acción `permissions` verifica con `id`
que el usuario existe en el servidor y lo dice si no es así (ver §7).

## 7. Configuración campo a campo

La configuración tiene dos niveles: uno a nivel raíz, común a todos los entornos, y otro por cada
entorno declarado dentro de `environments`.

### Nivel raíz

| Clave | Línea | Oblig. | Si falta | De dónde sale el valor |
|---|---|---|---|---|
| `settings` | 347, 407 | No | Defaults de cada subclave | — |
| `settings.bunImage` | 407 | No | `oven/bun:latest` | Imagen Docker con Bun. Fijar una versión hace el build reproducible |
| `settings.phpContainer` | 347 | No | config → `PHP_CONTAINER` del `Makefile` → `symfony-base-php` | Solo si el fork renombró el contenedor y no lo declaró en el `Makefile` |
| `backend` | 260 | No | `{}` | Passthrough de proyecto: variables iguales en todos los entornos |
| `environments` | 181-184 | **Sí** | Error claro y `exit 1` | Un objeto por entorno |

### Por entorno

| Clave | Líneas | Oblig. | Si falta | De dónde sale el valor |
|---|---|---|---|---|
| `url` | 241, 867 | **Sí** | Error claro vía `validateBackendEnv:272` (alimenta `DEFAULT_URI`) | URL pública completa del entorno |
| `ssh.host` | 214, 790 | **Sí** | Fallo de conexión (798-800) | Host o IP del VPS |
| `ssh.port` | 211, 791 | No | `22` | — |
| `ssh.username` | 214, 792 | **Sí** | Fallo de conexión | Usuario SSH del espacio asignado |
| `ssh.password` | 214, 793 | **Sí** | Fallo de conexión | Solo autenticación por contraseña; el script no soporta claves SSH |
| `db.host` | 235, 623, 656 | **Sí** | Error claro (`DB_HOST`) | Host de PostgreSQL (puede estar tras VPN) |
| `db.port` | 236, 623, 656 | No | `5432` | — |
| `db.version` | 237, 636 | No | `17`; fija la imagen `postgres:<version>` de `backup-db-local` | Versión mayor de PostgreSQL |
| `db.database` | 238, 616, 624, 639, 656 | **Sí** | Error claro (`DB_NAME`) | Nombre de la BD; también en el nombre del backup |
| `db.username` | 239, 623, 656 | **Sí** | Error claro (`DB_USERNAME`) | — |
| `db.password` | 240, 622, 653 | **Sí** | Error claro (`DB_PASSWORD`) | — |
| `db.pgDumpBin` | 619 | No | `cfg.pgDumpBin` → `pg_dump` | Solo si el binario no está en el `PATH` (p. ej. `/usr/pgsql-17/bin/pg_dump`). Alias: `pgDumpBin` a nivel de entorno |
| `paths.backend` | 189, 465, 491, 493, 615, 675, 680 | **Sí** | ⚠️ **Sin error claro**: los comandos se ejecutan en el home del SSH silenciosamente (190); `rsyncPush` revienta con `TypeError` (218) | Ruta absoluta del proyecto Symfony en el servidor |
| `paths.frontend` | 127, 129, 477, 480 | **Sí** | ⚠️ `TypeError` en 188 envuelto como «Error en "sync-frontend"» | `<paths.backend>/public/app` o la ruta base configurada |
| `phpBin` | 527, 531, 538, 539, 693 | **Sí** | ⚠️ **Sin error claro**: comando remoto `undefined bin/console …` → código 127 | Binario de PHP CLI del servidor: `php`, `php8.5`, `/usr/bin/php8.5` |
| `fpmUser` | 554, 563, 575, 582, 583 | No | Mensaje informativo (593) y fallback a `chmod` | Usuario real de PHP-FPM. Se comprueba con `id` que existe en el servidor (563) y se avisa si no (567) |
| `backend` (entorno) | 231 | No | `{}` | Passthrough de entorno |
| `frontend` | 101, 421, 464, 473 | No | `{}` | Se escribe **verbatim** a `frontend/.env.local` en el build |
| `frontend.VITE_BASE_URL` | 101 | Recomendada | `frontend/.env` → `/app/` | Ruta base de la SPA. **No puede ser `/`** (120-124) |
| `frontend.VITE_API_URL` | 447 | No para el script, **sí para la app** | La SPA no encuentra la API | `/api/v1` con el layout estándar |

Tres campos merecen atención especial porque su error **no** produce un mensaje claro:
`paths.backend`, `paths.frontend` y `phpBin`. Si algo falla de forma extraña y sin explicación, son
los primeros sospechosos.

`fpmUser` merece un apartado propio, más que una simple fila de tabla: es el campo que más
fácilmente se configura mal, porque un valor incorrecto no impide que el despliegue termine: solo
haría que la acción `permissions` aplicase los permisos al usuario equivocado. Por eso la acción
comprueba con `id` que el usuario existe en el servidor y avisa de forma explícita cuando no es así,
distinguiéndolo del caso en que `setfacl` no está disponible. Aun así, un usuario que **existe** pero
no es el de PHP-FPM sigue siendo indetectable: averigua el valor real con el método de §6 antes
de rellenarlo.

Por último, cualquier clave que pongas dentro de `frontend` termina en `frontend/.env.local`
(líneas 422-425) sin ninguna lista blanca que la filtre, así que añadir por ejemplo `VITE_FOO` no
requiere tocar `deploy.js`: es un passthrough igual que el del backend.

## 8. Variables de entorno propias (passthrough del backend)

La fuente de verdad de qué variables de entorno existen en el proyecto es **`backend/.env`**
(la ruta está fijada en la línea 29 de `deploy.js`, y el fichero se parsea en la línea 304). El
script no mantiene ninguna lista propia de variables: todo lo que declares en `backend/.env` es lo
que el script intentará resolver para el despliegue.

La resolución de cada variable sigue un orden de precedencia (`resolveBackendEnv`, líneas 258-262,
de menor a mayor prioridad): primero el valor derivado automáticamente, después `backend` a nivel
de proyecto (config raíz), y por último `backend` dentro de cada entorno. Los valores `undefined`
o `null` se descartan (`definedEntries`, 220-221), de forma que no pisan un valor de más prioridad
por error.

De las variables declaradas en `backend/.env`, 13 se derivan automáticamente y **no deben
declararse** en la configuración (ver §1 del `.example` para el listado completo:
`APP_ENV`, `DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_VERSION`, `DB_NAME`, `DB_USERNAME`, `DB_PASSWORD`,
`DEFAULT_URI`, `MESSENGER_TRANSPORT_DSN`, `JWT_SECRET_KEY`, `JWT_PUBLIC_KEY`,
`ROLLBAR_ENVIRONMENT`). `backend/.env` declara 22 claves en total; descontando esas 13 derivadas,
quedan 9 que sí hay que aportar desde la configuración del despliegue: `APP_SECRET`,
`EMAIL_BASE_URL`, `EMAIL_FROM`, `EMAIL_FROM_NAME`, `MAILER_DSN`, `JWT_PASSPHRASE`,
`CORS_ALLOW_ORIGIN`, `ROLLBAR_TOKEN` y `ROLLBAR_LOGGER_ENABLED`. El `.example` de este repositorio
ya las incluye todas.

Antes de escribir nada en el servidor, el script valida estas variables (`validateBackendEnv`,
líneas 269-301, línea 299 antes de escribir): si alguna clave declarada en `backend/.env` no tiene
valor resuelto, el script falla listando cuáles faltan y explicando que, de no corregirse,
heredarían el valor de desarrollo; si una clave tiene un valor vacío, también falla; y si
`ROLLBAR_TOKEN` no mide exactamente 32 o 96 caracteres, falla igualmente.

Si en la configuración aparece alguna clave que no está declarada en `backend/.env`, el script no
la rechaza: emite un aviso (líneas 316-317) y la escribe en un bloque separado, bajo el comentario
`# Variables específicas de este proyecto` (línea 328).

Para añadir una variable propia al proyecto, la receta es siempre la misma: declárala en
`backend/.env` con su valor de desarrollo por defecto, y después dale el valor de despliegue en
`backend` a nivel de proyecto o en `environments.<entorno>.backend`. No requiere ningún cambio en
`deploy.js`.

## 9. Ruta base de la SPA

El valor de la ruta base de la SPA se resuelve así: primero se mira `cfg.frontend.VITE_BASE_URL`
en la configuración del despliegue, y si no está, se cae al `VITE_BASE_URL` de `frontend/.env`. El
resultado se normaliza con `normalizeBaseUrl` (`utils.js:149-153`) a la forma `/dir/`.

Este mismo valor se usa en cuatro sitios distintos, y los cuatro deben contar la misma historia: el
`RewriteBase` del `.htaccess` que se genera (líneas 76-89, escrito en la 481), la exclusión de
`rsync` que evita borrar la propia SPA al sincronizar el backend (línea 464), la comprobación de
consistencia contra `paths.frontend` (líneas 126-131), y el redirect de la raíz del dominio de
`backend/public/.htaccess`.

Ese cuarto sitio es el único que **no** deriva la ruta del `VITE_BASE_URL`: el `.htaccess` del
`DocumentRoot` se versiona en el repositorio y se sube tal cual con `rsync`, así que lleva la ruta
cableada en su regla `RewriteRule ^$`. Hace falta porque la raíz del dominio no la sirve nadie:
Symfony no tiene ruta `/` y en `public/` no hay ningún `index.html`, de modo que sin el redirect
`/` devuelve el 404 «No route found for "GET /"» y hay que entrar a mano por `/app`. Por eso
`checkSpaBase` (líneas 132-144) lee el fichero y avisa si el redirect falta o apunta a un
directorio distinto del que se está desplegando: un proyecto que sirva la SPA en otra ruta tiene
que editar esa regla a mano.

**`VITE_BASE_URL=/` está prohibido.** Si se configura así, `checkSpaBase` (líneas 120-124) lanza un
error explicando que `vite.config.js:9` generaría `base: //`, algo inválido.

`checkSpaBase` (líneas 118-154) también avisa si en el proyecto existen los ficheros
`frontend/.env.production` o `.env.production.local`, porque Vite les da prioridad sobre el
`.env.local` que genera el propio despliegue, lo que podría hacer que la SPA ignore la
configuración recién escrita.

## 10. Las 15 acciones

El script agrupa sus acciones según dónde se ejecutan y qué tipo de trabajo hacen.

### Build local (`ssh: false`)

- **`composer-install`** (líneas 373-383): ejecuta, dentro del contenedor PHP configurado,
  `docker exec <phpContainer> composer install --no-dev --optimize-autoloader --no-interaction`,
  tras comprobar que el contenedor está levantado (`ensureContainerRunning`, líneas 355-371). Esto
  deja el `vendor/` local sin las dependencias de desarrollo, por lo que el propio script avisa
  (línea 382) de que hay que ejecutar `make composer-install` después para devolver el entorno
  local a su estado habitual. Es importante ejecutar esta acción siempre **antes** de
  `sync-backend`, porque es precisamente ese `vendor/` de producción el que se sube al servidor.
- **`frontend-build`** (líneas 400-453): escribe la configuración de `cfg.frontend` en
  `frontend/.env.local` (guardando antes una copia de seguridad en `.env.local.deploybak`, líneas
  386-389) y lanza el build dentro de un contenedor Docker: `docker run --rm --user <uid>:<gid> …
  <bunImage> sh -c "bun install --frozen-lockfile && bun run build-favicon && bun run vite build
  --mode=production"`. Comprueba que el resultado (`frontend/dist`) se ha generado (línea 444) y
  restaura el `.env.local` original en un bloque `finally` (líneas 450-451). El paso
  `build-favicon` es obligatorio, no opcional. Si el proceso se interrumpe de golpe, puede quedar
  un `.env.local.deploybak` huérfano (ver §16).
- **`backup-db-local`** (líneas 634-672): lanza `docker run --rm --network host --user <uid>:<gid>
  -e PGPASSWORD=… postgres:<db.version> pg_dump …` y guarda el resultado en
  `scripts/deploy/backups/` (gitignorado). Si el volcado falla o sale vacío, el `.sql` se borra
  (líneas 662 y 637-640) en vez de dejar un backup corrupto. La opción `--network host` es lo que
  permite alcanzar la IP de la base de datos a través de la VPN. Esta acción no necesita `pg_dump`
  instalado en el servidor.

### Sincronización (`ssh: true`)

- **`sync-backend`** (líneas 458-467): sincroniza `backend/` contra `paths.backend` con
  `rsync -az --delete`, aplicando las exclusiones descritas en §11. Requiere haber ejecutado antes
  `composer-install`. El `--delete` borra en destino cualquier fichero que no exista en local,
  salvo lo explícitamente excluido.
- **`sync-frontend`** (líneas 469-486): exige que exista `frontend/dist` (si no, el propio script
  recuerda «Ejecuta antes "frontend-build"»). Llama a `checkSpaBase` para validar la configuración
  de la ruta base y sincroniza `dist/` contra `paths.frontend` con `rsync -az --delete`, esta vez
  **sin** exclusiones. A continuación, escribe por SSH el `.htaccess` de fallback del router de la
  SPA, con el `RewriteBase` ya derivado.

### Configuración (`ssh: true`)

- **`env-file`** (líneas 488-497): genera el `.env.local` remoto mediante un heredoc, aplicando
  todo el mecanismo de resolución y validación descrito en §8. Sobrescribe el fichero existente en
  el servidor sin pedir confirmación, pero de forma segura: si la validación no pasa, no llega a
  escribir nada (línea 299).
- **`jwt-keys`** (líneas 499-523): resuelve `JWT_PASSPHRASE` de la configuración completa (línea
  472); si falta, falla con un error claro. Si ya existen claves en el servidor, pide confirmación
  antes de sobrescribirlas (línea 507, con valor por defecto `false`). Genera `private.pem` (RSA
  4096 cifrada con AES256) y `public.pem`, y ajusta permisos con `chmod 0755 config/jwt` y
  `chmod 0644` sobre ambos ficheros. **Regenerar las claves invalida todos los JWT ya emitidos**, es
  decir, desconecta a todos los usuarios con sesión activa. El directorio `config/jwt/` está
  excluido de `rsync`, así que esta acción es la única forma de gestionarlo.
- **`permissions`** (líneas 553-611): crea con `mkdir -p` los 7 directorios de `WRITABLE_DIRS`. Si
  hay `fpmUser` configurado, aplica `setfacl -R` y `setfacl -dR` con `u:<fpmUser>:rwX` sobre los 4
  directorios de `WRITABLE_ROOTS`; si `setfacl` no está disponible o no hay `fpmUser`, cae al
  fallback de `chmod 0777`. Más detalle sobre estas dos listas en §11.

### Base de datos (`ssh: true`)

- **`migrate`** (líneas 525-534): ejecuta `doctrine:migrations:sync-metadata-storage` (tolerando
  fallo) y después `doctrine:migrations:migrate --allow-no-migration --no-interaction
  --env=prod`. Requiere que el `.env.local` remoto exista ya, por lo que siempre debe ir después de
  `env-file`.
- **`backup-db`** (líneas 613-632): ejecuta `pg_dump` directamente en el servidor, vuelca el
  resultado en `<paths.backend>/backups/` y después descarga el fichero a
  `scripts/deploy/backups/` en local. A diferencia de `backup-db-local`, esta acción sí necesita
  `pg_dump` instalado en el servidor, que en el VPS de referencia usado para esta guía no estaba
  disponible.

### Operación (`ssh: true`)

- **`clear-cache`** (líneas 536-541): ejecuta `cache:clear --env=prod --no-debug` y `cache:warmup`
  (tolerando fallo). Esta acción regenera `var/cache/prod/` con la propiedad y el `umask` del
  usuario SSH, lo que es precisamente el motivo del orden de ejecución explicado en §12.
- **`create-admin`** (líneas 686-695): pregunta un email y una contraseña (mínimo 8 caracteres,
  línea 691) y ejecuta `app:create-user <email> <password> --admin`. El comando existe en
  `backend/src/Infrastructure/Command/CreateUserCommand.php:25`, y el mismo comando está disponible
  en local a través de `Makefile:37`.
- **`logs`** (líneas 697-707): pregunta cuántas líneas mostrar (80 por defecto) y ejecuta
  `tail -n <n> var/log/prod.log`.
- **`run-command`** (líneas 709-715): pide un comando arbitrario y lo ejecuta con `cwd =
  paths.backend`. No hay ninguna validación: es, literalmente, un shell remoto. Es la herramienta
  recomendada para verificar el resultado de un despliegue directamente en el servidor, sin caer en
  la caché de un posible CDN por delante (ver §14).
- **`backup-files`** (líneas 674-684): genera
  `tar --exclude=backups --exclude=var/cache --exclude=var/log -czf
  backups/backend_files_<TS>.tar.gz`. A diferencia de `backup-db`, este archivo se queda en el
  servidor y no se descarga.

### Comprobaciones previas comunes

Antes de ejecutar cualquier acción, `preDeploymentChecks` (líneas 760-785) comprueba que `rsync` y
`sshpass` estén disponibles en local, y avisa si el repositorio tiene cambios sin confirmar,
pidiendo confirmación explícita para continuar. Esta comprobación **no** verifica que Docker esté
en marcha ni la versión de Node instalada.

## 11. Exclusiones de rsync y directorios de escritura

Las exclusiones fijas de la sincronización del backend están en `BACKEND_RSYNC_EXCLUDES`
(líneas 49-68) y están **ancladas a la raíz del backend** con una barra inicial. Esto es
deliberado (ver el comentario en las líneas 44-48): sin esa barra, una exclusión como `Storage/`
también excluiría cualquier ruta anidada con ese nombre, por ejemplo
`vendor/symfony/security-core/…/Storage/`, y subiría al servidor un `vendor/` mutilado. Las rutas
ancladas son: `/var/`, `/tests/`, `/specs/`, `/config/jwt/`, `/public/uploads/`, `/backups/`,
`/Storage/` y `/tmp/`.

Hay también un segundo grupo de exclusiones que, a propósito, no están ancladas a la raíz:
`.git`, `.env.local`, `.env.*.local`, `node_modules/`, `.idea/`, `.php-cs-fixer.cache`,
`.phpunit.cache/` y `*.cache`.

Además, en tiempo de ejecución (línea 464) se añade una exclusión derivada: `/public<spaBaseUrl>`,
que con la configuración por defecto es `/public/app/`. Es importante entender que esta ruta se
calcula a partir de `VITE_BASE_URL` y no está cableada en el script: es precisamente lo que evita
que, en un proyecto que sirva la SPA desde otra ruta, `sync-backend` se borre a sí mismo la SPA con
su `--delete`.

Hay dos listas de directorios de escritura con papeles distintos:

- **`WRITABLE_ROOTS`** (línea 549): `var`, `Storage`, `public/uploads` y `tmp`. Son los 4
  directorios sobre los que se aplican las ACL (recursivas y por defecto) o, en su defecto, el
  `chmod`.
- **`WRITABLE_DIRS`** (línea 551): `var/cache`, `var/log`, `var/tmp/files`, `var/chunks`,
  `Storage`, `public/uploads` y `tmp`. Son los directorios que la acción `permissions` crea con
  `mkdir -p` (línea 556) y que también entran en el `chmod` del fallback.

Estos directorios corresponden a la configuración de `backend/config/app/filesystem.yaml`:
`public_local: public/uploads/`, `private_local: Storage/`, `chunks_local: var/chunks` y
`tmp_local: var/tmp/files`.

Merece una mención aparte el directorio `tmp/` (documentado en las líneas 547-548 de `deploy.js`),
porque su ausencia produce uno de los fallos más difíciles de diagnosticar: es el `upload_tmp_dir`
de PHP-FPM, y si no existe o no es escribible, PHP descarta silenciosamente los ficheros subidos,
que llegan vacíos en `$_FILES` sin ningún error visible.

## 12. Orden del primer despliegue

El orden recomendado no es el mismo para el primer despliegue que para los siguientes:

| Primer despliegue | Despliegues posteriores |
|---|---|
| 1. `composer-install` | 1. `backup-db-local` (o `backup-db`) |
| 2. `frontend-build` | 2. `composer-install` |
| 3. `sync-backend` | 3. `frontend-build` |
| 4. `sync-frontend` | 4. `sync-backend` |
| 5. `env-file` | 5. `sync-frontend` |
| 6. `jwt-keys` | 6. `env-file` (si cambió alguna variable) |
| 7. `migrate` | 7. `migrate` |
| 8. `clear-cache` | 8. `clear-cache` |
| 9. **`permissions`** | 9. **`permissions`** |
| 10. `create-admin` | — |

El motivo de ejecutar `permissions` al final, después de `clear-cache` y no antes, es que
`clear-cache` borra y regenera `var/cache/prod/`, y los directorios que se crean de nuevo nacen con
la propiedad y el `umask` del usuario SSH, no con los permisos que `permissions` acababa de
aplicar. Si `permissions` se ejecuta antes, ese trabajo se pierde en cuanto se limpia la caché.

> **Nota:** el array `ORDERED_ACTIONS` (líneas 739-755), que alimenta las opciones del checkbox
> (`askActions`, línea 833), refleja ya este orden, y es también el orden en que el script las
> ejecuta: el checkbox de `inquirer` **devuelve las acciones marcadas en el orden de la lista, no
> en el orden en que las marcas** (`getCurrentValue`, en `inquirer/lib/prompts/checkbox.js`). Por
> eso puedes marcarlas en cualquier orden y `permissions` se ejecutará siempre después de
> `clear-cache`, en la misma pasada.

## 13. Modo no interactivo

El script también puede invocarse sin pasar por el menú interactivo, indicando entorno y acción
como argumentos:

```bash
cd scripts/deploy && node deploy.js --env=pre --action=sync-backend
```

El valor de `--env` se valida (línea 885) contra los entornos declarados en la configuración, y el
de `--action` (línea 893) contra la lista de acciones disponibles. Cada invocación ejecuta
**una única acción**; para encadenar varias hay que invocar el script varias veces.

## 14. Nota sobre CDN (Cloudflare)

Si el dominio del proyecto está detrás de Cloudflare (u otro CDN), el comportamiento de caché no es
uniforme para todos los recursos:

| Recurso | Comportamiento |
|---|---|
| `assets/*` (nombre con hash) | Sin problema: cada build genera URLs nuevas |
| `index.html` | `cf-cache-status: DYNAMIC`, no se cachea |
| **Nombre fijo** (`favicon.ico`, cualquier cosa en `public/`) | **Se cachea**: tras desplegar se sigue sirviendo la versión anterior |

Los ficheros de nombre fijo requieren purgar la caché del CDN tras el despliegue para que se vea el
cambio. Un truco rápido de verificación es forzar la petición con un parámetro que rompa la caché:

```bash
curl -I "https://<url>/app/favicon.ico?v=$(date +%s)"
# cf-cache-status: MISS  → estás viendo el origen, no la caché
```

Una alternativa más fiable es comprobar directamente en el servidor por SSH, con la acción
`run-command` (usando `cat`, `ls -la` o `md5sum`), que no pasa por el CDN.

## 15. Limitaciones de seguridad conocidas

El script tiene cuatro limitaciones de seguridad conocidas y aceptadas conscientemente por su
diseño:

| Limitación | Línea real | Motivo y recomendación |
|---|---|---|
| Contraseña SSH pasada a `sshpass -p`, **visible en el `ps` local** | **222** (dentro de `rsyncPush`, líneas 213-226) | Solo la usa así `rsync`; la conexión SSH interactiva (`node-ssh`, líneas 789-794) la pasa en proceso, no como argumento visible. Mejora futura: claves SSH o, al menos, `sshpass -e` |
| `StrictHostKeyChecking=no` y `UserKnownHostsFile=/dev/null` | **211** (`sshOptions`, líneas 210-211) | Desactiva la verificación de la huella del servidor: no hay protección frente a un ataque de tipo man-in-the-middle |
| Fallback a `chmod 0777` | **603-605** (mensaje en la 608; decisión en las líneas 575/592) | Lo correcto es que sistemas habilite `setfacl` o iguale la propiedad de los ficheros con la del usuario de PHP-FPM; `0777` es un parche temporal, no una solución |
| `private.pem` con permisos `0644` | **518** (nota al usuario en la línea 522) | Necesario para que PHP-FPM pueda leer la clave. Está cifrada con la passphrase configurada, pero cualquier usuario del sistema con acceso al servidor puede leer el fichero |

Además de estas cuatro, hay otras exposiciones de credenciales en el propio proceso que conviene
conocer, aunque no estén cubiertas por las limitaciones anteriores:

| Exposición | Línea | Visible en |
|---|---|---|
| `PGPASSWORD=<pass> pg_dump …` | 622 | `ps` del **servidor** |
| `-e PGPASSWORD=<pass>` en `docker run` | 623 | `ps` local **y `docker inspect`** |
| Passphrase JWT vía `openssl -passout pass:<pp>` | 486-487 | `ps` del **servidor** |
| Contraseña del admin como argumento de `bin/console` | 663 | `ps` del **servidor** |

Como mejoras futuras generales: sustituir la autenticación SSH por contraseña por claves SSH (o al
menos `sshpass -e` en vez de `-p`), usar `~/.pgpass` para las credenciales de PostgreSQL, y sustituir
el fallback de `chmod 0777` por una configuración correcta de `setfacl`.

## 16. Resolución de problemas

- **La aplicación devuelve 500 en todas las rutas y `var/log/prod.log` no existe o no crece →
  revisa `ROLLBAR_TOKEN`.** Es el único fallo que no deja rastro en los logs de la aplicación. El token es
  obligatorio y debe medir exactamente 32 o 96 caracteres; el propio despliegue lo comprueba antes
  de escribir nada (líneas 290-296), pero si alguien edita a mano el `.env.local` del servidor con
  un valor de longitud incorrecta, la aplicación devuelve un 500 mudo. Poner
  `ROLLBAR_LOGGER_ENABLED=false` **no** protege frente a esto: `backend/config/services.yaml:19-20`
  inyecta `ROLLBAR_TOKEN` sin condicionarlo a esa variable. Este comportamiento está trackeado como
  la issue #89, todavía abierta.
- **El cambio desplegado no se ve en el navegador** → antes de sospechar del servidor, comprueba la
  cabecera `cf-cache-status` (ver §14): es muy probable que sea la caché del CDN.
- **Los deep links de la SPA devuelven 404** → revisa `AllowOverride` y `mod_rewrite` en el vhost,
  o si `paths.frontend` está desalineado respecto a `VITE_BASE_URL`.
- **La raíz del dominio devuelve «No route found for "GET /"»** → el redirect a la SPA de
  `backend/public/.htaccess` falta o apunta a otro directorio (ver §9); si el fichero es correcto,
  falta `AllowOverride` en `public/`.
- **Las subidas de ficheros llegan vacías** → comprueba que `tmp/` existe y es escribible; es el
  `upload_tmp_dir` de PHP-FPM (ver §11).
- **La cabecera `Bearer` se ignora y la autenticación JWT no funciona** → falta la directiva
  `SetEnvIf Authorization` en el vhost (ver §5).
- **La copia de seguridad sale vacía** → revisa la VPN, el host y las credenciales de la base de
  datos.
- **Aparece un `frontend/.env.local.deploybak` huérfano** → indica que un build anterior (acción
  `frontend-build`) se interrumpió a mitad de proceso.
- **El script avisa de que «el contenedor de PHP no está levantado»** → ejecuta `make start` antes
  de reintentar `composer-install`.

## 17. Limitaciones de alcance

Para terminar, un resumen de lo que este script deliberadamente no cubre: solo soporta PostgreSQL,
sin `mysqldump`; solo autenticación SSH por contraseña, sin claves SSH; en modo no interactivo,
una única acción por invocación; y no ofrece ningún mecanismo de rollback ni de purga automática de
CDN.
