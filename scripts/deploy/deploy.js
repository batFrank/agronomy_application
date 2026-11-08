const path = require('path');
const fs = require('fs');
const inquirer = require('inquirer');
const { NodeSSH } = require('node-ssh');
const {
    runNodeCommand,
    getNamedArgs,
    getLocalBranches,
    getCurrentBranch,
    timestamp,
    parseEnvKeys,
    envQuote,
    normalizeBaseUrl,
    parseEnvValues,
    parseMakefileVar,
} = require('./utils');

// ---------------------------------------------------------------------------
// Rutas y constantes del proyecto
// ---------------------------------------------------------------------------
const PROJECT_ROOT = path.resolve(__dirname, '..', '..');
const BACKEND_DIR = path.join(PROJECT_ROOT, 'backend');
const FRONTEND_DIR = path.join(PROJECT_ROOT, 'frontend');
const FRONTEND_DIST = path.join(FRONTEND_DIR, 'dist');
const CONFIG_PATH = path.join(__dirname, 'deploy.config.json');
// Fuente de verdad de QUÉ variables necesita la aplicación. Es el fichero que un
// fork ya edita al añadir una variable propia, así que no hay ninguna lista que
// mantener sincronizada aquí dentro.
const BACKEND_ENV_PATH = path.join(BACKEND_DIR, '.env');
const FRONTEND_ENV_PATH = path.join(FRONTEND_DIR, '.env');
// .htaccess del DocumentRoot. A diferencia del de la SPA (spaHtaccess), este no
// se genera: se versiona y sube con rsync, así que su redirect de la raíz lleva
// la ruta base cableada y hay que comprobar que sigue coincidiendo (ver
// checkSpaBase).
const PUBLIC_HTACCESS_PATH = path.join(BACKEND_DIR, 'public', '.htaccess');
const MAKEFILE_PATH = path.join(PROJECT_ROOT, 'Makefile');

// Directorios/ficheros locales que NO deben sincronizarse al servidor.
// var/ y config/jwt/ se excluyen para no pisar el estado del servidor: rsync
// --delete respeta las exclusiones y no las borra en destino. La carpeta donde
// vive el frontend desplegado NO está aquí: se deriva en syncBackend() a partir
// de la ruta base de la SPA, porque esa ruta depende del proyecto (ver
// spaBaseUrl) y no es necesariamente public/app/.
// Un patrón de rsync que NO empieza por «/» casa con ese nombre a CUALQUIER
// profundidad del árbol transferido. Por eso los directorios del proyecto van
// anclados con «/»: sin el ancla, «Storage/» o «tests/» también excluirían
// vendor/symfony/security-core/Authentication/Token/Storage/ y compañía,
// subiendo un vendor/ mutilado que revienta el autoloader en el servidor.
const BACKEND_RSYNC_EXCLUDES = [
    // Directorios y ficheros del proyecto: anclados a la raíz del backend.
    '/var/',
    '/tests/',
    '/specs/',
    '/config/jwt/',
    '/public/uploads/',
    '/backups/',
    '/Storage/',
    '/tmp/',
    // Sin anclar de forma deliberada: nunca queremos subirlos, esté donde esté.
    '.git',
    '.env.local',
    '.env.*.local',
    'node_modules/',
    '.idea/',
    '.php-cs-fixer.cache',
    '.phpunit.cache/',
    '*.cache',
];

// Fallback del router de la SPA (Vue history mode). Se escribe dentro de la
// carpeta del frontend en cada sync-frontend. La ruta base se deriva del
// VITE_BASE_URL del entorno (ver spaBaseUrl) para no cablear la del proyecto de
// origen. Las sustituciones son RELATIVAS a propósito: mod_rewrite les antepone
// el RewriteBase, así que «index.html» resuelve a «<base>index.html» sin
// repetir la ruta en cada regla.
const spaHtaccess = (baseUrl) => `# Generado por el script de despliegue. Fallback del router de la SPA (${baseUrl}).
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase ${baseUrl}

    # Sirve directamente los ficheros/directorios existentes (assets, index.html...)
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]

    # Cualquier otra ruta (deep-link del router) -> index.html
    RewriteRule ^ index.html [L]
</IfModule>
`;

// Ruta base con la que se sirve la SPA, en el mismo orden en que la resuelve
// loadEnv de Vite durante el build: el .env.local que escribe frontend-build a
// partir de environments.<entorno>.frontend gana al frontend/.env versionado, y
// si ninguno la declara manda el valor por defecto de vite.config.js.
// Toma el objeto por parámetro (como definedEntries) para poder comprobarla sin
// montar el estado del script.
// El «|| {}» cubre un «frontend» a null en el deploy.config.json, que el valor
// por defecto del parámetro no atrapa: sin él, un config mal editado rompería
// sync-backend con un TypeError en vez de con un mensaje útil.
const spaBaseUrl = (frontendVars) =>
    normalizeBaseUrl((frontendVars || {}).VITE_BASE_URL ?? parseEnvValues(FRONTEND_ENV_PATH).VITE_BASE_URL);

// Ruta a la que backend/public/.htaccess redirige la raíz del dominio, o null si
// no hay redirect. El parseo es deliberadamente simple: la regla es de una línea
// y el prefijo %{ENV:BASE} (instalaciones en subdirectorio) no forma parte de la
// ruta base de la SPA.
const publicHtaccessRootTarget = () => {
    if (!fs.existsSync(PUBLIC_HTACCESS_PATH)) return null;

    const rule = fs.readFileSync(PUBLIC_HTACCESS_PATH, 'utf8').match(/^\s*RewriteRule\s+\^\$\s+(\S+)/m);

    return rule ? normalizeBaseUrl(rule[1].replace('%{ENV:BASE}', '')) : null;
};

