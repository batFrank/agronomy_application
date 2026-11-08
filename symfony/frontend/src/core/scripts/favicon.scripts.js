import { copyFileSync, existsSync } from 'node:fs'
import { resolveFaviconSource } from '../utils/build.utils.js'

// Vite copies public/ into dist/ verbatim, so this is where the app picks the favicon up.
const FAVICON_DESTINATION = 'public/favicon.ico'

try {
  const source = resolveFaviconSource(process.argv[2], existsSync)
  copyFileSync(source, FAVICON_DESTINATION)
  console.log(`✅  Favicon copied from ${source}`)
} catch (error) {
  console.error('❌  Favicon error:', error.message)
  process.exitCode = 1
}
