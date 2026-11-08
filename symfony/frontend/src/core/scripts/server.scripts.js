import { clientSelector, environmentSelector, mockServerSelector } from '../utils/server.utils.js'
import { runNodeCommand } from '../utils/compile.utils.js'

async function buildProject(client, env, mock) {
  await runNodeCommand(`bun build-favicon ${client}`)
  console.log('✅  Build favicon')
  await runNodeCommand(`bun locales ${client}`)
  console.log('✅  Locales downloaded')
  await runNodeCommand(`cross-env VITE_CLIENT=${client} START_MOCK_SERVER=${mock} vite --mode=${env}`, {
    silentMode: false,
    loadingMode: false,
  })
  console.log('✅  Server started')
}

async function configProject() {
  const { result: client } = await clientSelector()
  const { result: env } = await environmentSelector()
  const { result: mock } = await mockServerSelector()
  await buildProject(client, env, mock === 'Si')
}

configProject().catch((e) => console.log('❌  Build error:', e.message))