// Dos parametrizaciones que tienen que contar la misma historia: la ruta base
// con la que se compila la SPA y el directorio del servidor donde se sube. Si
// discrepan, rsync deja el bundle en un sitio y el navegador lo pide en otro.
const checkSpaBase = (baseUrl) => {
    if (baseUrl === '/') {
        throw new Error(
            'VITE_BASE_URL apunta a la raíz del dominio y frontend/vite.config.js:9 genera «base: //» para ese valor,\n' +
                '   así que el bundle quedaría roto. Sirve la SPA en un subdirectorio (por ejemplo /app/).',
        );
    }

    const dir = baseUrl.replace(/(^\/|\/$)/g, '');
    const remote = String(cfg.paths.frontend).replace(/\/+$/, '');
    if (!remote.endsWith(`/${dir}`)) {
        console.log(`⚠️  «paths.frontend» (${cfg.paths.frontend}) no termina en «${dir}», la ruta base de la SPA (${baseUrl}).`);
        console.log('   Es correcto solo si un Alias de Apache publica ese directorio en esa ruta; si no, los deep links darán 404.');
    }

    // Symfony no sirve la raíz del dominio (no hay ruta "/"), así que quien la
    // manda a la SPA es el redirect de backend/public/.htaccess. Ese fichero se
    // versiona con la ruta cableada, de modo que un proyecto que sirva la SPA en
    // otro directorio se queda con un "/" que redirige a donde no hay nada.
    const rootTarget = publicHtaccessRootTarget();
    if (rootTarget === null) {
        console.log('⚠️  backend/public/.htaccess no redirige la raíz del dominio: «/» devolverá el 404 «No route found for GET /».');
        console.log(`   Añade «RewriteRule ^$ %{ENV:BASE}${baseUrl} [R=302,L]» antes de las reglas de Symfony.`);
    } else if (rootTarget !== baseUrl) {
        console.log(`⚠️  backend/public/.htaccess redirige la raíz a «${rootTarget}», pero la SPA se sirve en «${baseUrl}».`);
        console.log('   Corrige la regla «RewriteRule ^$ …» del fichero o «/» acabará en una carpeta vacía.');
    }

    // Vite resuelve .env.production y .env.production.local DESPUÉS del .env.local
    // que escribe frontend-build, así que su VITE_BASE_URL ganaría al del
    // deploy.config.json sin que este script se entere.
    const overrides = ['.env.production', '.env.production.local'].filter((f) => fs.existsSync(path.join(FRONTEND_DIR, f)));
    if (overrides.length) {
        console.log(`⚠️  Vite da prioridad a estos ficheros sobre el .env.local que escribe el despliegue: frontend/${overrides.join(', frontend/')}.`);
        console.log(`   Comprueba que su VITE_BASE_URL coincide con ${baseUrl} o el .htaccess quedará desalineado con el build.`);
    }
};

const ssh = new NodeSSH();
const TS = timestamp();

// ---------------------------------------------------------------------------
// Estado seleccionado
// ---------------------------------------------------------------------------
let config = null;
let selectedEnv = null;
let cfg = null; // config.environments[selectedEnv]

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
const loadConfig = () => {
    if (!fs.existsSync(CONFIG_PATH)) {
        console.log('❌ No existe scripts/deploy/deploy.config.json');
        console.log('   Copia deploy.config.example.json a deploy.config.json y rellena las credenciales.');
        process.exit(1);
    }
    try {
        config = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf8'));
    } catch (error) {
        console.log('❌ Error al parsear deploy.config.json:', error.message);
        process.exit(1);
    }
    if (!config.environments || !Object.keys(config.environments).length) {
        console.log('❌ deploy.config.json no contiene «environments»');
        process.exit(1);
    }
};

const sshEscape = (value) => `'${String(value).replace(/'/g, "'\\''")}'`;

const sshRun = async (command, { cwd = cfg.paths.backend, allowFail = false, stream = true } = {}) => {
    const fullCommand = cwd ? `cd ${sshEscape(cwd)} && ${command}` : command;

    if (stream) {
        const result = await ssh.execCommand(fullCommand, {
            onStdout: (chunk) => process.stdout.write(chunk.toString()),
            onStderr: (chunk) => process.stderr.write(chunk.toString()),
        });
        if (result.code !== 0 && !allowFail) {
            throw new Error(`Comando remoto falló (código ${result.code}): ${command}`);
        }
        return result;
    }

    const result = await ssh.execCommand(fullCommand);
    if (result.code !== 0 && !allowFail) {
        throw new Error(`Comando remoto falló (código ${result.code}): ${command}\n${result.stderr}`);
    }
    return result;
};

const sshOptions = () =>
    `-p ${cfg.ssh.port || 22} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null`;

const rsyncPush = async (localPath, remotePath, excludes = []) => {
    const { host, username, password } = cfg.ssh;
    const excludeFlags = excludes.map((e) => `--exclude=${sshEscape(e)}`).join(' ');

    // Aseguramos la carpeta padre en el servidor antes de sincronizar.
    const remoteParent = path.posix.dirname(remotePath.replace(/\/$/, ''));

    const command = `rsync -az --delete ${excludeFlags} \
        --rsync-path="mkdir -p ${sshEscape(remoteParent)} && rsync" \
        -e "sshpass -p ${sshEscape(password)} ssh ${sshOptions()}" \
        ${sshEscape(localPath)} ${sshEscape(`${username}@${host}:${remotePath}`)}`;

    await runNodeCommand(command, { silentMode: false, loadingMode: false });
};

