import enquirer from 'enquirer'
import { generateVersionFile } from './version.js'
import { loadClientConfig } from './clients.scripts.js'
import { runNodeCommand } from '../utils/compile.utils.js'
import { clientSelector, listClients } from '../utils/server.utils.js'
import { BUILD_MODES, parseBuildArgs } from '../utils/build.utils.js'

const DIST_DIR = 'dist'
// Served by the web server as a sibling of the app, see docs/component-catalog.md.
const COMPONENT_CATALOG_DIR = `${DIST_DIR}/storybook`

function modeSelector() {
  return enquirer.prompt([{ type: 'select', name: 'result', message: 'Select the build mode', choices: BUILD_MODES }])
}

function confirmBuild(client, mode) {
  return enquirer.prompt([
    { type: 'confirm', name: 'result', message: `Build client "${client}" in ${mode} mode into ${DIST_DIR}/?` },
  ])
}

/** Flags given on the command line win; anything missing is asked interactively. */
async function resolveBuildOptions(argv) {
  const cliOptions = parseBuildArgs(argv, listClients())
  const isInteractive = cliOptions.client === undefined || cliOptions.mode === undefined

  const client = cliOptions.client ?? (await clientSelector()).result
  const mode = cliOptions.mode ?? (await modeSelector()).result

  if (isInteractive && !(await confirmBuild(client, mode)).result) return null
  return { client, mode }
}

async function buildApp(client, mode) {
  await runNodeCommand(`bun build-favicon ${client}`)
  console.log('✅  Favicon copied')
  await runNodeCommand(`bun locales ${client}`)
  console.log('✅  Locales downloaded')
  await runNodeCommand(`cross-env VITE_CLIENT=${client} vite build --mode=${mode}`, { loadingMode: true })
  generateVersionFile(DIST_DIR)
  console.log(`\r✅  App built in ${DIST_DIR}/`)
}

async function buildComponentCatalog(client) {
  await runNodeCommand(`cross-env VITE_CLIENT=${client} storybook build --output-dir ${COMPONENT_CATALOG_DIR}`, {
    loadingMode: true,
  })
  console.log(`\r✅  Component catalog built in ${COMPONENT_CATALOG_DIR}/`)
}

async function build() {
  const options = await resolveBuildOptions(process.argv.slice(2))
  if (!options) {
    console.log('❌  Build cancelled')
    return
  }

  const { client, mode } = options
  const clientConfig = await loadClientConfig(client)

  await buildApp(client, mode)
  if (clientConfig.BUILD_COMPONENT_CATALOG) await buildComponentCatalog(client)
}

build().catch((error) => {
  console.error('❌  Build error:', error.message)
  process.exitCode = 1
})
