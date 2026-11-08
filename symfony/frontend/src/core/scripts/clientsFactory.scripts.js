import inquirer from 'inquirer'
import fs from 'fs'

function promptFactory(message, type = 'input') {
  return inquirer.prompt([
    {
      type,
      name: 'result',
      message,
    },
  ])
}

function normalizeText(text = '', regexFilter) {
  return text.replace(regexFilter, '').trim()
}

async function generateNewClient() {
  const { result: folderName } = await promptFactory('Ingrese el directorio del cliente: ')
  const normalizedFolderName = normalizeText(folderName, /[^(a-zA-Z)]+/g)
  if (!normalizedFolderName) throw new Error(`Debe ingresar un directorio valido`)
  if (fs.existsSync(`./src/clients/${folderName}`)) throw new Error(`El directorio "${normalizedFolderName}" ya existe`)

  const { result: clientName } = await promptFactory('Ingrese el nombre del cliente: ')
  const normalizedClientName = normalizeText((clientName || '').replace(/\s{2,}/g, ' '), /(\W\s)+/g)
  if (!normalizedClientName) throw new Error(`Debe ingresar un nombre de cliente valido`)

  const message = `¿Está seguro de crear el directorio "src/clients/${normalizedFolderName}" para el cliente "${normalizedClientName}"?`
  const { result: validation } = await promptFactory(message, 'confirm')

  if (!validation) throw new Error('Se canceló la generación del cliente')

  const folderList = generateDefaultDirectory(normalizedFolderName)
  await generateSettingsJS(folderList.scripts, normalizedFolderName, normalizedClientName)
  await generateGlobalsSCSS(folderList.styles)

  console.log(`✅  Se han registrado los archivos necesarios para el cliente "${normalizedClientName}"`)
  console.log(`🖌️  Para cambiar los estilos, se debe editar el archivo ${folderList.styles}/globals.scss`)
  console.log(
    `🖼️  Para sustituir las imágenes por defecto, las imágenes del cliente se deben agregar en ${folderList.images}/*`
  )
  console.log(
    `🛠️  Se ha creado un archivo de configuración en ${folderList.scripts}/settings.js, debe editarlo solamente si es necesario.`
  )
}

function generateDefaultDirectory(folderName) {
  const folderList = {
    images: `./src/clients/${folderName}/shared/assets/images`,
    styles: `./src/clients/${folderName}/shared/assets/styles`,
    scripts: `./src/clients/${folderName}/shared/assets/js`,
  }

  Object.keys(folderList).forEach((key) => {
    fs.mkdirSync(folderList[key], { recursive: true })
  })

  return folderList
}
async function generateSettingsJS(basePath, FOLDER_NAME, TITLE) {
  const defaultSettings = `export default {
  FOLDER_NAME: '${FOLDER_NAME}',
  TITLE: '${TITLE}',
}
`
  fs.writeFileSync(`${basePath}/settings.js`, defaultSettings)
}
async function generateGlobalsSCSS(basePath) {
  const defaultGlobals = `@use '@/contexts/shared/assets/styles/globals' with (
  $color-primary: #2196f3
);`
  fs.writeFileSync(`${basePath}/globals.scss`, defaultGlobals)
}

generateNewClient().catch((e) => console.log(`❌  ${e.message}`))