// Valores que el script deduce de la propia configuración del entorno, para no
// obligar a repetir en el deploy.config.json lo que ya se sabe de otra sección
// (la base de datos) o lo que es fijo en cualquier despliegue (rutas del JWT,
// entorno de Symfony, transporte de Messenger).
const derivedBackendEnv = () => ({
    APP_ENV: 'prod',
    DB_DRIVER: 'pdo_pgsql',
    DB_HOST: cfg.db.host,
    DB_PORT: String(cfg.db.port || 5432),
    DB_VERSION: String(cfg.db.version || '17'),
    DB_NAME: cfg.db.database,
    DB_USERNAME: cfg.db.username,
    DB_PASSWORD: cfg.db.password,
    DEFAULT_URI: cfg.url,
    MESSENGER_TRANSPORT_DSN: 'doctrine://default?auto_setup=0',
    JWT_SECRET_KEY: '%kernel.project_dir%/config/jwt/private.pem',
    JWT_PUBLIC_KEY: '%kernel.project_dir%/config/jwt/public.pem',
    ROLLBAR_ENVIRONMENT: selectedEnv,
});

// Descarta las claves sin valor para que un «backend» declarado a medias no
// pise con undefined lo que aporta el nivel inferior.
const definedEntries = (source) =>
    Object.fromEntries(Object.entries(source || {}).filter(([, value]) => value !== undefined && value !== null));

// Un repositorio despliega SIEMPRE el mismo proyecto a distintos entornos, así
// que qué variables existen es propiedad del proyecto y qué valor tienen es
// propiedad del entorno. De ahí los dos niveles: lo que no cambia se declara una
// sola vez en el «backend» de la raíz.
// Precedencia, de mayor a menor: entorno → proyecto → derivado.
const resolveBackendEnv = () => ({
    ...derivedBackendEnv(),
    ...definedEntries(config.backend),
    ...definedEntries(cfg.backend),
});

// El .env versionado viaja al servidor y Symfony lo carga antes que el
// .env.local, así que toda variable que no escribamos aquí hereda en silencio el
// valor del entorno local de desarrollo. Con DB_HOST el fallo es inmediato, pero
// con MAILER_DSN o EMAIL_BASE_URL la aplicación funciona y el problema solo
// aparece en los correos que recibe el usuario final.
const validateBackendEnv = (resolved, declaredKeys) => {
    const problems = [];

    const missing = declaredKeys.filter((key) => resolved[key] === undefined);
    if (missing.length) {
        problems.push(
            `Sin valor en deploy.config.json: ${missing.join(', ')}.\n` +
                '   Están declaradas en backend/.env, así que en el servidor heredarían el valor de desarrollo.',
        );
    }

    const empty = Object.keys(resolved)
        .filter((key) => !missing.includes(key))
        .filter((key) => String(resolved[key]).trim() === '');
    if (empty.length) {
        problems.push(`Declaradas con valor vacío: ${empty.join(', ')}.`);
    }

    // Un token inválido revienta el arranque de la aplicación al construir el
    // contenedor de servicios, y lo hace ANTES de que exista Monolog: la
    // respuesta es un 500 y no se escribe nada en var/log.
    const token = resolved.ROLLBAR_TOKEN;
    if (token !== undefined && ![32, 96].includes(String(token).length)) {
        problems.push(
            `ROLLBAR_TOKEN debe tener 32 o 96 caracteres (recibido: ${String(token).length}).\n` +
                '   Un token inválido impide arrancar la aplicación aunque ROLLBAR_LOGGER_ENABLED sea «false».',
        );
    }

    if (problems.length) {
        throw new Error(`Configuración incompleta, no se ha escrito nada en el servidor:\n   - ${problems.join('\n   - ')}`);
    }
};

const buildBackendEnv = () => {
    const declaredKeys = parseEnvKeys(BACKEND_ENV_PATH);
    if (!declaredKeys.length) {
        console.log(`⚠️  No se han encontrado variables en ${BACKEND_ENV_PATH}; se usarán solo las del deploy.config.json.`);
    }

    const resolved = resolveBackendEnv();
    validateBackendEnv(resolved, declaredKeys);

    // Las que el proyecto declara en su .env, en el mismo orden, y al final las
    // que solo existen en la configuración del despliegue.
    const extraKeys = Object.keys(resolved).filter((key) => !declaredKeys.includes(key));
    if (extraKeys.length) {
        console.log(`ℹ️  Variables del deploy.config.json que no están en backend/.env: ${extraKeys.join(', ')}.`);
        console.log('   Revisa que no sean una errata; se escriben igualmente.');
    }

    const lines = [
        '# Archivo generado automáticamente por el script de despliegue.',
        `# Entorno: ${selectedEnv} · Generado: ${TS}`,
        '',
        ...declaredKeys.filter((key) => resolved[key] !== undefined).map((key) => `${key}=${envQuote(resolved[key])}`),
    ];

    if (extraKeys.length) {
        lines.push('', '# Variables específicas de este proyecto', ...extraKeys.map((key) => `${key}=${envQuote(resolved[key])}`));
    }

    lines.push('');
    return lines.join('\n');
};

const confirm = async (message, def = false) => {
    const { value } = await inquirer.prompt([{ type: 'confirm', name: 'value', message, default: def }]);
    return value;
};

