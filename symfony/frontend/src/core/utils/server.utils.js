import { globSync } from 'glob'
import path from 'path'
import enquirer from 'enquirer'

function promptFactory(message, choices) {
  return enquirer.prompt([
    {
      type: 'select',
      name: 'result',
      choices,
      message,
    },
  ])
}

export function listClients() {
  return ['default', ...globSync('./src/clients/*').map((client) => path.basename(client))]
}

export function clientSelector() {
  const clientList = listClients()
  if (clientList.length < 2) {
    return { result: 'default' }
  }

  return promptFactory('Selecciona el cliente', clientList)
}

export async function environmentSelector() {
  const envList = ['dev', 'staging']
  return promptFactory('Selecciona el entorno', envList)
}

export async function mockServerSelector() {
  const envList = ['No', 'Si']
  return promptFactory('Desea inicializar el servidor offline', envList)
}
