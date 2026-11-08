import { runNodeCommand } from '../utils/compile.utils.js'

async function formatFiles() {
  try {
    console.log('🗃️ Run Prettier')
    await runNodeCommand('bun prettier')
    console.log('🖌️ Run Linter')
    await runNodeCommand('bun lint')
    console.log('✅   Finished')
  } catch (e) {
    console.log('❌ Format error')
    console.log('---------------------------------')
    console.log(e)
    console.log('---------------------------------')
  }
}

formatFiles().then(() => {})