// ---------------------------------------------------------------------------
// Acciones locales (build)
// ---------------------------------------------------------------------------
// El Makefile ya es la fuente de verdad del nombre del contenedor de PHP: lo usan
// todos sus targets, así que un fork que lo renombre no tiene que repetirlo en el
// deploy.config.json. Precedencia: configuración -> Makefile -> nombre de la base.
const phpContainer = () =>
    (config && config.settings && config.settings.phpContainer) ||
    parseMakefileVar(MAKEFILE_PATH, 'PHP_CONTAINER') ||
    'symfony-base-php';

// Sin esta comprobación, un nombre de contenedor equivocado (o Docker parado) sale
// como un «Error: No such container» de Docker en medio de la salida del script,
// que no dice ni qué valor se ha usado ni de dónde viene. Listar lo que SÍ está
// levantado es lo que resuelve el caso real: el nombre está mal escrito.
const ensureContainerRunning = async (container) => {
    let running = '';
    try {
        running = await runNodeCommand('docker ps --format "{{.Names}}"', { silentMode: true, loadingMode: false });
    } catch (error) {
        throw new Error('No se ha podido consultar Docker. ¿Está el demonio arrancado y tu usuario en el grupo «docker»?');
    }

    const names = running.split('\n').map((name) => name.trim()).filter(Boolean);
    if (names.includes(container)) return;

    throw new Error(
        `El contenedor de PHP «${container}» no está levantado.\n` +
            '   Arráncalo con «make start», o ajusta «settings.phpContainer» en scripts/deploy/deploy.config.json.\n' +
            `   Contenedores en ejecución: ${names.length ? names.join(', ') : '(ninguno)'}`,
    );
};

const composerInstall = async () => {
    const container = phpContainer();
    await ensureContainerRunning(container);
    process.stdout.write('📦 composer install --no-dev (en el contenedor PHP)...\n');
    await runNodeCommand(
        `docker exec ${container} composer install --no-dev --optimize-autoloader --no-interaction`,
        { silentMode: false, loadingMode: false },
    );
    console.log('✅ dependencias de composer instaladas (sin dev)');
    console.log('   ⚠️  Para volver a desarrollar en local ejecuta «make composer-install».');
};

const ensureFrontendDirsWritable = () => {
    for (const dir of [FRONTEND_DIST, path.join(FRONTEND_DIR, 'node_modules')]) {
        if (!fs.existsSync(dir)) continue;
        try {
            fs.accessSync(dir, fs.constants.W_OK);
        } catch (error) {
            throw new Error(
                `${path.relative(PROJECT_ROOT, dir)} existe pero no es escribible por tu usuario.\n` +
                    '   Suele venir de un build hecho dentro del contenedor, que lo deja como root.\n' +
                    `   Elimínalo y reintenta: sudo rm -rf ${dir}`,
            );
        }
    }
};

const frontendBuild = async () => {
    // Precondición, antes de tocar el .env.local: el contenedor de bun corre con
    // --user <uid>:<gid>, así que un dist/ o node_modules/ que haya quedado de un
    // build hecho DENTRO del contenedor (propiedad de root) no es escribible y el
    // build muere con un volcado de pila de Vite que no menciona los permisos.
    ensureFrontendDirsWritable();

    const image = (config.settings && config.settings.bunImage) || 'oven/bun:latest';
    const uid = process.getuid ? process.getuid() : 1000;
    const gid = process.getgid ? process.getgid() : 1000;

    // Vite carga las variables VITE_* desde ficheros .env (loadEnv). El build se
    // ejecuta con --mode=production, así que la precedencia real es
    // .env.production.local > .env.production > .env.local > .env. Escribimos la
    // config del entorno en .env.local: por debajo de los dos «production» (que
    // este proyecto no versiona, ver checkSpaBase) pero por encima del .env base.
    const envLocalPath = path.join(FRONTEND_DIR, '.env.local');
    const backupPath = `${envLocalPath}.deploybak`;
    const hadPrevious = fs.existsSync(envLocalPath);
    if (hadPrevious) fs.renameSync(envLocalPath, backupPath);

    const frontendVars = cfg.frontend || {};
    const envContent = Object.entries(frontendVars)
        .map(([k, v]) => `${k}=${v}`)
        .join('\n');
    fs.writeFileSync(envLocalPath, `# Generado por deploy (${selectedEnv}, ${TS})\n${envContent}\n`);

    try {
        process.stdout.write(`🏗️  Build del frontend con Docker (${image}) [entorno: ${selectedEnv}]...\n`);
        const buildCmd = [
            `docker run --rm --user ${uid}:${gid}`,
            `-e HOME=/tmp -e BUN_INSTALL_CACHE_DIR=/tmp/.bun`,
            `-v ${sshEscape(FRONTEND_DIR)}:/app -w /app`,
            image,
            // «build-favicon» copia el favicon del cliente (o el genérico) a
            // frontend/public/, que es el publicDir de Vite. El fichero está en el
            // .gitignore del frontend a propósito: se genera en cada build. Sin este
            // paso el dist sale sin favicon. Fuente de verdad de los pasos del build:
            // frontend/src/core/scripts/build.scripts.js
            `sh -c "bun install --frozen-lockfile && bun run build-favicon && bun run vite build --mode=production"`,
        ].join(' ');

        await runNodeCommand(buildCmd, { silentMode: false, loadingMode: false });

        if (!fs.existsSync(FRONTEND_DIST)) {
            throw new Error(`No se generó la carpeta dist en: ${FRONTEND_DIST}`);
        }
        console.log(`✅ frontend construido en frontend/dist (API: ${frontendVars.VITE_API_URL || '—'})`);
    } finally {
        // Restauramos el .env.local previo (si existía) o eliminamos el temporal.
        fs.rmSync(envLocalPath, { force: true });
        if (hadPrevious) fs.renameSync(backupPath, envLocalPath);
    }
};

