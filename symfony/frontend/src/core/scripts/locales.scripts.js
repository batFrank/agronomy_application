import { mkdir, writeFile } from 'node:fs/promises'
import path from 'node:path'
import axios from 'axios'
import { resolveLocaleFilePath } from '../utils/build.utils.js'

const LOCALES_DIR = 'src/core/locales'
const LOCALE_EXPORT_URL = 'https://localise.biz/api/export/all.json?status=translated'
const LOCALE_API_KEY = ''

async function saveLocale(exportKey, messages) {
  const filePath = resolveLocaleFilePath(exportKey, LOCALES_DIR)
  await mkdir(path.dirname(filePath), { recursive: true })
  // vue-i18n treats "@" as a linked-message marker, so a literal "@" has to be escaped.
  await writeFile(filePath, JSON.stringify(messages).replaceAll('@', "'{@}'"))
  console.log(`✅  Locale ${exportKey} saved to ${filePath}`)
}

async function downloadLocales() {
  if (!LOCALE_API_KEY) {
    console.log('ℹ️  No locale API key configured, nothing to download')
    return
  }

  const { data } = await axios.get(`${LOCALE_EXPORT_URL}&key=${LOCALE_API_KEY}`)
  await Promise.all(Object.entries(data).map(([exportKey, messages]) => saveLocale(exportKey, messages)))
}

downloadLocales().catch((error) => {
  console.error('❌  Locales error:', error.message)
  process.exitCode = 1
})
