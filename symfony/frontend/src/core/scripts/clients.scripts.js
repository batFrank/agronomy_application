import * as mainConfig from '../../contexts/shared/assets/js/settings.js'
import { deepMerge } from '../utils/misc.utils.js'
import { globSync } from 'glob'
import { resolve } from 'path'

export async function loadClientConfig(folderName) {
  const clientList = globSync('./src/clients/*/shared/assets/js/settings.js')
  const configUrl = clientList.find((route) => route.includes(`src/clients/${folderName}/`))
  let clientConfig = { default: {} }
  if (configUrl) {
    clientConfig = await import(resolve(configUrl))
  }
  return deepMerge(mainConfig.default, clientConfig.default)
}