// ---------------------------------------------------------------------------
// Acciones remotas
// ---------------------------------------------------------------------------
const syncBackend = async () => {
    process.stdout.write('🔄 Sincronizando backend...\n');
    // El frontend desplegado vive dentro de public/, en la ruta base de la SPA, y
    // rsync corre con --delete: el exclude tiene que derivarse de esa misma base o
    // un sync-backend borraría del servidor la SPA de cualquier proyecto que no se
    // sirva en /app/.
    const spaExclude = `/public${spaBaseUrl(cfg.frontend)}`;
    await rsyncPush(`${BACKEND_DIR}/`, cfg.paths.backend, [...BACKEND_RSYNC_EXCLUDES, spaExclude]);
    console.log(`✅ backend sincronizado (preservado ${spaExclude} en el servidor)`);
};

const syncFrontend = async () => {
    if (!fs.existsSync(FRONTEND_DIST)) {
        throw new Error('No existe frontend/dist. Ejecuta antes «frontend-build».');
    }
    const baseUrl = spaBaseUrl(cfg.frontend);
    checkSpaBase(baseUrl);

    process.stdout.write('🔄 Sincronizando frontend (dist)...\n');
    await rsyncPush(`${FRONTEND_DIST}/`, cfg.paths.frontend, []);

    // Escribimos el .htaccess de fallback del router dentro de la carpeta del frontend.
    const htaccessPath = path.posix.join(cfg.paths.frontend, '.htaccess');
    await sshRun(`cat > ${sshEscape(htaccessPath)} <<'DEPLOY_EOF'\n${spaHtaccess(baseUrl)}\nDEPLOY_EOF`, {
        cwd: null,
        stream: false,
    });
    console.log(`✅ frontend sincronizado (+ .htaccess de la SPA, RewriteBase ${baseUrl})`);
};

const createEnvFile = async () => {
    const content = buildBackendEnv();
    // Escribimos el .env.local remoto vía heredoc para preservar el contenido íntegro.
    const remoteFile = path.posix.join(cfg.paths.backend, '.env.local');
    await sshRun(
        `mkdir -p ${sshEscape(cfg.paths.backend)} && cat > ${sshEscape(remoteFile)} <<'DEPLOY_EOF'\n${content}\nDEPLOY_EOF`,
        { cwd: null, stream: false },
    );
    console.log('✅ .env.local remoto generado');
};

const generateJwtKeys = async () => {
    // Por la resolución completa, no por cfg.backend: la passphrase puede estar
    // declarada a nivel de proyecto y ser común a todos los entornos.
    const pp = resolveBackendEnv().JWT_PASSPHRASE;
    if (!pp) throw new Error('Falta JWT_PASSPHRASE en la configuración (nivel de proyecto o de entorno).');

    const exists = await sshRun('test -f config/jwt/private.pem && echo yes || echo no', { stream: false });
    if (exists.stdout.trim() === 'yes') {
        const overwrite = await confirm('Ya existen claves JWT en el servidor. ¿Regenerarlas?', false);
        if (!overwrite) {
            console.log('↩️  Se conservan las claves JWT existentes');
            return;
        }
    }

    await sshRun(
        `mkdir -p config/jwt && \
        openssl genrsa -passout pass:${sshEscape(pp)} -aes256 -out config/jwt/private.pem 4096 && \
        openssl rsa -passin pass:${sshEscape(pp)} -in config/jwt/private.pem -pubout -out config/jwt/public.pem && \
        chmod 0755 config/jwt && chmod 0644 config/jwt/private.pem config/jwt/public.pem`,
        { stream: false },
    );
    console.log('✅ claves JWT generadas en el servidor (legibles por el usuario de FPM)');
    console.log('   Nota: private.pem queda con permiso de lectura 0644, pero está cifrada con la passphrase.');
};

const runMigrations = async () => {
    process.stdout.write('🗄️  Ejecutando migraciones...\n');
    await sshRun(`${cfg.phpBin} bin/console doctrine:migrations:sync-metadata-storage --no-interaction`, {
        allowFail: true,
    });
    await sshRun(
        `${cfg.phpBin} bin/console doctrine:migrations:migrate --allow-no-migration --no-interaction --env=prod`,
    );
    console.log('✅ migraciones aplicadas');
};

const clearCache = async () => {
    process.stdout.write('🧹 Limpiando caché de Symfony...\n');
    await sshRun(`${cfg.phpBin} bin/console cache:clear --env=prod --no-debug`);
    await sshRun(`${cfg.phpBin} bin/console cache:warmup --env=prod --no-debug`, { allowFail: true });
    console.log('✅ caché regenerada');
};

// Raíces sobre las que aplicar permisos de escritura para FPM.
// var/            -> caché y logs
// Storage/        -> ficheros privados de la aplicación
// public/uploads/ -> ficheros públicos (filesystem PUBLIC_LOCAL)
// tmp/            -> upload_tmp_dir de PHP-FPM (subidas HTTP); si no existe/es escribible,
//                    PHP descarta los ficheros subidos y llegan vacíos ($_FILES).
const WRITABLE_ROOTS = ['var', 'Storage', 'public/uploads', 'tmp'];
// Subdirectorios que deben existir para que la app pueda escribir desde el primer uso.
const WRITABLE_DIRS = ['var/cache', 'var/log', 'var/tmp/files', 'var/chunks', 'Storage', 'public/uploads', 'tmp'];

