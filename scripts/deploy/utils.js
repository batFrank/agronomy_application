const { spawn, spawnSync } = require('child_process');
const fs = require('fs');

const printLoader = () => {
    const shapes = ['\\', '|', '/', '-'];
    let index = 0;
    return setInterval(() => {
        process.stdout.write(`\r${shapes[index]}`);
        index += 1;
        index &= 3;
    }, 250);
};

/**
 * Ejecuta un comando local en un shell y devuelve una promesa.
 * Rechaza con error si el código de salida no es 0.
 */
const runNodeCommand = (command, { silentMode, loadingMode } = { silentMode: true, loadingMode: true }) => {
    const interval = loadingMode ? printLoader() : null;

    return new Promise((resolve, reject) => {
        const task = spawn(command, { shell: true });
        let stdoutData = '';
        let stderrData = '';

        task.stdout.on('data', (data) => {
            const dataStr = data.toString();
            stdoutData += dataStr;
            if (!silentMode) process.stdout.write(dataStr);
        });
        task.stderr.on('data', (data) => {
            const dataStr = data.toString();
            stderrData += dataStr;
            if (!silentMode) process.stdout.write(dataStr);
        });

        task.on('error', (error) => {
            if (interval) clearInterval(interval);
            reject(error);
        });

        task.on('close', (code) => {
            if (interval) clearInterval(interval);

            if (code === 0) {
                resolve(stdoutData.trim());
                return;
            }

            console.log(`\nComando fallido: ${command}`);
            if (stderrData) {
                console.log('--- stderr ---');
                console.log(stderrData);
            }
            if (stdoutData && silentMode) {
                console.log('--- stdout ---');
                console.log(stdoutData);
            }
            const error = new Error(`El comando falló con el código de salida ${code}: ${command}`);
            error.exitCode = code;
            error.command = command;
            error.stderr = stderrData;
            reject(error);
        });
    });
};

const getNamedArgs = () => {
    const args = process.argv.slice(2);
    const namedArgs = {};

    args.forEach((arg) => {
        const [key, value] = arg.split('=');
        if (key.startsWith('--')) {
            namedArgs[key.slice(2)] = value === undefined ? true : value;
        }
    });

    return namedArgs;
};

const getLocalBranches = () => {
    const result = spawnSync('git', ['branch', '--format=%(refname:short)']);
    const branches = result.stdout.toString().trim().split('\n').filter(Boolean);

    const priority = ['main', 'develop', 'dev'];
    const prioritized = priority.filter((b) => branches.includes(b));
    const rest = branches.filter((b) => !priority.includes(b)).sort();

    return [...prioritized, ...rest];
};

const getCurrentBranch = () => {
    const result = spawnSync('git', ['rev-parse', '--abbrev-ref', 'HEAD']);
    return result.stdout.toString().trim();
};

const timestamp = () => {
    const now = new Date();
    const pad = (n) => `${n}`.padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}_${pad(now.getHours())}-${pad(now.getMinutes())}-${pad(now.getSeconds())}`;
};

// Nombres de las variables declaradas en un fichero .env, en orden de aparición.
// El parseo es deliberadamente simple: solo interesan las claves, así que no hay
// que resolver comillas, interpolaciones ni referencias entre variables.
const parseEnvKeys = (filePath) => {
    if (!fs.existsSync(filePath)) return [];

    const keys = [];
    for (const line of fs.readFileSync(filePath, 'utf8').split('\n')) {
        const match = line.match(/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=/);
        if (match && !keys.includes(match[1])) keys.push(match[1]);
    }
    return keys;
};

// Pares clave/valor de un fichero .env. Complemento de parseEnvKeys, con el
// mismo criterio: parseo simple, sin interpolación ni referencias entre
// variables. Se quitan las comillas envolventes y el comentario final porque es
// lo que hace dotenv (y por tanto loadEnv de Vite) antes de entregar el valor.
const parseEnvValues = (filePath) => {
    if (!fs.existsSync(filePath)) return {};

    const values = {};
    for (const line of fs.readFileSync(filePath, 'utf8').split('\n')) {
        const match = line.match(/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/);
        if (!match) continue;
        const raw = match[2].trim();
        const quoted = raw.match(/^(['"])([\s\S]*)\1$/);
        values[match[1]] = quoted ? quoted[2] : raw.replace(/\s+#.*$/, '');
    }
    return values;
};

// Entrecomillado para un fichero .env leído por Symfony. Las comillas simples
// preservan el valor tal cual (ni expansión de $VAR ni secuencias de escape),
// así que son la opción por defecto. Solo cuando el valor ya contiene una
// comilla simple hay que recurrir a las dobles, donde sí hay que escapar la
// barra, la comilla, el dólar y los saltos de línea.
const envQuote = (value) => {
    const raw = String(value);
    if (!raw.includes("'")) return `'${raw}'`;

    return `"${raw.replace(/([\\"$])/g, '\\$1').replace(/\n/g, '\\n')}"`;
};

// Misma normalización que frontend/vite.config.js:9, que es quien decide la
// «base» con la que se construye la SPA: se quitan la barra inicial y la final
// y se devuelve la ruta con ambas. Si divergen, el .htaccess apunta a una ruta
// distinta de la que el bundle tiene compilada y los deep links dan 404.
// El .trim() no está en vite.config.js porque ahí lo hace dotenv antes.
const normalizeBaseUrl = (value) => {
    const raw = String(value ?? '').trim() || 'app';
    const dir = raw.replace(/(^\/|\/$)/gi, '');
    return dir === '' ? '/' : `/${dir}/`;
};

// Valor de una variable declarada en un Makefile. Como parseEnvKeys, el parseo
// es deliberadamente simple: solo asignaciones literales de una línea (=, :=,
// ?=). Si el valor pasa a calcularse con $(shell ...) devuelve undefined y quien
// llama cae a su valor por defecto. «name» viene siempre de código propio, no de
// entrada del usuario, así que no hace falta escaparlo para la expresión.
const parseMakefileVar = (filePath, name) => {
    if (!fs.existsSync(filePath)) return undefined;

    const match = fs.readFileSync(filePath, 'utf8').match(new RegExp(`^\\s*${name}\\s*[:?]?=\\s*(.*)$`, 'm'));
    if (!match) return undefined;

    const value = match[1].replace(/\s+#.*$/, '').trim();
    return value === '' || value.includes('$(') || value.includes('${') ? undefined : value;
};

module.exports = {
    printLoader,
    runNodeCommand,
    getNamedArgs,
    getLocalBranches,
    getCurrentBranch,
    timestamp,
    parseEnvKeys,
    parseEnvValues,
    envQuote,
    normalizeBaseUrl,
    parseMakefileVar,
};
