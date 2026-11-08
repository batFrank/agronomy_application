import { clientSelector, mockServerSelector } from '../utils/server.utils.js'
import { runNodeCommand } from '../utils/compile.utils.js'

async function buildProject(client, env, mock) {
  await runNodeCommand(`bun locales ${client}`)
  console.log('✅  Locales downloaded')
  await runNodeCommand(`cross-env VITE_CLIENT=${client} START_MOCK_SERVER=${mock} vitest --mode=${env}`, {
    silentMode: false,
    loadingMode: false,
  })
  console.log('✅  Tests successfully passed')
}

async function configProject() {
  const { result: client } = await clientSelector()
  const { result: mock } = await mockServerSelector()
  await buildProject(client, 'dev', mock === 'Si')
}

configProject().catch((e) => console.log('❌  Test error:', e.message))