const setPermissions = async () => {
    const fpmUser = cfg.fpmUser;
    process.stdout.write('🔐 Ajustando permisos de var/ y Storage/...\n');
    await sshRun(`mkdir -p ${WRITABLE_DIRS.map(sshEscape).join(' ')}`, { allowFail: true });

    // Un «fpmUser» que no existe en el servidor no produce ningún error visible: setfacl
    // falla (o aplica las ACLs a un usuario equivocado) y el problema solo aparece más
    // tarde, como una aplicación que no puede escribir en su propia caché. Lo comprobamos
    // una vez, antes de usarlo, para poder distinguir ese caso del de «setfacl ausente».
    const fpmUserExists = fpmUser
        ? 0 === (await sshRun(`id ${sshEscape(fpmUser)}`, { allowFail: true, stream: false })).code
        : false;

    if (fpmUser && !fpmUserExists) {
        console.log(`⚠️  El usuario «${fpmUser}» declarado en «fpmUser» no existe en el servidor.`);
        console.log('   Sigo con chmod, pero conviene corregirlo: mientras el valor sea incorrecto,');
        console.log('   PHP-FPM no recibe los permisos que esta acción cree estar dándole.');
        console.log('   Para averiguar el usuario real, desde el directorio del proyecto en el servidor:');
        console.log("   printf '<?php echo get_current_user();' > public/_ver.php");
        console.log('   y consúltalo con: curl https://<url>/_ver.php && rm public/_ver.php');
    }

    if (fpmUserExists) {
        // El usuario de PHP-FPM es distinto del de despliegue, así que damos acceso
        // de escritura vía ACLs (recomendado por Symfony): -R para lo existente y
        // -dR (default) para que los ficheros nuevos (prod.log, subidas…) hereden
        // el permiso.
        const aclCmd = WRITABLE_ROOTS.map(
            (root) =>
                `setfacl -R -m u:${sshEscape(fpmUser)}:rwX ${sshEscape(root)} && ` +
                `setfacl -dR -m u:${sshEscape(fpmUser)}:rwX ${sshEscape(root)}`,
        ).join(' && ');
        const acl = await sshRun(aclCmd, { allowFail: true });
        if (acl.code === 0) {
            console.log(`✅ ACLs aplicadas a ${WRITABLE_ROOTS.join('/, ')}/ para el usuario de FPM «${fpmUser}»`);
            return;
        }
        console.log(`⚠️  setfacl no está disponible o el FS no lo soporta (el usuario «${fpmUser}» sí existe);`);
        console.log('   uso chmod en los directorios.');
    } else if (!fpmUser) {
        console.log('ℹ️  Sin «fpmUser» en la config; aplico chmod a los directorios.');
    }

    // Sin ACL y con dos usuarios distintos (deploy vs FPM) no se pueden modificar
    // los ficheros ajenos. Hacemos escribibles solo los DIRECTORIOS que posee el
    // usuario de deploy (chmod requiere ser propietario), sin -R, para que FPM
    // pueda crear ahí sus ficheros. No fallamos si algún subdirectorio pertenece a
    // FPM: ya será escribible por él.
    const roots = WRITABLE_ROOTS.map(sshEscape).join(' ');
    const dirs = WRITABLE_DIRS.map(sshEscape).join(' ');
    await sshRun(
        `find ${roots} -type d -user "$(id -un)" -exec chmod 0777 {} + 2>/dev/null; ` +
            `chmod 0777 ${roots} ${dirs} 2>/dev/null; true`,
        { allowFail: true },
    );
    console.log('✅ directorios de var/ y Storage/ hechos escribibles (0777) donde ha sido posible');
    console.log('   Si algún subdirectorio pertenece a FPM y sigue dando problemas, hará falta');
    console.log('   que sistemas iguale la propiedad o habilite ACL (setfacl).');
};

const backupDatabase = async () => {
    const { db } = cfg;
    const remoteDir = path.posix.join(cfg.paths.backend, 'backups');
    const filename = `${db.database}_${TS}.sql`;
    const remoteFile = path.posix.join(remoteDir, filename);

    const pgDump = db.pgDumpBin || cfg.pgDumpBin || 'pg_dump';
    process.stdout.write(`💾 Backup de base de datos (${pgDump})...\n`);
    await sshRun(
        `mkdir -p ${sshEscape(remoteDir)} && PGPASSWORD=${sshEscape(db.password)} ${pgDump} \
        -h ${sshEscape(db.host)} -p ${db.port || 5432} -U ${sshEscape(db.username)} \
        -d ${sshEscape(db.database)} -f ${sshEscape(remoteFile)}`,
        { cwd: null, stream: false },
    );

    const localDir = path.join(__dirname, 'backups');
    fs.mkdirSync(localDir, { recursive: true });
    await ssh.getFile(path.join(localDir, filename), remoteFile);
    console.log(`✅ backup de BD descargado en scripts/deploy/backups/${filename}`);
};

