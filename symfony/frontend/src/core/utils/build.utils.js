export const BUILD_MODES = ['production', 'dev']

const BUILD_ARG_PATTERN = /^--(client|mode)=(.+)$/

function describeUsage(availableClients) {
  return `Usage: bun run build [--client=<${availableClients.join('|')}>] [--mode=<${BUILD_MODES.join('|')}>]`
}

/**
 * Parses the non-interactive build flags. A missing flag is left undefined so the caller
 * can prompt for it; an invalid one is an error, never a silent fallback.
 */
export function parseBuildArgs(argv, availableClients) {
  const options = {}

  for (const arg of argv) {
    const match = arg.match(BUILD_ARG_PATTERN)
    if (!match) throw new Error(`Unknown build argument "${arg}". ${describeUsage(availableClients)}`)
    const [, name, value] = match
    options[name] = value
  }

  if (options.mode !== undefined && !BUILD_MODES.includes(options.mode)) {
    throw new Error(`Unknown build mode "${options.mode}". ${describeUsage(availableClients)}`)
  }
  if (options.client !== undefined && !availableClients.includes(options.client)) {
    throw new Error(`Unknown client "${options.client}". ${describeUsage(availableClients)}`)
  }

  return options
}

const DEFAULT_CLIENT = 'default'
const DEFAULT_FAVICON = 'src/contexts/shared/assets/images/favicon.ico'

/**
 * A client's own favicon wins; the template's is the fallback. No favicon at all is an error,
 * because the build would otherwise ship without one and nobody would notice.
 */
export function resolveFaviconSource(client, exists) {
  const candidates =
    client && client !== DEFAULT_CLIENT
      ? [`src/clients/${client}/shared/assets/images/favicon.ico`, DEFAULT_FAVICON]
      : [DEFAULT_FAVICON]
  const source = candidates.find(exists)
  if (!source) throw new Error(`No favicon found. Looked in: ${candidates.join(', ')}`)
  return source
}

const LOCALE_REGION_SUFFIX = /-\w+$/

/**
 * Maps a Localise export key to its file: "en" is a template locale, "en_<client>" a client override,
 * and a region suffix such as "es-ES" is dropped because the app keys locales by language only.
 */
export function resolveLocaleFilePath(exportKey, localesDir) {
  const [locale, clientName] = exportKey.replace(LOCALE_REGION_SUFFIX, '').split('_')
  if (clientName) return `${localesDir}/clients/${clientName}/${locale}.json`
  return `${localesDir}/${locale}.json`
}