const backupDatabaseLocal = async () => {
    const { db } = cfg;
    const image = `postgres:${db.version || '17'}`;
    const localDir = path.join(__dirname, 'backups');
    fs.mkdirSync(localDir, { recursive: true });
    const filename = `${db.database}_${selectedEnv}_${TS}.sql`;
    const localFile = path.join(localDir, filename);

    const uid = process.getuid ? process.getuid() : 1000;
    const gid = process.getgid ? process.getgid() : 1000;

    process.stdout.write(`💾 Backup local vía Docker (${image}) contra ${db.host}:${db.port || 5432}...\n`);

    // --network host: el contenedor usa la red del host, así alcanza la IP de la BD
    // enrutada por la VPN. Montamos la carpeta de backups y usamos «pg_dump -f»
    // (en vez de redirigir stdout, que da «bad file descriptor» sin TTY).
    const cmd = [
        'docker run --rm --network host',
        `--user ${uid}:${gid}`,
        `-e PGPASSWORD=${sshEscape(db.password)}`,
        `-v ${sshEscape(localDir)}:/backup`,
        image,
        `pg_dump -h ${sshEscape(db.host)} -p ${db.port || 5432} -U ${sshEscape(db.username)} -d ${sshEscape(db.database)} -f ${sshEscape(`/backup/${filename}`)}`,
    ].join(' ');

    try {
        await runNodeCommand(cmd, { silentMode: false, loadingMode: false });
    } catch (error) {
        fs.rmSync(localFile, { force: true }); // no dejar un .sql vacío si falla
        throw error;
    }

    const size = fs.statSync(localFile).size;
    if (size === 0) {
        fs.rmSync(localFile, { force: true });
        throw new Error('El backup quedó vacío; revisa la conexión VPN, host y credenciales.');
    }
    console.log(`✅ backup guardado en scripts/deploy/backups/${filename} (${Math.round(size / 1024)} KiB)`);
};

const backupFiles = async () => {
    const remoteDir = path.posix.join(cfg.paths.backend, 'backups');
    const filename = `backend_files_${TS}.tar.gz`;
    process.stdout.write('💾 Backup de ficheros del backend...\n');
    await sshRun(
        `mkdir -p ${sshEscape(remoteDir)} && tar --exclude=backups --exclude=var/cache --exclude=var/log \
        -czf ${sshEscape(path.posix.join(remoteDir, filename))} -C ${sshEscape(cfg.paths.backend)} .`,
        { cwd: null, stream: false },
    );
    console.log(`✅ backup de ficheros creado en el servidor (backups/${filename})`);
};

const createAdmin = async () => {
    const { email } = await inquirer.prompt([
        { type: 'input', name: 'email', message: 'Email del admin:', validate: (v) => !!v || 'Requerido' },
    ]);
    const { password } = await inquirer.prompt([
        { type: 'password', name: 'password', message: 'Contraseña (mín. 8):', validate: (v) => (v && v.length >= 8) || 'Mínimo 8 caracteres' },
    ]);
    await sshRun(`${cfg.phpBin} bin/console app:create-user ${sshEscape(email)} ${sshEscape(password)} --admin`);
    console.log('✅ usuario administrador creado');
};

const showLogs = async () => {
    const { lines } = await inquirer.prompt([
        { type: 'input', name: 'lines', message: 'Nº de líneas a mostrar:', default: '80' },
    ]);
    const n = parseInt(lines, 10) || 80;
    process.stdout.write('📜 var/log/prod.log (cola)...\n');
    await sshRun(`tail -n ${n} var/log/prod.log 2>/dev/null || echo "(no existe var/log/prod.log)"`, {
        allowFail: true,
    });
    console.log('');
};

const runRemoteCommand = async () => {
    const { command } = await inquirer.prompt([
        { type: 'input', name: 'command', message: 'Comando a ejecutar (cwd = backend):', validate: (v) => !!v.trim() || 'Requerido' },
    ]);
    await sshRun(command);
    console.log('✅ comando ejecutado');
};

// ---------------------------------------------------------------------------
// Registro de acciones
// ---------------------------------------------------------------------------
const actions = {
    'composer-install': { fn: composerInstall, ssh: false, label: 'Backend: composer install (--no-dev)' },
    'frontend-build': { fn: frontendBuild, ssh: false, label: 'Frontend: build con Docker (bun)' },
    'backup-db': { fn: backupDatabase, ssh: true, label: 'Servidor: backup de base de datos (pg_dump remoto)' },
    'backup-db-local': { fn: backupDatabaseLocal, ssh: false, label: 'Local: backup de BD vía Docker (guardado en local)' },
    'backup-files': { fn: backupFiles, ssh: true, label: 'Servidor: backup de ficheros del backend' },
    'sync-backend': { fn: syncBackend, ssh: true, label: 'Servidor: sincronizar backend (rsync)' },
    'sync-frontend': { fn: syncFrontend, ssh: true, label: 'Servidor: sincronizar frontend (rsync)' },
    'env-file': { fn: createEnvFile, ssh: true, label: 'Servidor: generar .env.local' },
    'jwt-keys': { fn: generateJwtKeys, ssh: true, label: 'Servidor: generar claves JWT' },
    migrate: { fn: runMigrations, ssh: true, label: 'Servidor: ejecutar migraciones' },
    'clear-cache': { fn: clearCache, ssh: true, label: 'Servidor: limpiar/calentar caché' },
    permissions: { fn: setPermissions, ssh: true, label: 'Servidor: ajustar permisos (var/)' },
    'create-admin': { fn: createAdmin, ssh: true, label: 'Servidor: crear usuario administrador' },
    logs: { fn: showLogs, ssh: true, label: 'Servidor: ver var/log/prod.log' },
    'run-command': { fn: runRemoteCommand, ssh: true, label: 'Servidor: ejecutar comando arbitrario' },
};

// Orden recomendado, y también el de ejecución. «permissions» va después de «clear-cache» a propósito: ver §12 del README.
const ORDERED_ACTIONS = [
    'composer-install',
    'frontend-build',
    'backup-db',
    'backup-db-local',
    'backup-files',
    'sync-backend',
    'sync-frontend',
    'env-file',
    'jwt-keys',
    'migrate',
    'clear-cache',
    'permissions',
    'create-admin',
    'logs',
    'run-command',
];

// ---------------------------------------------------------------------------
// Flujo interactivo
// ---------------------------------------------------------------------------
const preDeploymentChecks = async () => {
    // Comprobamos las herramientas una a una para poder decir cuál falta.
    for (const [tool, versionCommand] of [['rsync', 'rsync --version'], ['sshpass', 'sshpass -V']]) {
        try {
            await runNodeCommand(versionCommand, { silentMode: true, loadingMode: false });
        } catch (error) {
            console.log(`❌ Falta «${tool}», y el script de despliegue lo necesita.`);
            console.log(`   Instálalo con: sudo apt install ${tool}`);
            process.exit(1);
        }
    }

    let status;
    try {
        status = (await runNodeCommand('git status --porcelain', { silentMode: true, loadingMode: false })).trim();
    } catch (error) {
        console.log('❌ No se ha podido consultar el estado del repositorio con git.');
        console.log('   Lanza el script desde dentro del repositorio del proyecto (o instala git si falta).');
        process.exit(1);
    }

    if (status !== '') {
        const cont = await confirm('Hay cambios sin confirmar en el repositorio. ¿Continuar de todos modos?', false);
        if (!cont) process.exit(1);
    }
};

const connectSsh = async () => {
    try {
        await ssh.connect({
            host: cfg.ssh.host,
            port: cfg.ssh.port || 22,
            username: cfg.ssh.username,
            password: cfg.ssh.password,
        });
        console.log(`🔌 Conectado por SSH a ${cfg.ssh.username}@${cfg.ssh.host}`);
        return true;
    } catch (error) {
        console.log('❌ Error de conexión SSH:', error.message);
        console.log('   ¿Está la VPN activa y las credenciales son correctas?');
        return false;
    }
};

const executeAction = async (name) => {
    const action = actions[name];
    try {
        console.log(`\n▶️  ${action.label}`);
        await action.fn();
    } catch (error) {
        console.error(`❌ Error en «${name}»: ${error.message}`);
        const retry = await confirm(`¿Reintentar «${name}»?`, false);
        if (retry) return executeAction(name);
        const cont = await confirm('¿Continuar con el resto del despliegue?', false);
        if (!cont) throw new Error('Despliegue cancelado por el usuario');
    }
};

const askEnvironment = async () => {
    const envs = Object.keys(config.environments);
    const { env } = await inquirer.prompt([
        { type: 'list', name: 'env', message: 'Selecciona el entorno:', choices: envs },
    ]);
    return env;
};

const askActions = async () => {
    const { selected } = await inquirer.prompt([
        {
            type: 'checkbox',
            name: 'selected',
            message: 'Selecciona las acciones (en orden de ejecución):',
            pageSize: 20,
            choices: ORDERED_ACTIONS.map((key) => ({ name: actions[key].label, value: key })),
        },
    ]);
    return selected;
};

const maybeCheckoutBranch = async () => {
    const wantSwitch = await confirm(`Rama actual: «${getCurrentBranch()}». ¿Cambiar de rama antes de desplegar?`, false);
    if (!wantSwitch) return;

    const branches = getLocalBranches();
    const { branch } = await inquirer.prompt([
        { type: 'list', name: 'branch', message: 'Selecciona la rama:', choices: branches, pageSize: 20 },
    ]);
    await runNodeCommand(`git checkout ${branch}`, { silentMode: false, loadingMode: false });
    console.log(`✅ Rama «${branch}» seleccionada`);
};

const interactiveDeploy = async () => {
    selectedEnv = await askEnvironment();
    cfg = config.environments[selectedEnv];

    await maybeCheckoutBranch();

    const selected = await askActions();
    if (!selected.length) {
        console.log('No se seleccionó ninguna acción. Fin.');
        return;
    }

    const needsSsh = selected.some((a) => actions[a].ssh);
    if (needsSsh && !(await connectSsh())) return;

    const ok = await confirm(
        `Vas a ejecutar [${selected.join(', ')}] en el entorno «${selectedEnv}» (${cfg.url}). ¿Confirmas?`,
        false,
    );
    if (!ok) {
        console.log('❌ Despliegue cancelado');
        return;
    }

    for (const name of selected) {
        await executeAction(name);
    }

    console.log('\n🎉 Despliegue finalizado');
};

// Modo no interactivo: node deploy.js --env=pre --action=sync-backend
const manualDeploy = async (args) => {
    selectedEnv = args.env;
    if (!selectedEnv || !config.environments[selectedEnv]) {
        console.log('❌ Entorno no válido. Usa --env=pre|pro');
        return;
    }
    cfg = config.environments[selectedEnv];

    const name = args.action;
    if (!actions[name]) {
        console.log(`❌ Acción no válida. Disponibles: ${Object.keys(actions).join(', ')}`);
        return;
    }

    if (actions[name].ssh && !(await connectSsh())) return;
    await executeAction(name);
    console.log('🎉 Acción finalizada');
};

const main = async () => {
    try {
        loadConfig();
        await preDeploymentChecks();

        const args = getNamedArgs();
        if (args.env || args.action) {
            await manualDeploy(args);
        } else {
            await interactiveDeploy();
        }
    } catch (error) {
        console.error(`\n❌ ${error.message}`);
        process.exitCode = 1;
    } finally {
        ssh.dispose();
    }
};

module.exports = { spaBaseUrl, spaHtaccess, phpContainer };

if (require.main === module) {
    // Última red: un rechazo que escape de main() saldría como volcado de pila de Node.
    main().catch((error) => {
        console.error(`\n❌ ${error.message}`);
        process.exit(1);
    });
}
